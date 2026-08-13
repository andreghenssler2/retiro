<?php

declare(strict_types=1);

/**
 * Cliente da API Asaas.
 *
 * O ambiente e as credenciais são definidos em Admin > Configurações > Bancário.
 * As credenciais salvas pelo painel ficam criptografadas no banco. Constantes
 * antigas em config/integracoes.php continuam aceitas somente como fallback.
 */
class AsaasService
{
    private HttpClientService $http;
    private string $apiKey;
    private string $baseUrl;
    private string $ambiente;
    private ConfiguracaoBancaria $configuracaoBancaria;
    private ?string $erroConfiguracao = null;

    public function __construct(?HttpClientService $http = null)
    {
        $this->http = $http ?? new HttpClientService([
            "timeout" => 20.0,
            "connect_timeout" => 8.0
        ]);

        $this->configuracaoBancaria = new ConfiguracaoBancaria();
        $this->ambiente = $this->configuracaoBancaria->ambiente();
        $this->apiKey = $this->configuracaoBancaria->apiKey($this->ambiente);
        $this->baseUrl = $this->configuracaoBancaria->apiUrl($this->ambiente);

        $this->validarChaveDoAmbiente();
    }

    public function estaConfigurado(): bool
    {
        return $this->configuracaoBancaria->ativo()
            && $this->erroConfiguracao === null
            && $this->configuracaoBancaria->credencialConfigurada("api", $this->ambiente);
    }

    public function ambiente(): string
    {
        return in_array($this->ambiente, ["producao", "production", "prod"], true)
            ? "producao"
            : "sandbox";
    }

    /**
     * Prefixo estável usado nas referências externas.
     * Evita colisões entre localhost, produção e cópias diferentes do banco.
     */
    public function prefixoReferencia(): string
    {
        $configurado = $this->configuracaoBancaria->prefixoReferencia();

        if ($configurado !== "") {
            $base = $configurado;
        } else {
            $host = trim((string) ($_SERVER["HTTP_HOST"] ?? ""));
            $host = preg_replace('/:\d+$/', '', strtolower($host)) ?? "";
            $base = $host !== "" ? $host : Title::getAtual()->getSigla();
        }

        $base = preg_replace('/[^a-z0-9]+/i', '-', strtolower($base)) ?? Title::getAtual()->getSigla();
        $base = trim($base, '-');

        return substr($base !== "" ? $base : Title::getAtual()->getSigla(), 0, 40);
    }

    public function consultarCliente(string $idAsaas): array
    {
        return $this->requisitar(
            "GET",
            "/customers/" . rawurlencode($this->idValido($idAsaas))
        );
    }

        /**
     * Desabilita as notificações padrão do Asaas
     * para o cliente.
     *
     * O site é a única camada responsável pelas
     * comunicações com o participante.
     */
    public function desabilitarNotificacoesCliente(
        string $idAsaas
    ): array {
        $idAsaas =
            $this->idValido(
                $idAsaas
            );

        $cliente =
            $this->requisitar(
                "PUT",
                "/customers/"
                    . rawurlencode(
                        $idAsaas
                    ),
                [
                    "notificationDisabled"
                        => true
                ]
            );

        if (
            array_key_exists(
                "notificationDisabled",
                $cliente
            )
            && $cliente[
                "notificationDisabled"
            ] !== true
        ) {
            throw new RuntimeException(
                "O Asaas não confirmou "
                . "a desativação das "
                . "notificações do cliente."
            );
        }

        return $cliente;
    }

public function consultarClienteOuNull(string $idAsaas): ?array
    {
        try {
            return $this->consultarCliente($idAsaas);
        } catch (RuntimeException $erro) {
            if (str_contains($erro->getMessage(), "HTTP 404")) {
                return null;
            }

            throw $erro;
        }
    }

    public function consultarCobrancaOuNull(string $idAsaas): ?array
    {
        try {
            return $this->consultarCobranca($idAsaas);
        } catch (RuntimeException $erro) {
            if (str_contains($erro->getMessage(), "HTTP 404")) {
                return null;
            }

            throw $erro;
        }
    }

