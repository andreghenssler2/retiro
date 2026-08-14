<?php

declare(strict_types=1);

/**
 * Inscrição pública sem exigir login.
 *
 * O e-mail validado é a credencial temporária do fluxo.
 * O CPF somente libera dados preexistentes quando pertence
 * ao mesmo e-mail que acabou de ser validado.
 */
class InscricaoPublicaService
{
    private PDO $db;
    private EventoInscricaoPublicaConfig $config;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connect();

        $this->db->setAttribute(
            PDO::ATTR_ERRMODE,
            PDO::ERRMODE_EXCEPTION
        );

        $this->db->setAttribute(
            PDO::ATTR_DEFAULT_FETCH_MODE,
            PDO::FETCH_ASSOC
        );

        $this->config =
            new EventoInscricaoPublicaConfig(
                $this->db
            );
    }

    public function buscarEvento(int $idEvento): array
    {
        if ($idEvento <= 0) {
            throw new InvalidArgumentException(
                "Evento inválido."
            );
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM eventos
            WHERE idEvento = :idEvento
              AND ativo = 1
            LIMIT 1
        ");

        $stmt->execute([
            ":idEvento" => $idEvento
        ]);

        $evento = $stmt->fetch();

        if (!$evento) {
            throw new RuntimeException(
                "Evento não encontrado."
            );
        }

        return $evento;
    }

    public function comunidades(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                nome_comunidade
            FROM minha_comunidade
            ORDER BY nome_comunidade ASC
        ");

        return $stmt->fetchAll();
    }

    public function termos(int $idEvento): array
    {
        return $this->config->listarTermos(
            $idEvento
        );
    }

    public function camisetas(int $idEvento): array
    {
        return $this->config->listarCamisetas(
            $idEvento
        );
    }

    public function enviarCodigo(
        int $idEvento,
        string $email
    ): array {
        $evento = $this->buscarEvento(
            $idEvento
        );

        $this->validarEventoAberto(
            $evento
        );

        $email = Usuario::normalizarEmail(
            $email
        );

        if (
            !filter_var(
                $email,
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new InvalidArgumentException(
                "Informe um e-mail válido."
            );
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM inscricao_publica_fluxos
            WHERE idEvento = :idEvento
              AND email = :email
              AND expira_em > NOW()
              AND concluido_em IS NULL
            ORDER BY idFluxo DESC
            LIMIT 1
        ");

        $stmt->execute([
            ":idEvento" => $idEvento,
            ":email" => $email
        ]);

        $fluxo = $stmt->fetch();

        if ($fluxo) {
            $ultimoEnvio = strtotime(
                (string) (
                    $fluxo["ultimo_envio_em"]
                    ?? ""
                )
            );

            if (
                $ultimoEnvio !== false
                && time() - $ultimoEnvio < 60
            ) {
                $faltam =
                    60 - (time() - $ultimoEnvio);

                throw new RuntimeException(
                    "Aguarde {$faltam} segundo(s) "
                    . "para solicitar outro código."
                );
            }

            $token = (string) $fluxo["token"];
            $idFluxo = (int) $fluxo["idFluxo"];
        } else {
            $token = bin2hex(
                random_bytes(32)
            );

            $stmt = $this->db->prepare("
                INSERT INTO
                    inscricao_publica_fluxos (
                        token,
                        idEvento,
                        email,
                        expira_em,
                        criado_em,
                        atualizado_em
                    )
                VALUES (
                    :token,
                    :idEvento,
                    :email,
                    DATE_ADD(
                        NOW(),
                        INTERVAL 2 HOUR
                    ),
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                ":token" => $token,
                ":idEvento" => $idEvento,
                ":email" => $email
            ]);

            $idFluxo =
                (int) $this->db
                    ->lastInsertId();
        }

        $codigo = (string)
            random_int(100000, 999999);

        $stmt = $this->db->prepare("
            UPDATE inscricao_publica_fluxos
            SET
                codigo_hash = :codigo_hash,
                codigo_expira_em =
                    DATE_ADD(
                        NOW(),
                        INTERVAL 10 MINUTE
                    ),
                tentativas_codigo = 0,
                email_verificado_em = NULL,
                cpf = NULL,
                ultimo_envio_em = NOW(),
                atualizado_em = NOW()
            WHERE idFluxo = :idFluxo
        ");

        $stmt->execute([
            ":codigo_hash" =>
                password_hash(
                    $codigo,
                    PASSWORD_DEFAULT
                ),
            ":idFluxo" => $idFluxo
        ]);

        $this->enviarEmailCodigo(
            $email,
            $codigo,
            (string) (
                $evento["titulo"]
                ?? "Evento"
            )
        );

        return [
            "token" => $token,
            "email" => $email,
            "expiraMinutos" => 10
        ];
    }

    public function validarCodigo(
        string $token,
        string $codigo
    ): array {
        $fluxo = $this->buscarFluxo(
            $token,
            false
        );

        if (
            !empty(
                $fluxo["email_verificado_em"]
            )
        ) {
            return [
                "token" => $token,
                "email" =>
                    (string) $fluxo["email"],
                "verificado" => true
            ];
        }

        if (
            (int) (
                $fluxo["tentativas_codigo"]
                ?? 0
            ) >= 5
        ) {
            throw new RuntimeException(
                "O limite de tentativas foi atingido. "
                . "Solicite um novo código."
            );
        }

        $expira = strtotime(
            (string) (
                $fluxo["codigo_expira_em"]
                ?? ""
            )
        );

        if (
            $expira === false
            || time() > $expira
        ) {
            throw new RuntimeException(
                "O código expirou. "
                . "Solicite um novo."
            );
        }

        $codigo = preg_replace(
            "/\D+/",
            "",
            $codigo
        ) ?? "";

        $valido =
            strlen($codigo) === 6
            && password_verify(
                $codigo,
                (string) (
                    $fluxo["codigo_hash"]
                    ?? ""
                )
            );

        if (!$valido) {
            $stmt = $this->db->prepare("
                UPDATE
                    inscricao_publica_fluxos
                SET
                    tentativas_codigo =
                        tentativas_codigo + 1,
                    atualizado_em = NOW()
                WHERE idFluxo = :idFluxo
            ");

            $stmt->execute([
                ":idFluxo" =>
                    (int) $fluxo["idFluxo"]
            ]);

            throw new RuntimeException(
                "Código de validação inválido."
            );
        }

        $stmt = $this->db->prepare("
            UPDATE inscricao_publica_fluxos
            SET
                email_verificado_em = NOW(),
                codigo_hash = NULL,
                codigo_expira_em = NULL,
                atualizado_em = NOW()
            WHERE idFluxo = :idFluxo
        ");

        $stmt->execute([
            ":idFluxo" =>
                (int) $fluxo["idFluxo"]
        ]);

        return [
            "token" => $token,
            "email" =>
                (string) $fluxo["email"],
            "verificado" => true
        ];
    }

    public function buscarPerfilPorCpf(
        string $token,
        string $cpf
    ): array {
        $fluxo = $this->buscarFluxo(
            $token,
            true
        );

        $cpf = Usuario::normalizarCpf(
            $cpf
        );

        if (!Usuario::cpfValido($cpf)) {
            throw new InvalidArgumentException(
                "Informe um CPF válido."
            );
        }

        $email = Usuario::normalizarEmail(
            (string) $fluxo["email"]
        );

        $usuarioPorCpf =
            $this->buscarUsuarioPorCpf(
                $cpf
            );

        $usuarioPorEmail =
            $this->buscarUsuarioPorEmail(
                $email
            );

        if (
            $usuarioPorCpf
            && Usuario::normalizarEmail(
                (string) (
                    $usuarioPorCpf["email"]
                    ?? ""
                )
            ) !== $email
        ) {
            throw new RuntimeException(
                "Este CPF já está vinculado "
                . "a outro e-mail no sistema. "
                . "Use o e-mail associado ao cadastro."
            );
        }

        if (
            $usuarioPorEmail
            && trim(
                (string) (
                    $usuarioPorEmail["cpf"]
                    ?? ""
                )
            ) !== ""
            && Usuario::normalizarCpf(
                (string) $usuarioPorEmail["cpf"]
            ) !== $cpf
        ) {
            throw new RuntimeException(
                "O e-mail validado já está "
                . "vinculado a outro CPF."
            );
        }

        if (
            $usuarioPorCpf
            && $usuarioPorEmail
            && (int) $usuarioPorCpf["id"]
                !== (int) $usuarioPorEmail["id"]
        ) {
            throw new RuntimeException(
                "Existe conflito entre o e-mail "
                . "e o CPF cadastrados."
            );
        }

        $usuario =
            $usuarioPorCpf
            ?: $usuarioPorEmail
            ?: null;

        $idUsuario = $usuario
            ? (int) $usuario["id"]
            : null;

        $stmt = $this->db->prepare("
            UPDATE inscricao_publica_fluxos
            SET
                cpf = :cpf,
                idUsuario = :idUsuario,
                atualizado_em = NOW()
            WHERE idFluxo = :idFluxo
        ");

        $stmt->bindValue(
            ":cpf",
            $cpf,
            PDO::PARAM_STR
        );

        if ($idUsuario !== null) {
            $stmt->bindValue(
                ":idUsuario",
                $idUsuario,
                PDO::PARAM_INT
            );
        } else {
            $stmt->bindValue(
                ":idUsuario",
                null,
                PDO::PARAM_NULL
            );
        }

        $stmt->bindValue(
            ":idFluxo",
            (int) $fluxo["idFluxo"],
            PDO::PARAM_INT
        );

        $stmt->execute();

        $perfil = [
            "encontrado" => false,
            "cpf" => $cpf,
            "email" => $email,
            "nome" => "",
            "nacionalidade" => "Brasileira",
            "data_nascimento" => "",
            "genero" => "",
            "telefone" => "",
            "pais" => "Brasil",
            "cep" => "",
            "logradouro" => "",
            "numero" => "",
            "complemento" => "",
            "bairro" => "",
            "cidade" => "",
            "estado" => "RS",
            "idComunidade" => "",
            "restricao_medicacao" => "0",
            "medicacao_detalhes" => "",
            "deficiencia" => "Não",
            "deficiencia_detalhes" => "",
            "precisa_acessibilidade" => "0",
            "acessibilidade_detalhes" => "",
            "restricao_alimentar" => "0",
            "alimentar_detalhes" => ""
        ];

        if ($usuario) {
            $perfil["encontrado"] = true;

            foreach (
                [
                    "nome",
                    "nacionalidade",
                    "data_nascimento",
                    "genero",
                    "telefone",
                    "pais",
                    "cep",
                    "logradouro",
                    "numero",
                    "complemento",
                    "bairro",
                    "cidade",
                    "estado",
                    "idComunidade"
                ]
                as $campo
            ) {
                if (
                    isset($usuario[$campo])
                    && trim(
                        (string) $usuario[$campo]
                    ) !== ""
                ) {
                    $perfil[$campo] =
                        (string) $usuario[$campo];
                }
            }
        }

        $historico =
            $this->buscarUltimoHistorico(
                $cpf,
                $email,
                $idUsuario
            );

        if ($historico) {
            $mapa = [
                "nome" => "nome",
                "data_nascimento" =>
                    "data_nascimento",
                "sexo" => "genero",
                "telefone" => "telefone",
                "nacionalidade" =>
                    "nacionalidade",
                "genero_extra" => "genero",
                "pais" => "pais",
                "cep" => "cep",
                "logradouro" => "logradouro",
                "numero" => "numero",
                "complemento" => "complemento",
                "bairro" => "bairro",
                "cidade_extra" => "cidade",
                "estado_extra" => "estado",
                "idComunidade_extra" =>
                    "idComunidade",
                "medicacao_detalhes" =>
                    "medicacao_detalhes",
                "deficiencia" =>
                    "deficiencia",
                "deficiencia_detalhes" =>
                    "deficiencia_detalhes",
                "acessibilidade_detalhes" =>
                    "acessibilidade_detalhes",
                "alimentar_detalhes" =>
                    "alimentar_detalhes"
            ];

            foreach (
                $mapa
                as $origem => $destino
            ) {
                if (
                    isset($historico[$origem])
                    && trim(
                        (string) $historico[$origem]
                    ) !== ""
                    && trim(
                        (string) (
                            $perfil[$destino]
                            ?? ""
                        )
                    ) === ""
                ) {
                    $perfil[$destino] =
                        (string) $historico[$origem];
                }
            }

            foreach (
                [
                    "restricao_medicacao",
                    "precisa_acessibilidade",
                    "restricao_alimentar"
                ]
                as $campo
            ) {
                if (
                    array_key_exists(
                        $campo,
                        $historico
                    )
                ) {
                    $perfil[$campo] =
                        (string) $historico[$campo];
                }
            }
        }

        return $perfil;
    }

    public function finalizar(
        string $token,
        array $dados
    ): array {
        $fluxo = $this->buscarFluxo(
            $token,
            true
        );

        $evento = $this->buscarEvento(
            (int) $fluxo["idEvento"]
        );

        $this->validarEventoAberto(
            $evento
        );

        $cpf = Usuario::normalizarCpf(
            (string) (
                $dados["cpf"]
                ?? $fluxo["cpf"]
                ?? ""
            )
        );

        if (
            !Usuario::cpfValido($cpf)
            || $cpf !== Usuario::normalizarCpf(
                (string) (
                    $fluxo["cpf"]
                    ?? ""
                )
            )
        ) {
            throw new InvalidArgumentException(
                "O CPF precisa ser validado "
                . "antes de concluir a inscrição."
            );
        }

        $email = Usuario::normalizarEmail(
            (string) $fluxo["email"]
        );

        $nome = trim(
            (string) ($dados["nome"] ?? "")
        );

        $nacionalidade = trim(
            (string) (
                $dados["nacionalidade"]
                ?? "Brasileira"
            )
        );

        $dataNascimento = trim(
            (string) (
                $dados["data_nascimento"]
                ?? ""
            )
        );

        $genero = trim(
            (string) (
                $dados["genero"]
                ?? ""
            )
        );

        $telefone = trim(
            (string) (
                $dados["telefone"]
                ?? ""
            )
        );

        $pais = trim(
            (string) (
                $dados["pais"]
                ?? "Brasil"
            )
        );

        $cep = preg_replace(
            "/\D+/",
            "",
            (string) (
                $dados["cep"]
                ?? ""
            )
        ) ?? "";

        $logradouro = trim(
            (string) (
                $dados["logradouro"]
                ?? ""
            )
        );

        $numero = trim(
            (string) (
                $dados["numero"]
                ?? ""
            )
        );

        $complemento = trim(
            (string) (
                $dados["complemento"]
                ?? ""
            )
        );

        $bairro = trim(
            (string) (
                $dados["bairro"]
                ?? ""
            )
        );

        $cidade = trim(
            (string) (
                $dados["cidade"]
                ?? ""
            )
        );

        $estado = strtoupper(
            trim(
                (string) (
                    $dados["estado"]
                    ?? ""
                )
            )
        );

        $idComunidade = (int) (
            $dados["idComunidade"]
            ?? 0
        );

        $visitante =
            (string) (
                $dados["visitante"]
                ?? "0"
            ) === "1"
                ? 1
                : 0;

        $valorVisitanteConfigurado =
            array_key_exists(
                "valor_visitante",
                $evento
            )
            && $evento["valor_visitante"]
                !== null
            && $evento["valor_visitante"]
                !== "";

        if (
            $visitante === 1
            && !$valorVisitanteConfigurado
        ) {
            throw new InvalidArgumentException(
                "Este evento não possui "
                . "valor especial para visitante."
            );
        }

        if ($nome === "") {
            throw new InvalidArgumentException(
                "Informe o nome completo."
            );
        }

        if (
            $nacionalidade === ""
            || $dataNascimento === ""
            || !$this->dataValida(
                $dataNascimento
            )
        ) {
            throw new InvalidArgumentException(
                "Informe nacionalidade e "
                . "data de nascimento válidas."
            );
        }

        if ($genero === "") {
            throw new InvalidArgumentException(
                "Informe o gênero."
            );
        }

        if (
            strlen(
                preg_replace(
                    "/\D+/",
                    "",
                    $telefone
                ) ?? ""
            ) < 10
        ) {
            throw new InvalidArgumentException(
                "Informe um telefone válido."
            );
        }

        if (
            $pais === ""
            || strlen($cep) !== 8
            || $logradouro === ""
            || $numero === ""
            || $bairro === ""
            || $cidade === ""
            || strlen($estado) !== 2
        ) {
            throw new InvalidArgumentException(
                "Preencha o endereço completo."
            );
        }

        if (
            $visitante === 0
            && !$this->comunidadeExiste(
                $idComunidade
            )
        ) {
            throw new InvalidArgumentException(
                "Selecione a comunidade/paróquia "
                . "ou marque a opção visitante."
            );
        }

        /*
         * A opção visitante é específica desta inscrição.
         * Ela não apaga uma comunidade já salva no perfil.
         */
        $idComunidadePerfil =
            $visitante === 1
                ? null
                : (
                    $idComunidade > 0
                        ? $idComunidade
                        : null
                );

        $this->validarIdade(
            $dataNascimento,
            $evento
        );

        $termos = $this->termos(
            (int) $evento["idEvento"]
        );

        $aceites = $dados["termos"] ?? [];

        if (!is_array($aceites)) {
            $aceites = [];
        }

        $aceitesString =
            array_map(
                "strval",
                $aceites
            );

        foreach ($termos as $termo) {
            if (
                (int) (
                    $termo["obrigatorio"]
                    ?? 0
                ) === 1
                && !in_array(
                    (string) $termo["idTermo"],
                    $aceitesString,
                    true
                )
            ) {
                throw new InvalidArgumentException(
                    "Aceite todos os termos "
                    . "obrigatórios para continuar."
                );
            }
        }

        $camiseta = null;

        if (
            (int) (
                $evento["camiseta_ativa"]
                ?? 0
            ) === 1
        ) {
            $opcoes = $this->camisetas(
                (int) $evento["idEvento"]
            );

            if ($opcoes !== []) {
                $camiseta = strtoupper(
                    trim(
                        (string) (
                            $dados["camiseta"]
                            ?? ""
                        )
                    )
                );

                if (
                    !in_array(
                        $camiseta,
                        $opcoes,
                        true
                    )
                ) {
                    throw new InvalidArgumentException(
                        "Selecione o tamanho "
                        . "da camiseta."
                    );
                }
            }
        }

        $restricaoMedicacao =
            (string) (
                $dados["restricao_medicacao"]
                ?? "0"
            ) === "1"
                ? 1
                : 0;

        $medicacaoDetalhes = trim(
            (string) (
                $dados["medicacao_detalhes"]
                ?? ""
            )
        );

        $deficiencia = trim(
            (string) (
                $dados["deficiencia"]
                ?? "Não"
            )
        );

        $deficienciaDetalhes = trim(
            (string) (
                $dados["deficiencia_detalhes"]
                ?? ""
            )
        );

        $precisaAcessibilidade =
            (string) (
                $dados[
                    "precisa_acessibilidade"
                ]
                ?? "0"
            ) === "1"
                ? 1
                : 0;

        $acessibilidadeDetalhes = trim(
            (string) (
                $dados[
                    "acessibilidade_detalhes"
                ]
                ?? ""
            )
        );

        $restricaoAlimentar =
            (string) (
                $dados["restricao_alimentar"]
                ?? "0"
            ) === "1"
                ? 1
                : 0;

        $alimentarDetalhes = trim(
            (string) (
                $dados["alimentar_detalhes"]
                ?? ""
            )
        );

        if (
            $restricaoMedicacao === 1
            && $medicacaoDetalhes === ""
        ) {
            throw new InvalidArgumentException(
                "Descreva a restrição "
                . "ou medicação."
            );
        }

        if (
            $precisaAcessibilidade === 1
            && $acessibilidadeDetalhes === ""
        ) {
            throw new InvalidArgumentException(
                "Informe o recurso de "
                . "acessibilidade necessário."
            );
        }

        if (
            $restricaoAlimentar === 1
            && $alimentarDetalhes === ""
        ) {
            throw new InvalidArgumentException(
                "Descreva a restrição alimentar."
            );
        }

        $valorPadrao = (float) (
            $evento["valor_inscricao"]
            ?? $evento["valor"]
            ?? 0
        );

        $valorVisitante =
            $valorVisitanteConfigurado
                ? (float) $evento[
                    "valor_visitante"
                ]
                : null;

        $valor =
            $visitante === 1
            && $valorVisitante !== null
                ? $valorVisitante
                : $valorPadrao;

        $valor = round(
            max(0, $valor),
            2
        );

        $pagamentoObrigatorio =
            (int) (
                $evento["pagamento_obrigatorio"]
                ?? 1
            ) === 1
            && $valor > 0;

        $idInscricao =
            (int) (
                $fluxo["idInscricao"]
                ?? 0
            );

        $idUsuario =
            (int) (
                $fluxo["idUsuario"]
                ?? 0
            );

        $idPagamento =
            (int) (
                $fluxo["idPagamento"]
                ?? 0
            );

        $iniciouTransacao =
            !$this->db->inTransaction();

        if ($iniciouTransacao) {
            $this->db->beginTransaction();
        }

        try {
            /*
             * O usuário é criado/vinculado somente
             * depois da validação e confirmação dos dados.
             */
            if ($idUsuario <= 0) {
                $idUsuario =
                    $this->obterOuCriarUsuario([
                        "email" => $email,
                        "cpf" => $cpf,
                        "nome" => $nome,
                        "nacionalidade" =>
                            $nacionalidade,
                        "data_nascimento" =>
                            $dataNascimento,
                        "genero" => $genero,
                        "telefone" => $telefone,
                        "pais" => $pais,
                        "cep" => $cep,
                        "logradouro" =>
                            $logradouro,
                        "numero" => $numero,
                        "complemento" =>
                            $complemento,
                        "bairro" => $bairro,
                        "cidade" => $cidade,
                        "estado" => $estado,
                        "idComunidade" =>
                            $idComunidadePerfil
                    ]);
            } else {
                /*
                 * Mesmo usuário encontrado no CPF/e-mail:
                 * atualiza os campos confirmados.
                 */
                $this->atualizarUsuarioExistente(
                    $idUsuario,
                    [
                        "email" => $email,
                        "cpf" => $cpf,
                        "nome" => $nome,
                        "nacionalidade" =>
                            $nacionalidade,
                        "data_nascimento" =>
                            $dataNascimento,
                        "genero" => $genero,
                        "telefone" => $telefone,
                        "pais" => $pais,
                        "cep" => $cep,
                        "logradouro" =>
                            $logradouro,
                        "numero" => $numero,
                        "complemento" =>
                            $complemento,
                        "bairro" => $bairro,
                        "cidade" => $cidade,
                        "estado" => $estado,
                        "idComunidade" =>
                            $idComunidadePerfil
                    ]
                );
            }

            if ($idInscricao <= 0) {
                $inscricaoAtiva =
                    $this->buscarInscricaoAtiva(
                        (int) $evento["idEvento"],
                        $idUsuario
                    );

                if ($inscricaoAtiva) {
                    $idInscricao =
                        (int) $inscricaoAtiva[
                            "idInscricao"
                        ];
                } else {
                    $this->validarVagas(
                        $evento
                    );

                    $inscricao =
                        new Inscricao(
                            $this->db
                        );

                    $ok = $inscricao->salvar([
                        "idEvento" =>
                            (int) $evento[
                                "idEvento"
                            ],
                        "idUsuario" =>
                            $idUsuario,
                        "nome" => $nome,
                        "cpf" => $cpf,
                        "rg" => null,
                        "email" => $email,
                        "telefone" => $telefone,
                        "sexo" => $genero,
                        "data_nascimento" =>
                            $dataNascimento,
                        "cidade" => $cidade,
                        "estado" => $estado,
                        "camiseta" => $camiseta,
                        "observacoes" => null,
                        "contato_emergencia" =>
                            null,
                        "telefone_emergencia" =>
                            null,
                        "status" => "Pendente",
                        "pagamento" =>
                            $pagamentoObrigatorio
                                ? "Pendente"
                                : "Pago",
                        "presenca" => 0,
                        "certificado" => 0,
                        "valor" => $valor,
                        "valor_pago" => 0,
                        "forma_pagamento" => null,
                        "codigo_pagamento" => null
                    ]);

                    if (!$ok) {
                        throw new RuntimeException(
                            "Não foi possível "
                            . "salvar a inscrição."
                        );
                    }

                    $idInscricao =
                        $inscricao->ultimoId();
                }
            }

            if ($idInscricao <= 0) {
                throw new RuntimeException(
                    "Não foi possível identificar "
                    . "a inscrição."
                );
            }

            /*
             * Atualiza snapshot principal caso uma
             * inscrição existente seja reaproveitada.
             */
            $stmt = $this->db->prepare("
                UPDATE inscricoes
                SET
                    idUsuario = :idUsuario,
                    nome = :nome,
                    cpf = :cpf,
                    email = :email,
                    telefone = :telefone,
                    sexo = :sexo,
                    data_nascimento =
                        :data_nascimento,
                    cidade = :cidade,
                    estado = :estado,
                    camiseta = :camiseta,
                    valor = :valor,
                    pagamento =
                        CASE
                            WHEN pagamento = 'Pago'
                                THEN pagamento
                            ELSE :pagamento
                        END
                WHERE idInscricao =
                    :idInscricao
            ");

            $stmt->execute([
                ":idUsuario" => $idUsuario,
                ":nome" => $nome,
                ":cpf" => $cpf,
                ":email" => $email,
                ":telefone" => $telefone,
                ":sexo" => $genero,
                ":data_nascimento" =>
                    $dataNascimento,
                ":cidade" => $cidade,
                ":estado" => $estado,
                ":camiseta" => $camiseta,
                ":valor" => $valor,
                ":pagamento" =>
                    $pagamentoObrigatorio
                        ? "Pendente"
                        : "Pago",
                ":idInscricao" =>
                    $idInscricao
            ]);

            $this->salvarDadosAdicionais(
                $idInscricao,
                [
                    "nacionalidade" =>
                        $nacionalidade,
                    "genero" => $genero,
                    "pais" => $pais,
                    "cep" => $cep,
                    "logradouro" =>
                        $logradouro,
                    "numero" => $numero,
                    "complemento" =>
                        $complemento,
                    "bairro" => $bairro,
                    "cidade" => $cidade,
                    "estado" => $estado,
                    "idComunidade" =>
                        $visitante === 1
                            ? null
                            : $idComunidade,
                    "visitante" =>
                        $visitante,
                    "restricao_medicacao" =>
                        $restricaoMedicacao,
                    "medicacao_detalhes" =>
                        $medicacaoDetalhes,
                    "deficiencia" =>
                        $deficiencia,
                    "deficiencia_detalhes" =>
                        $deficienciaDetalhes,
                    "precisa_acessibilidade" =>
                        $precisaAcessibilidade,
                    "acessibilidade_detalhes" =>
                        $acessibilidadeDetalhes,
                    "restricao_alimentar" =>
                        $restricaoAlimentar,
                    "alimentar_detalhes" =>
                        $alimentarDetalhes
                ]
            );

            $this->salvarTermosAceites(
                $idInscricao,
                $termos,
                $aceites
            );

            /*
             * É idempotente: se já houver pagamento,
             * Pagamento::criarParaInscricao o reutiliza.
             */
            $pagamentoModel =
                new Pagamento(
                    $this->db
                );

            $idPagamento =
                $pagamentoModel
                    ->criarParaInscricao(
                        $idInscricao
                    );

            $stmt = $this->db->prepare("
                UPDATE inscricao_publica_fluxos
                SET
                    idUsuario = :idUsuario,
                    idInscricao = :idInscricao,
                    idPagamento = :idPagamento,
                    atualizado_em = NOW()
                WHERE idFluxo = :idFluxo
            ");

            $stmt->bindValue(
                ":idUsuario",
                $idUsuario,
                PDO::PARAM_INT
            );

            $stmt->bindValue(
                ":idInscricao",
                $idInscricao,
                PDO::PARAM_INT
            );

            if ($idPagamento > 0) {
                $stmt->bindValue(
                    ":idPagamento",
                    $idPagamento,
                    PDO::PARAM_INT
                );
            } else {
                $stmt->bindValue(
                    ":idPagamento",
                    null,
                    PDO::PARAM_NULL
                );
            }

            $stmt->bindValue(
                ":idFluxo",
                (int) $fluxo["idFluxo"],
                PDO::PARAM_INT
            );

            $stmt->execute();

            if ($iniciouTransacao) {
                $this->db->commit();
            }
        } catch (Throwable $erro) {
            if (
                $iniciouTransacao
                && $this->db->inTransaction()
            ) {
                $this->db->rollBack();
            }

            throw $erro;
        }

        if ($idPagamento <= 0) {
            $this->concluirFluxo(
                (int) $fluxo["idFluxo"]
            );

            return [
                "concluido" => true,
                "gratuito" => true,
                "idInscricao" =>
                    $idInscricao,
                "mensagem" =>
                    "Inscrição realizada "
                    . "e confirmada com sucesso."
            ];
        }

        $forma = trim(
            (string) (
                $dados["forma_pagamento"]
                ?? ""
            )
        );

        if (
            !in_array(
                $forma,
                [
                    "PIX",
                    "Boleto",
                    "Cartao"
                ],
                true
            )
        ) {
            throw new InvalidArgumentException(
                "Selecione a forma de pagamento."
            );
        }

        $pagamentoModel =
            new Pagamento(
                $this->db
            );

        $asaas =
            new AsaasPagamentoService(
                $this->db,
                $pagamentoModel
            );

        if (!$asaas->estaConfigurado()) {
            throw new RuntimeException(
                "O pagamento online ainda "
                . "não está configurado."
            );
        }

        if ($forma === "PIX") {
            $retorno =
                $asaas
                    ->gerarOuRecuperarCobranca(
                        $idPagamento,
                        "PIX"
                    );

            return [
                "concluido" => true,
                "gratuito" => false,
                "idInscricao" =>
                    $idInscricao,
                "idPagamento" =>
                    $idPagamento,
                "forma" => "PIX",
                "statusPagamento" =>
                    (string) (
                        $retorno["status"]
                        ?? "Pendente"
                    ),
                "pixQrCode" =>
                    (string) (
                        $retorno["pixQrCode"]
                        ?? ""
                    ),
                "pixCopiaCola" =>
                    (string) (
                        $retorno["pixCopiaCola"]
                        ?? ""
                    ),
                "mensagem" =>
                    "Inscrição criada. "
                    . "Realize o pagamento pelo PIX."
            ];
        }

        if ($forma === "Boleto") {
            $retorno =
                $asaas
                    ->gerarOuRecuperarCobranca(
                        $idPagamento,
                        "Boleto"
                    );

            return [
                "concluido" => true,
                "gratuito" => false,
                "idInscricao" =>
                    $idInscricao,
                "idPagamento" =>
                    $idPagamento,
                "forma" => "Boleto",
                "statusPagamento" =>
                    (string) (
                        $retorno["status"]
                        ?? "Pendente"
                    ),
                "linhaDigitavel" =>
                    (string) (
                        $retorno[
                            "boletoLinhaDigitavel"
                        ]
                        ?? ""
                    ),
                "boletoUrl" =>
                    (string) (
                        $retorno["bankSlipUrl"]
                        ?? $retorno["invoiceUrl"]
                        ?? ""
                    ),
                "mensagem" =>
                    "Inscrição criada. "
                    . "O boleto foi gerado."
            ];
        }

        $cartao = [
            "holderName" => trim(
                (string) (
                    $dados["cardHolderName"]
                    ?? ""
                )
            ),
            "number" => preg_replace(
                "/\D+/",
                "",
                (string) (
                    $dados["cardNumber"]
                    ?? ""
                )
            ) ?? "",
            "expiryMonth" =>
                preg_replace(
                    "/\D+/",
                    "",
                    (string) (
                        $dados[
                            "cardExpiryMonth"
                        ]
                        ?? ""
                    )
                ) ?? "",
            "expiryYear" =>
                preg_replace(
                    "/\D+/",
                    "",
                    (string) (
                        $dados[
                            "cardExpiryYear"
                        ]
                        ?? ""
                    )
                ) ?? "",
            "ccv" => preg_replace(
                "/\D+/",
                "",
                (string) (
                    $dados["cardCcv"]
                    ?? ""
                )
            ) ?? ""
        ];

        $titular = [
            "name" => $nome,
            "email" => $email,
            "cpfCnpj" => $cpf,
            "postalCode" => $cep,
            "addressNumber" => $numero,
            "addressComplement" =>
                $complemento !== ""
                    ? $complemento
                    : null,
            "phone" => $telefone,
            "mobilePhone" => $telefone
        ];

        try {
            $retorno =
                $asaas->pagarCobrancaCartao(
                    $idPagamento,
                    $cartao,
                    $titular
                );
        } finally {
            unset(
                $cartao,
                $titular
            );
        }

        $status =
            (string) (
                $retorno["status"]
                ?? "Pendente"
            );

        if ($status === "Pago") {
            $this->concluirFluxo(
                (int) $fluxo["idFluxo"]
            );
        }

        return [
            "concluido" => true,
            "gratuito" => false,
            "idInscricao" =>
                $idInscricao,
            "idPagamento" =>
                $idPagamento,
            "forma" => "Cartao",
            "statusPagamento" => $status,
            "mensagem" =>
                $status === "Pago"
                    ? "Pagamento aprovado e "
                        . "inscrição confirmada."
                    : "Pagamento enviado "
                        . "para processamento."
        ];
    }

    private function buscarFluxo(
        string $token,
        bool $exigirVerificado
    ): array {
        $token = trim($token);

        if (
            strlen($token) !== 64
            || !ctype_xdigit($token)
        ) {
            throw new RuntimeException(
                "Sessão de inscrição inválida."
            );
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM inscricao_publica_fluxos
            WHERE token = :token
              AND expira_em > NOW()
            LIMIT 1
        ");

        $stmt->execute([
            ":token" => $token
        ]);

        $fluxo = $stmt->fetch();

        if (!$fluxo) {
            throw new RuntimeException(
                "A sessão de inscrição expirou. "
                . "Inicie novamente."
            );
        }

        if (
            $exigirVerificado
            && empty(
                $fluxo["email_verificado_em"]
            )
        ) {
            throw new RuntimeException(
                "Valide o e-mail antes de continuar."
            );
        }

        return $fluxo;
    }

    private function buscarUsuarioPorCpf(
        string $cpf
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE REPLACE(
                REPLACE(
                    REPLACE(
                        REPLACE(
                            TRIM(cpf),
                            '.',
                            ''
                        ),
                        '-',
                        ''
                    ),
                    ' ',
                    ''
                ),
                '/',
                ''
            ) = :cpf
            LIMIT 1
        ");

        $stmt->execute([
            ":cpf" => $cpf
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function buscarUsuarioPorEmail(
        string $email
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM usuarios
            WHERE LOWER(TRIM(email)) = :email
            LIMIT 1
        ");

        $stmt->execute([
            ":email" => $email
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function buscarUltimoHistorico(
        string $cpf,
        string $email,
        ?int $idUsuario
    ): ?array {
        $whereUsuario = "";

        $params = [
            ":cpf" => $cpf,
            ":email" => $email
        ];

        if ($idUsuario !== null) {
            $whereUsuario =
                " OR i.idUsuario = :idUsuario ";

            $params[":idUsuario"] =
                $idUsuario;
        }

        $stmt = $this->db->prepare("
            SELECT
                i.nome,
                i.telefone,
                i.sexo,
                i.data_nascimento,
                d.nacionalidade,
                d.genero AS genero_extra,
                d.pais,
                d.cep,
                d.logradouro,
                d.numero,
                d.complemento,
                d.bairro,
                d.cidade AS cidade_extra,
                d.estado AS estado_extra,
                d.idComunidade AS idComunidade_extra,
                d.restricao_medicacao,
                d.medicacao_detalhes,
                d.deficiencia,
                d.deficiencia_detalhes,
                d.precisa_acessibilidade,
                d.acessibilidade_detalhes,
                d.restricao_alimentar,
                d.alimentar_detalhes
            FROM inscricoes i
            LEFT JOIN
                inscricao_dados_adicionais d
                ON d.idInscricao =
                    i.idInscricao
            WHERE (
                (
                    REPLACE(
                        REPLACE(
                            REPLACE(
                                REPLACE(
                                    TRIM(i.cpf),
                                    '.',
                                    ''
                                ),
                                '-',
                                ''
                            ),
                            ' ',
                            ''
                        ),
                        '/',
                        ''
                    ) = :cpf
                    AND LOWER(TRIM(i.email))
                        = :email
                )
                {$whereUsuario}
            )
            ORDER BY i.idInscricao DESC
            LIMIT 1
        ");

        $stmt->execute($params);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function obterOuCriarUsuario(
        array $dados
    ): int {
        $email = Usuario::normalizarEmail(
            (string) $dados["email"]
        );

        $cpf = Usuario::normalizarCpf(
            (string) $dados["cpf"]
        );

        $usuarioCpf =
            $this->buscarUsuarioPorCpf(
                $cpf
            );

        $usuarioEmail =
            $this->buscarUsuarioPorEmail(
                $email
            );

        if (
            $usuarioCpf
            && Usuario::normalizarEmail(
                (string) $usuarioCpf["email"]
            ) !== $email
        ) {
            throw new RuntimeException(
                "O CPF está vinculado "
                . "a outro e-mail."
            );
        }

        if (
            $usuarioEmail
            && trim(
                (string) (
                    $usuarioEmail["cpf"]
                    ?? ""
                )
            ) !== ""
            && Usuario::normalizarCpf(
                (string) $usuarioEmail["cpf"]
            ) !== $cpf
        ) {
            throw new RuntimeException(
                "O e-mail está vinculado "
                . "a outro CPF."
            );
        }

        $usuario =
            $usuarioCpf
            ?: $usuarioEmail
            ?: null;

        if ($usuario) {
            $id = (int) $usuario["id"];

            $this->atualizarUsuarioExistente(
                $id,
                $dados
            );

            return $id;
        }

        /*
         * A inscrição pública não solicita senha.
         * É criado um segredo aleatório e a pessoa
         * poderá usar "Recuperar senha" no futuro.
         */
        $senhaAleatoria = bin2hex(
            random_bytes(32)
        );

        $stmt = $this->db->prepare("
            INSERT INTO usuarios (
                nome,
                email,
                telefone,
                cpf,
                senha,
                tipo,
                ativo,
                idComunidade,
                logradouro,
                numero,
                bairro,
                cidade,
                estado,
                nacionalidade,
                data_nascimento,
                genero,
                pais,
                cep,
                complemento,
                email_verificado_em,
                ultimo_login
            ) VALUES (
                :nome,
                :email,
                :telefone,
                :cpf,
                :senha,
                3,
                1,
                :idComunidade,
                :logradouro,
                :numero,
                :bairro,
                :cidade,
                :estado,
                :nacionalidade,
                :data_nascimento,
                :genero,
                :pais,
                :cep,
                :complemento,
                NOW(),
                NULL
            )
        ");

        $stmt->execute([
            ":nome" => $dados["nome"],
            ":email" => $email,
            ":telefone" =>
                $dados["telefone"],
            ":cpf" => $cpf,
            ":senha" => password_hash(
                $senhaAleatoria,
                PASSWORD_DEFAULT
            ),
            ":idComunidade" =>
                isset($dados["idComunidade"])
                && (int) $dados["idComunidade"] > 0
                    ? (int) $dados["idComunidade"]
                    : null,
            ":logradouro" =>
                $dados["logradouro"],
            ":numero" => $dados["numero"],
            ":bairro" => $dados["bairro"],
            ":cidade" => $dados["cidade"],
            ":estado" => $dados["estado"],
            ":nacionalidade" =>
                $dados["nacionalidade"],
            ":data_nascimento" =>
                $dados["data_nascimento"],
            ":genero" => $dados["genero"],
            ":pais" => $dados["pais"],
            ":cep" => $dados["cep"],
            ":complemento" =>
                trim(
                    (string) (
                        $dados["complemento"]
                        ?? ""
                    )
                ) !== ""
                    ? $dados["complemento"]
                    : null
        ]);

        return (int)
            $this->db->lastInsertId();
    }

    private function atualizarUsuarioExistente(
        int $idUsuario,
        array $dados
    ): void {
        if ($idUsuario <= 0) {
            throw new InvalidArgumentException(
                "Usuário inválido."
            );
        }

        $email = Usuario::normalizarEmail(
            (string) $dados["email"]
        );

        $cpf = Usuario::normalizarCpf(
            (string) $dados["cpf"]
        );

        $stmt = $this->db->prepare("
            UPDATE usuarios
            SET
                nome = :nome,
                email = :email,
                telefone = :telefone,
                cpf = :cpf,
                idComunidade =
                    COALESCE(
                        :idComunidade,
                        idComunidade
                    ),
                logradouro = :logradouro,
                numero = :numero,
                bairro = :bairro,
                cidade = :cidade,
                estado = :estado,
                nacionalidade = :nacionalidade,
                data_nascimento =
                    :data_nascimento,
                genero = :genero,
                pais = :pais,
                cep = :cep,
                complemento = :complemento,
                email_verificado_em =
                    COALESCE(
                        email_verificado_em,
                        NOW()
                    )
            WHERE id = :id
        ");

        $stmt->execute([
            ":nome" => $dados["nome"],
            ":email" => $email,
            ":telefone" =>
                $dados["telefone"],
            ":cpf" => $cpf,
            ":idComunidade" =>
                isset($dados["idComunidade"])
                && (int) $dados["idComunidade"] > 0
                    ? (int) $dados["idComunidade"]
                    : null,
            ":logradouro" =>
                $dados["logradouro"],
            ":numero" => $dados["numero"],
            ":bairro" => $dados["bairro"],
            ":cidade" => $dados["cidade"],
            ":estado" => $dados["estado"],
            ":nacionalidade" =>
                $dados["nacionalidade"],
            ":data_nascimento" =>
                $dados["data_nascimento"],
            ":genero" => $dados["genero"],
            ":pais" => $dados["pais"],
            ":cep" => $dados["cep"],
            ":complemento" =>
                trim(
                    (string) (
                        $dados["complemento"]
                        ?? ""
                    )
                ) !== ""
                    ? $dados["complemento"]
                    : null,
            ":id" => $idUsuario
        ]);
    }

    private function salvarDadosAdicionais(
        int $idInscricao,
        array $dados
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO
                inscricao_dados_adicionais (
                    idInscricao,
                    nacionalidade,
                    genero,
                    pais,
                    cep,
                    logradouro,
                    numero,
                    complemento,
                    bairro,
                    cidade,
                    estado,
                    idComunidade,
                    visitante,
                    restricao_medicacao,
                    medicacao_detalhes,
                    deficiencia,
                    deficiencia_detalhes,
                    precisa_acessibilidade,
                    acessibilidade_detalhes,
                    restricao_alimentar,
                    alimentar_detalhes
                )
            VALUES (
                :idInscricao,
                :nacionalidade,
                :genero,
                :pais,
                :cep,
                :logradouro,
                :numero,
                :complemento,
                :bairro,
                :cidade,
                :estado,
                :idComunidade,
                :visitante,
                :restricao_medicacao,
                :medicacao_detalhes,
                :deficiencia,
                :deficiencia_detalhes,
                :precisa_acessibilidade,
                :acessibilidade_detalhes,
                :restricao_alimentar,
                :alimentar_detalhes
            )
            ON DUPLICATE KEY UPDATE
                nacionalidade =
                    VALUES(nacionalidade),
                genero = VALUES(genero),
                pais = VALUES(pais),
                cep = VALUES(cep),
                logradouro =
                    VALUES(logradouro),
                numero = VALUES(numero),
                complemento =
                    VALUES(complemento),
                bairro = VALUES(bairro),
                cidade = VALUES(cidade),
                estado = VALUES(estado),
                idComunidade =
                    VALUES(idComunidade),
                visitante =
                    VALUES(visitante),
                restricao_medicacao =
                    VALUES(restricao_medicacao),
                medicacao_detalhes =
                    VALUES(medicacao_detalhes),
                deficiencia =
                    VALUES(deficiencia),
                deficiencia_detalhes =
                    VALUES(deficiencia_detalhes),
                precisa_acessibilidade =
                    VALUES(
                        precisa_acessibilidade
                    ),
                acessibilidade_detalhes =
                    VALUES(
                        acessibilidade_detalhes
                    ),
                restricao_alimentar =
                    VALUES(restricao_alimentar),
                alimentar_detalhes =
                    VALUES(alimentar_detalhes)
        ");

        $params = [
            ":idInscricao" =>
                $idInscricao,
            ":nacionalidade" =>
                $dados["nacionalidade"] ?? null,
            ":genero" =>
                $dados["genero"] ?? null,
            ":pais" =>
                $dados["pais"] ?? null,
            ":cep" =>
                $dados["cep"] ?? null,
            ":logradouro" =>
                $dados["logradouro"] ?? null,
            ":numero" =>
                $dados["numero"] ?? null,
            ":complemento" =>
                trim(
                    (string) (
                        $dados["complemento"]
                        ?? ""
                    )
                ) !== ""
                    ? $dados["complemento"]
                    : null,
            ":bairro" =>
                $dados["bairro"] ?? null,
            ":cidade" =>
                $dados["cidade"] ?? null,
            ":estado" =>
                $dados["estado"] ?? null,
            ":idComunidade" =>
                (int) (
                    $dados["idComunidade"]
                    ?? 0
                ) > 0
                    ? (int) $dados["idComunidade"]
                    : null,
            ":visitante" =>
                !empty($dados["visitante"])
                    ? 1
                    : 0,
            ":restricao_medicacao" =>
                (int) (
                    $dados[
                        "restricao_medicacao"
                    ]
                    ?? 0
                ),
            ":medicacao_detalhes" =>
                trim(
                    (string) (
                        $dados[
                            "medicacao_detalhes"
                        ]
                        ?? ""
                    )
                ) !== ""
                    ? $dados[
                        "medicacao_detalhes"
                    ]
                    : null,
            ":deficiencia" =>
                $dados["deficiencia"] ?? null,
            ":deficiencia_detalhes" =>
                trim(
                    (string) (
                        $dados[
                            "deficiencia_detalhes"
                        ]
                        ?? ""
                    )
                ) !== ""
                    ? $dados[
                        "deficiencia_detalhes"
                    ]
                    : null,
            ":precisa_acessibilidade" =>
                (int) (
                    $dados[
                        "precisa_acessibilidade"
                    ]
                    ?? 0
                ),
            ":acessibilidade_detalhes" =>
                trim(
                    (string) (
                        $dados[
                            "acessibilidade_detalhes"
                        ]
                        ?? ""
                    )
                ) !== ""
                    ? $dados[
                        "acessibilidade_detalhes"
                    ]
                    : null,
            ":restricao_alimentar" =>
                (int) (
                    $dados[
                        "restricao_alimentar"
                    ]
                    ?? 0
                ),
            ":alimentar_detalhes" =>
                trim(
                    (string) (
                        $dados[
                            "alimentar_detalhes"
                        ]
                        ?? ""
                    )
                ) !== ""
                    ? $dados[
                        "alimentar_detalhes"
                    ]
                    : null
        ];

        $stmt->execute($params);
    }

    private function salvarTermosAceites(
        int $idInscricao,
        array $termos,
        array $aceites
    ): void {
        $aceites =
            array_map(
                "strval",
                $aceites
            );

        $stmt = $this->db->prepare("
            DELETE FROM
                inscricao_termos_aceites
            WHERE idInscricao =
                :idInscricao
        ");

        $stmt->execute([
            ":idInscricao" => $idInscricao
        ]);

        foreach ($termos as $termo) {
            if (
                !in_array(
                    (string) $termo["idTermo"],
                    $aceites,
                    true
                )
            ) {
                continue;
            }

            $stmt = $this->db->prepare("
                INSERT INTO
                    inscricao_termos_aceites (
                        idInscricao,
                        idTermo,
                        titulo_snapshot,
                        descricao_snapshot,
                        url_snapshot,
                        obrigatorio,
                        aceito_em,
                        ip,
                        user_agent
                    )
                VALUES (
                    :idInscricao,
                    :idTermo,
                    :titulo,
                    :descricao,
                    :url,
                    :obrigatorio,
                    NOW(),
                    :ip,
                    :user_agent
                )
            ");

            $stmt->execute([
                ":idInscricao" => $idInscricao,
                ":idTermo" =>
                    (int) $termo["idTermo"],
                ":titulo" =>
                    (string) $termo["titulo"],
                ":descricao" =>
                    $termo["descricao"] ?? null,
                ":url" =>
                    $termo["url"] ?? null,
                ":obrigatorio" =>
                    (int) $termo["obrigatorio"],
                ":ip" => substr(
                    (string) (
                        $_SERVER["REMOTE_ADDR"]
                        ?? ""
                    ),
                    0,
                    45
                ),
                ":user_agent" => substr(
                    (string) (
                        $_SERVER["HTTP_USER_AGENT"]
                        ?? ""
                    ),
                    0,
                    500
                )
            ]);
        }
    }

    private function buscarInscricaoAtiva(
        int $idEvento,
        int $idUsuario
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT idInscricao
            FROM inscricoes
            WHERE idEvento = :idEvento
              AND idUsuario = :idUsuario
              AND status <> 'Cancelada'
            ORDER BY idInscricao DESC
            LIMIT 1
        ");

        $stmt->execute([
            ":idEvento" => $idEvento,
            ":idUsuario" => $idUsuario
        ]);

        $registro = $stmt->fetch();

        return $registro ?: null;
    }

    private function validarEventoAberto(
        array $evento
    ): void {
        if (
            (int) ($evento["ativo"] ?? 0)
                !== 1
            || (int) (
                $evento["inscricao_aberta"]
                ?? 0
            ) !== 1
        ) {
            throw new RuntimeException(
                "As inscrições deste evento "
                . "não estão abertas."
            );
        }

        $agora = new DateTimeImmutable(
            "now",
            new DateTimeZone(
                "America/Sao_Paulo"
            )
        );

        $inicio = trim(
            (string) (
                $evento["inscricao_inicio"]
                ?? ""
            )
        );

        if ($inicio !== "") {
            $dataInicio =
                new DateTimeImmutable(
                    $inicio,
                    new DateTimeZone(
                        "America/Sao_Paulo"
                    )
                );

            if ($agora < $dataInicio) {
                throw new RuntimeException(
                    "As inscrições ainda "
                    . "não começaram."
                );
            }
        }

        $fim = trim(
            (string) (
                $evento["inscricao_fim"]
                ?? ""
            )
        );

        if ($fim !== "") {
            $dataFim =
                new DateTimeImmutable(
                    $fim,
                    new DateTimeZone(
                        "America/Sao_Paulo"
                    )
                );

            if ($agora > $dataFim) {
                throw new RuntimeException(
                    "As inscrições foram encerradas."
                );
            }
        }

        $this->validarVagas($evento);
    }

    private function validarVagas(
        array $evento
    ): void {
        $vagas = (int) (
            $evento["vagas"]
            ?? 0
        );

        if ($vagas <= 0) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM inscricoes
            WHERE idEvento = :idEvento
              AND status <> 'Cancelada'
        ");

        $stmt->execute([
            ":idEvento" =>
                (int) $evento["idEvento"]
        ]);

        if (
            (int) $stmt->fetchColumn()
            >= $vagas
        ) {
            throw new RuntimeException(
                "As vagas deste evento "
                . "estão esgotadas."
            );
        }
    }

    private function validarIdade(
        string $dataNascimento,
        array $evento
    ): void {
        $nascimento =
            new DateTimeImmutable(
                $dataNascimento
            );

        $referencia =
            new DateTimeImmutable(
                (string) (
                    $evento["data_inicio"]
                    ?? "now"
                )
            );

        $idade =
            $nascimento
                ->diff($referencia)
                ->y;

        $minima = (int) (
            $evento["idade_minima"]
            ?? 0
        );

        $maxima = (int) (
            $evento["idade_maxima"]
            ?? 0
        );

        if (
            $minima > 0
            && $idade < $minima
        ) {
            throw new RuntimeException(
                "A idade mínima para este evento "
                . "é {$minima} anos."
            );
        }

        if (
            $maxima > 0
            && $idade > $maxima
        ) {
            throw new RuntimeException(
                "A idade máxima para este evento "
                . "é {$maxima} anos."
            );
        }
    }

    private function comunidadeExiste(
        int $idComunidade
    ): bool {
        if ($idComunidade <= 0) {
            return false;
        }

        $stmt = $this->db->prepare("
            SELECT 1
            FROM minha_comunidade
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            ":id" => $idComunidade
        ]);

        return $stmt->fetchColumn()
            !== false;
    }

    private function dataValida(
        string $data
    ): bool {
        $objeto =
            DateTimeImmutable::createFromFormat(
                "!Y-m-d",
                $data
            );

        return $objeto
            instanceof DateTimeImmutable
            && $objeto->format("Y-m-d")
                === $data;
    }

    private function concluirFluxo(
        int $idFluxo
    ): void {
        $stmt = $this->db->prepare("
            UPDATE inscricao_publica_fluxos
            SET
                concluido_em = NOW(),
                atualizado_em = NOW()
            WHERE idFluxo = :idFluxo
        ");

        $stmt->execute([
            ":idFluxo" => $idFluxo
        ]);
    }

    private function enviarEmailCodigo(
        string $email,
        string $codigo,
        string $evento
    ): void {
        $eventoEscapado =
            htmlspecialchars(
                $evento,
                ENT_QUOTES | ENT_SUBSTITUTE,
                "UTF-8"
            );

        $codigoEscapado =
            htmlspecialchars(
                $codigo,
                ENT_QUOTES | ENT_SUBSTITUTE,
                "UTF-8"
            );

        $html = <<<HTML
<!doctype html>
<html lang="pt-BR">
<body style="
    margin:0;
    background:#f4f5f7;
    font-family:Arial,Helvetica,sans-serif;
    color:#202124;
">
    <table
        width="100%"
        cellspacing="0"
        cellpadding="0"
        style="padding:24px 12px;"
    >
        <tr>
            <td align="center">
                <table
                    width="100%"
                    cellspacing="0"
                    cellpadding="0"
                    style="
                        max-width:560px;
                        background:#fff;
                        border-radius:12px;
                        border:1px solid #e3e6ea;
                    "
                >
                    <tr>
                        <td style="padding:28px;">
                            <h1 style="
                                margin:0 0 16px;
                                font-size:22px;
                            ">
                                Validação de e-mail
                            </h1>

                            <p>
                                Use o código abaixo para
                                continuar sua inscrição em
                                <strong>{$eventoEscapado}</strong>.
                            </p>

                            <div style="
                                text-align:center;
                                font-size:34px;
                                font-weight:bold;
                                letter-spacing:8px;
                                padding:22px;
                                margin:24px 0;
                                background:#f6f7f9;
                                border-radius:10px;
                            ">
                                {$codigoEscapado}
                            </div>

                            <p style="
                                color:#6b7280;
                                font-size:13px;
                                margin-bottom:0;
                            ">
                                O código é válido por 10 minutos.
                                Se você não iniciou esta inscrição,
                                ignore este e-mail.
                            </p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;

        $mail = new Mail();

        if (
            !$mail->send(
                $email,
                "Participante",
                "Código de validação - "
                    . $evento,
                $html
            )
        ) {
            throw new RuntimeException(
                "Não foi possível enviar "
                . "o código de validação "
                . "por e-mail."
            );
        }
    }
}