        public function obterOuCriarCliente(
        array $dados,
        ?string $clienteExistente = null
    ): array {
        $clienteExistente =
            trim(
                (string) (
                    $clienteExistente
                    ?? ""
                )
            );

        $cpfCnpj =
            preg_replace(
                '/\D+/',
                '',
                (string) (
                    $dados["cpfCnpj"]
                    ?? ""
                )
            )
            ?? "";

        $referencia =
            trim(
                (string) (
                    $dados[
                        "externalReference"
                    ]
                    ?? ""
                )
            );

        /*
         * Cliente já vinculado no banco local.
         */
        if ($clienteExistente !== "") {
            $clienteValidado =
                $this
                    ->consultarClienteOuNull(
                        $clienteExistente
                    );

            if (
                $clienteValidado
                !== null
            ) {
                $cpfExistente =
                    preg_replace(
                        '/\D+/',
                        '',
                        (string) (
                            $clienteValidado[
                                "cpfCnpj"
                            ]
                            ?? ""
                        )
                    )
                    ?? "";

                $referenciaExistente =
                    trim(
                        (string) (
                            $clienteValidado[
                                "externalReference"
                            ]
                            ?? ""
                        )
                    );

                $mesmoCpf =
                    $cpfCnpj !== ""
                    && $cpfExistente !== ""
                    && hash_equals(
                        $cpfCnpj,
                        $cpfExistente
                    );

                $mesmaReferencia =
                    $referencia !== ""
                    && $referenciaExistente !== ""
                    && hash_equals(
                        $referencia,
                        $referenciaExistente
                    );

                if (
                    $mesmoCpf
                    || (
                        $cpfCnpj === ""
                        && $mesmaReferencia
                    )
                ) {
                    $clienteValidado =
                        $this
                            ->desabilitarNotificacoesCliente(
                                (string) (
                                    $clienteValidado[
                                        "id"
                                    ]
                                    ?? $clienteExistente
                                )
                            );

                    $clienteValidado[
                        "reutilizado"
                    ] = true;

                    return $clienteValidado;
                }
            }
        }

        /*
         * Procura cliente existente no ambiente
         * atual do Asaas por CPF/referência.
         */
        $localizado =
            $this->localizarCliente(
                $cpfCnpj,
                $referencia
            );

        if ($localizado !== null) {
            $idLocalizado =
                trim(
                    (string) (
                        $localizado["id"]
                        ?? ""
                    )
                );

            if ($idLocalizado === "") {
                throw new RuntimeException(
                    "O cliente localizado "
                    . "no Asaas não possui "
                    . "identificador."
                );
            }

            $localizado =
                $this
                    ->desabilitarNotificacoesCliente(
                        $idLocalizado
                    );

            $localizado[
                "reutilizado"
            ] = true;

            return $localizado;
        }

        /*
         * Novo cliente.
         */
        $nome =
            trim(
                (string) (
                    $dados["name"]
                    ?? ""
                )
            );

        if ($nome === "") {
            throw new InvalidArgumentException(
                "O nome do participante "
                . "é obrigatório para gerar "
                . "a cobrança no Asaas."
            );
        }

        if (
            !in_array(
                strlen($cpfCnpj),
                [11, 14],
                true
            )
        ) {
            throw new InvalidArgumentException(
                "O participante precisa ter "
                . "um CPF ou CNPJ válido "
                . "para gerar a cobrança "
                . "no Asaas."
            );
        }

        $payload = [
            "name" => $nome,
            "cpfCnpj" => $cpfCnpj,

            "externalReference" =>
                $referencia !== ""
                    ? $referencia
                    : null,

            /*
             * REGRA DO SISTEMA:
             *
             * Asaas = processador financeiro.
             * Site = comunicação com participante.
             */
            "notificationDisabled"
                => true
        ];

        $email =
            trim(
                (string) (
                    $dados["email"]
                    ?? ""
                )
            );

        if (
            $email !== ""
            && filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            $payload["email"] =
                $email;
        }

        $telefone =
            preg_replace(
                '/\D+/',
                '',
                (string) (
                    $dados[
                        "mobilePhone"
                    ]
                    ?? ""
                )
            )
            ?? "";

        if ($telefone !== "") {
            $payload["mobilePhone"] =
                $telefone;
        }

        $payload =
            array_filter(
                $payload,
                static function (
                    mixed $valor
                ): bool {
                    return
                        $valor !== null
                        && $valor !== "";
                }
            );

        $resposta =
            $this->requisitar(
                "POST",
                "/customers",
                $payload
            );

        $id =
            trim(
                (string) (
                    $resposta["id"]
                    ?? ""
                )
            );

        if ($id === "") {
            throw new RuntimeException(
                "O Asaas não retornou "
                . "o identificador do "
                . "cliente criado."
            );
        }

        $resposta[
            "reutilizado"
        ] = false;

        return $resposta;
    }

    public function localizarCliente(string $cpfCnpj, string $externalReference = ""): ?array
    {
        $cpfCnpj = preg_replace('/\D+/', '', $cpfCnpj) ?? "";
        $externalReference = trim($externalReference);

        $consultas = [];

        if ($cpfCnpj !== "") {
            $consultas[] = ["cpfCnpj" => $cpfCnpj, "limit" => 1];
        }

        if ($externalReference !== "") {
            $consultas[] = ["externalReference" => $externalReference, "limit" => 1];
        }

        foreach ($consultas as $query) {
            $resposta = $this->requisitar("GET", "/customers", [], ["query" => $query]);
            $lista = $resposta["data"] ?? [];

            if (!is_array($lista) || !$lista) {
                continue;
            }

            $cliente = $lista[0] ?? null;

            if (is_array($cliente)) {
                return $cliente;
            }
        }

        return null;
    }

    public function criarCobranca(array $dados): array
    {
        $billingType = strtoupper(trim((string) ($dados["billingType"] ?? "")));

        if (!in_array($billingType, ["PIX", "BOLETO", "CREDIT_CARD"], true)) {
            throw new InvalidArgumentException("Forma de pagamento inválida para a integração com o Asaas.");
        }

        $customer = trim((string) ($dados["customer"] ?? ""));
        $valor = (float) ($dados["value"] ?? 0);
        $vencimento = trim((string) ($dados["dueDate"] ?? ""));

        if ($customer === "") {
            throw new InvalidArgumentException("Cliente Asaas não informado.");
        }

        if ($valor <= 0) {
            throw new InvalidArgumentException("O valor da cobrança deve ser maior que zero.");
        }

        if (!$this->dataValida($vencimento)) {
            throw new InvalidArgumentException("A data de vencimento da cobrança é inválida.");
        }

        $descricao = trim((string) ($dados["description"] ?? ""));
        $descricao = function_exists("mb_substr")
            ? mb_substr($descricao, 0, 500, "UTF-8")
            : substr($descricao, 0, 500);

        $payload = [
            "customer" => $customer,
            "billingType" => $billingType,
            "value" => round($valor, 2),
            "dueDate" => $vencimento,
            "description" => $descricao,
            "externalReference" => trim((string) ($dados["externalReference"] ?? ""))
        ];

        if ($billingType === "BOLETO") {
            /*
             * O Asaas recebe a quantidade equivalente de dias corridos até o
             * terceiro dia útil após o vencimento. O cron local permanece como
             * garantia de sincronização e cancela a inscrição na mesma regra.
             */
            $payload["daysAfterDueDateToRegistrationCancellation"] =
                $this->diasCorridosAteDiasUteis($vencimento, 3);
        }

        $payload = array_filter(
            $payload,
            static fn (mixed $valorPayload): bool => $valorPayload !== null && $valorPayload !== ""
        );

        return $this->requisitar("POST", "/payments", $payload);
    }

    /**
     * Converte uma tolerância em dias úteis para a quantidade de dias corridos
     * exigida pelo campo daysAfterDueDateToRegistrationCancellation do Asaas.
     * Sábados e domingos não são contados como dias úteis.
     */
    private function diasCorridosAteDiasUteis(
        string $dataVencimento,
        int $diasUteis
    ): int {
        $data = new DateTimeImmutable($dataVencimento);
        $cursor = $data;
        $uteis = 0;

        while ($uteis < $diasUteis) {
            $cursor = $cursor->modify("+1 day");

            if ((int) $cursor->format("N") <= 5) {
                $uteis++;
            }
        }

        return max(1, (int) $data->diff($cursor)->format("%a"));
    }

    /**
     * Recupera as tarifas vigentes da conta Asaas selecionada em
     * Configurações > Bancário.
     */
    public function recuperarTaxasConta(): array
    {
        return $this->requisitar("GET", "/myAccount/fees/");
    }

    public function consultarCobranca(string $idAsaas): array
    {
        return $this->requisitar("GET", "/payments/" . rawurlencode($this->idValido($idAsaas)));
    }

    public function localizarCobrancaPorReferencia(string $externalReference): ?array
    {
        $externalReference = trim($externalReference);

        if ($externalReference === "") {
            return null;
        }

        $resposta = $this->requisitar(
            "GET",
            "/payments",
            [],
            [
                "query" => [
                    "externalReference" => $externalReference,
                    "limit" => 1
                ]
            ]
        );

        $lista = $resposta["data"] ?? [];

        if (!is_array($lista) || !$lista) {
            return null;
        }

        $cobranca = $lista[0] ?? null;
        return is_array($cobranca) ? $cobranca : null;
    }

    public function excluirCobranca(string $idAsaas): bool
    {
        $resposta = $this->requisitar("DELETE", "/payments/" . rawurlencode($this->idValido($idAsaas)));
        return (bool) ($resposta["deleted"] ?? false);
    }

    /**
     * Solicita o estorno integral de uma cobrança recebida no Asaas.
     *
     * O valor não é enviado, portanto a API utiliza o valor total disponível
     * para estorno. O endpoint é indicado para cobranças pagas via PIX ou
     * cartão de crédito.
     */
    /**
     * Inicia o estorno de uma cobrança paga por boleto.
     *
     * O Asaas retorna requestUrl para o pagador informar
     * dados bancários e documentos. Isso inicia o processo,
     * mas não significa que o estorno já foi concluído.
     */
    public function solicitarEstornoBoleto(
        string $idAsaas
    ): array {
        return $this->requisitar(
            "POST",
            "/payments/"
                . rawurlencode(
                    $this->idValido($idAsaas)
                )
                . "/bankSlip/refund",
            []
        );
    }

    public function estornarCobranca(
        string $idAsaas,
        string $descricao = ""
    ): array {
        $payload = [];
        $descricao = trim($descricao);

        if ($descricao !== "") {
            $payload["description"] = function_exists("mb_substr")
                ? mb_substr($descricao, 0, 500, "UTF-8")
                : substr($descricao, 0, 500);
        }

        return $this->requisitar(
            "POST",
            "/payments/" . rawurlencode($this->idValido($idAsaas)) . "/refund",
            $payload
        );
    }


    /**
     * Paga uma cobrança existente usando cartão informado
     * no checkout transparente.
     *
     * ATENÇÃO:
     * os arrays recebidos contêm dados sensíveis e não devem
     * ser gravados em banco, sessão ou logs.
     */
    public function pagarCobrancaComCartao(
        string $idAsaas,
        array $cartao,
        array $titular
    ): array {
        $numero = preg_replace(
            '/\D+/',
            '',
            (string) ($cartao["number"] ?? "")
        ) ?? "";

        $ccv = preg_replace(
            '/\D+/',
            '',
            (string) ($cartao["ccv"] ?? "")
        ) ?? "";

        $mes = str_pad(
            preg_replace(
                '/\D+/',
                '',
                (string) ($cartao["expiryMonth"] ?? "")
            ) ?? "",
            2,
            "0",
            STR_PAD_LEFT
        );

        $ano = preg_replace(
            '/\D+/',
            '',
            (string) ($cartao["expiryYear"] ?? "")
        ) ?? "";

        $nomeImpresso = trim(
            (string) ($cartao["holderName"] ?? "")
        );

        if (
            strlen($numero) < 13
            || strlen($numero) > 19
            || !in_array(strlen($ccv), [3, 4], true)
            || !preg_match('/^(0[1-9]|1[0-2])$/', $mes)
            || !preg_match('/^\d{4}$/', $ano)
            || $nomeImpresso === ""
        ) {
            throw new InvalidArgumentException(
                "Dados do cartão inválidos."
            );
        }

        $payload = [
            "creditCard" => [
                "holderName" => $nomeImpresso,
                "number" => $numero,
                "expiryMonth" => $mes,
                "expiryYear" => $ano,
                "ccv" => $ccv
            ],
            "creditCardHolderInfo" => [
                "name" => trim(
                    (string) ($titular["name"] ?? "")
                ),
                "email" => trim(
                    (string) ($titular["email"] ?? "")
                ),
                "cpfCnpj" => preg_replace(
                    '/\D+/',
                    '',
                    (string) ($titular["cpfCnpj"] ?? "")
                ) ?? "",
                "postalCode" => preg_replace(
                    '/\D+/',
                    '',
                    (string) ($titular["postalCode"] ?? "")
                ) ?? "",
                "addressNumber" => trim(
                    (string) (
                        $titular["addressNumber"]
                        ?? ""
                    )
                ),
                "addressComplement" => trim(
                    (string) (
                        $titular["addressComplement"]
                        ?? ""
                    )
                ) ?: null,
                "phone" => preg_replace(
                    '/\D+/',
                    '',
                    (string) ($titular["phone"] ?? "")
                ) ?: null,
                "mobilePhone" => preg_replace(
                    '/\D+/',
                    '',
                    (string) (
                        $titular["mobilePhone"]
                        ?? $titular["phone"]
                        ?? ""
                    )
                ) ?: null
            ]
        ];

        $resposta = $this->requisitar(
            "POST",
            "/payments/"
                . rawurlencode(
                    $this->idValido($idAsaas)
                )
                . "/payWithCreditCard",
            $payload,
            [
                /*
                 * Processamento de cartão pode levar mais tempo.
                 * Evita timeout curto no checkout.
                 */
                "timeout" => 60.0,
                "connect_timeout" => 8.0
            ]
        );

        unset(
            $payload,
            $numero,
            $ccv,
            $mes,
            $ano,
            $nomeImpresso
        );

        return $resposta;
    }

    public function obterQrCodePix(string $idAsaas): array
    {
        return $this->requisitar(
            "GET",
            "/payments/" . rawurlencode($this->idValido($idAsaas)) . "/pixQrCode"
        );
    }

    public function obterLinhaDigitavel(string $idAsaas): array
    {
        return $this->requisitar(
            "GET",
            "/payments/" . rawurlencode($this->idValido($idAsaas)) . "/identificationField"
        );
    }

    private function requisitar(
        string $metodo,
        string $endpoint,
        array $payload = [],
        array $opcoes = []
    ): array {
        if ($this->erroConfiguracao !== null) {
            throw new RuntimeException($this->erroConfiguracao);
        }

        if (!$this->estaConfigurado()) {
            throw new RuntimeException(
                "A integração com o Asaas não está configurada para o ambiente selecionado em Configurações > Bancário."
            );
        }

        $cabecalhos = $opcoes["headers"] ?? [];
        $cabecalhos["access_token"] = $this->apiKey;
        $cabecalhos["Accept"] = "application/json";
        $opcoes["headers"] = $cabecalhos;

        $resposta = $this->http->requisitarJson(
            $metodo,
            $this->baseUrl . "/" . ltrim($endpoint, "/"),
            $payload,
            $opcoes
        );

        if (!$resposta["sucesso"]) {
            throw new RuntimeException($this->mensagemErro($resposta));
        }

        if (!is_array($resposta["dados"])) {
            throw new RuntimeException("O Asaas retornou uma resposta inválida.");
        }

        return $resposta["dados"];
    }

    private function mensagemErro(array $resposta): string
    {
        $status = (int) ($resposta["status"] ?? 0);
        $dados = $resposta["dados"] ?? null;
        $mensagens = [];

        if (is_array($dados)) {
            $erros = $dados["errors"] ?? [];

            if (is_array($erros)) {
                foreach ($erros as $erro) {
                    if (is_array($erro)) {
                        $descricao = trim((string) ($erro["description"] ?? $erro["message"] ?? ""));
                        if ($descricao !== "") {
                            $mensagens[] = $descricao;
                        }
                    }
                }
            }
        }

        $detalhe = $mensagens ? implode(" ", array_unique($mensagens)) : "Falha não detalhada pela API.";

        return "O Asaas retornou HTTP {$status}: {$detalhe}";
    }

    private function validarChaveDoAmbiente(): void
    {
        $this->erroConfiguracao = null;

        if ($this->apiKey === "" || !$this->configuracaoBancaria->ativo()) {
            return;
        }

        $sandbox = $this->ambiente() === "sandbox";
        $chaveSandbox = str_starts_with($this->apiKey, '$aact_hmlg_');
        $chaveProducao = str_starts_with($this->apiKey, '$aact_prod_');

        if ($sandbox && $chaveProducao) {
            $this->erroConfiguracao =
                "A configuração bancária está em Sandbox, mas a API Key disponível é de produção.";
            return;
        }

        if (!$sandbox && $chaveSandbox) {
            $this->erroConfiguracao =
                "A configuração bancária está em Produção, mas a API Key disponível é de Sandbox.";
        }
    }

    private function configuracao(string $nome, string $padrao = ""): string
    {
        if (defined($nome)) {
            return trim((string) constant($nome));
        }

        $valor = getenv($nome);
        return $valor === false ? $padrao : trim((string) $valor);
    }

    private function idValido(string $id): string
    {
        $id = trim($id);

        if ($id === "" || !preg_match('/^[A-Za-z0-9_-]+$/', $id)) {
            throw new InvalidArgumentException("Identificador Asaas inválido.");
        }

        return $id;
    }

    private function dataValida(string $data): bool
    {
        $objeto = DateTimeImmutable::createFromFormat("!Y-m-d", $data);
        $erros = DateTimeImmutable::getLastErrors();

        return $objeto instanceof DateTimeImmutable
            && ($erros === false || (($erros["warning_count"] ?? 0) === 0 && ($erros["error_count"] ?? 0) === 0))
            && $objeto->format("Y-m-d") === $data;
    }
}
