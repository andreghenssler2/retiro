<?php

declare(strict_types=1);

if (
    PHP_SAPI !== "cli"
    && PHP_SAPI !== "phpdbg"
) {
    http_response_code(404);
    exit;
}

$raiz =
    dirname(
        __DIR__,
        2
    );

/*
 * A sessão deve iniciar antes da primeira saída.
 */
if (
    session_status()
    !== PHP_SESSION_ACTIVE
) {
    session_start();
}

require_once
    $raiz
    . "/mod/auth/Permissao.php";

require_once
    $raiz
    . "/mod/auth/Usuario.php";

require_once
    $raiz
    . "/mod/services/PagamentoRegraService.php";

require_once
    $raiz
    . "/mod/services/AsaasWebhookRegraService.php";

final class TesteCritico
{
    private int $ok = 0;
    private int $falhas = 0;

    public function executar(
        string $nome,
        callable $teste
    ): void {
        try {
            $teste($this);

            $this->ok++;

            echo
                "[OK] "
                . $nome
                . PHP_EOL;
        } catch (Throwable $erro) {
            $this->falhas++;

            echo
                "[FALHA] "
                . $nome
                . PHP_EOL
                . "        "
                . $erro->getMessage()
                . PHP_EOL;
        }
    }

    public function verdadeiro(
        mixed $valor,
        string $mensagem = ""
    ): void {
        if ($valor !== true) {
            throw new RuntimeException(
                $mensagem !== ""
                    ? $mensagem
                    : "Esperado true."
            );
        }
    }

    public function falso(
        mixed $valor,
        string $mensagem = ""
    ): void {
        if ($valor !== false) {
            throw new RuntimeException(
                $mensagem !== ""
                    ? $mensagem
                    : "Esperado false."
            );
        }
    }

    public function igual(
        mixed $esperado,
        mixed $atual,
        string $mensagem = ""
    ): void {
        if ($esperado !== $atual) {
            throw new RuntimeException(
                (
                    $mensagem !== ""
                        ? $mensagem
                            . " "
                        : ""
                )
                . "Esperado "
                . var_export(
                    $esperado,
                    true
                )
                . ", obtido "
                . var_export(
                    $atual,
                    true
                )
                . "."
            );
        }
    }

    public function contem(
        string $agulha,
        string $texto,
        string $mensagem = ""
    ): void {
        if (
            !str_contains(
                $texto,
                $agulha
            )
        ) {
            throw new RuntimeException(
                $mensagem !== ""
                    ? $mensagem
                    : (
                        "Trecho obrigatório não encontrado: "
                        . $agulha
                    )
            );
        }
    }

    public function totalOk(): int
    {
        return $this->ok;
    }

    public function totalFalhas(): int
    {
        return $this->falhas;
    }
}

$teste =
    new TesteCritico();

echo
    "======================================"
    . PHP_EOL;

echo
    "TESTES CRÍTICOS - SISTEMA DE EVENTOS"
    . PHP_EOL;

echo
    "======================================"
    . PHP_EOL
    . PHP_EOL;

/*
|--------------------------------------------------------------------------
| Permissões
|--------------------------------------------------------------------------
*/

$definirUsuario =
    static function (
        ?int $tipo
    ): void {
        if ($tipo === null) {
            unset(
                $_SESSION["user"]
            );

            return;
        }

        $_SESSION["user"] = [
            "id" => 9000 + $tipo,
            "nome" => "Teste CI",
            "tipo" => $tipo
        ];
    };

$teste->executar(
    "Permissões: administrador possui acesso total",
    static function (
        TesteCritico $t
    ) use ($definirUsuario): void {
        $definirUsuario(
            Permissao::ADMINISTRADOR
        );

        $t->verdadeiro(
            Permissao::pode(
                "usuarios.excluir"
            )
        );

        $t->verdadeiro(
            Permissao::pode(
                "qualquer.permissao.futura"
            )
        );
    }
);

$teste->executar(
    "Permissões: moderador acessa operação de eventos",
    static function (
        TesteCritico $t
    ) use ($definirUsuario): void {
        $definirUsuario(
            Permissao::MODERADOR
        );

        $t->verdadeiro(
            Permissao::pode(
                "eventos.editar"
            )
        );

        $t->verdadeiro(
            Permissao::pode(
                "financeiro.visualizar"
            )
        );

        $t->falso(
            Permissao::pode(
                "usuarios.excluir"
            )
        );
    }
);

$teste->executar(
    "Permissões: participante fica restrito aos próprios dados",
    static function (
        TesteCritico $t
    ) use ($definirUsuario): void {
        $definirUsuario(
            Permissao::PARTICIPANTE
        );

        $t->verdadeiro(
            Permissao::pode(
                "pagamentos.proprios"
            )
        );

        $t->verdadeiro(
            Permissao::pode(
                "inscricoes.proprias"
            )
        );

        $t->falso(
            Permissao::pode(
                "financeiro.visualizar"
            )
        );
    }
);

$teste->executar(
    "Permissões: usuário anônimo não recebe acesso",
    static function (
        TesteCritico $t
    ) use ($definirUsuario): void {
        $definirUsuario(null);

        $t->falso(
            Permissao::autenticado()
        );

        $t->falso(
            Permissao::pode(
                "perfil.proprio"
            )
        );
    }
);

/*
|--------------------------------------------------------------------------
| Inscrição pública / identidade
|--------------------------------------------------------------------------
*/

$teste->executar(
    "Inscrição: e-mail é normalizado antes da identidade",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "participante@example.com",
            Usuario::normalizarEmail(
                " Participante@Example.COM "
            )
        );
    }
);

$teste->executar(
    "Inscrição: CPF formatado é normalizado para 11 dígitos",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "52998224725",
            Usuario::normalizarCpf(
                "529.982.247-25"
            )
        );
    }
);

$teste->executar(
    "Inscrição: CPF válido passa pelos dígitos verificadores",
    static function (
        TesteCritico $t
    ): void {
        $t->verdadeiro(
            Usuario::cpfValido(
                "529.982.247-25"
            )
        );
    }
);

$teste->executar(
    "Inscrição: CPF repetido é rejeitado",
    static function (
        TesteCritico $t
    ): void {
        $t->falso(
            Usuario::cpfValido(
                "111.111.111-11"
            )
        );
    }
);

/*
|--------------------------------------------------------------------------
| Pagamentos
|--------------------------------------------------------------------------
*/

$teste->executar(
    "Pagamento: status permitidos permanecem controlados",
    static function (
        TesteCritico $t
    ): void {
        foreach (
            [
                "Pendente",
                "Vencido",
                "Pago",
                "Cancelado",
                "Estornado"
            ]
            as $status
        ) {
            $t->verdadeiro(
                PagamentoRegraService
                    ::statusValido(
                        $status
                    )
            );
        }

        $t->falso(
            PagamentoRegraService
                ::statusValido(
                    "Aprovado"
                )
        );
    }
);

$teste->executar(
    "Pagamento: formas permitidas permanecem controladas",
    static function (
        TesteCritico $t
    ): void {
        foreach (
            [
                "NaoDefinido",
                "PIX",
                "Cartao",
                "Boleto",
                "Dinheiro",
                "Transferencia"
            ]
            as $forma
        ) {
            $t->verdadeiro(
                PagamentoRegraService
                    ::formaValida(
                        $forma
                    )
            );
        }

        $t->falso(
            PagamentoRegraService
                ::formaValida(
                    "Criptomoeda"
                )
        );
    }
);

$teste->executar(
    "Pagamento: valor brasileiro é normalizado sem perder centavos",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            1234.56,
            PagamentoRegraService
                ::normalizarValor(
                    "R$ 1.234,56"
                )
        );

        $t->igual(
            99.9,
            PagamentoRegraService
                ::normalizarValor(
                    "99.90"
                )
        );

        $t->igual(
            0.0,
            PagamentoRegraService
                ::normalizarValor(
                    "não informado"
                )
        );
    }
);

$teste->executar(
    "Pagamento: pago confirma inscrição e registra valor pago",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "Confirmada",
            PagamentoRegraService
                ::statusInscricao(
                    "Pago"
                )
        );

        $t->igual(
            150.75,
            PagamentoRegraService
                ::valorPago(
                    "Pago",
                    150.75
                )
        );
    }
);

$teste->executar(
    "Pagamento: cancelamento e estorno cancelam presença",
    static function (
        TesteCritico $t
    ): void {
        $t->verdadeiro(
            PagamentoRegraService
                ::deveCancelarPresenca(
                    "Cancelado"
                )
        );

        $t->verdadeiro(
            PagamentoRegraService
                ::deveCancelarPresenca(
                    "Estornado"
                )
        );

        $t->falso(
            PagamentoRegraService
                ::deveCancelarPresenca(
                    "Pago"
                )
        );
    }
);

$teste->executar(
    "Pagamento: boleto vencido preserva histórico após PAYMENT_DELETED",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "Vencido",
            PagamentoRegraService
                ::statusPersistidoAsaas(
                    "Cancelado",
                    "Vencido",
                    "Boleto"
                )
        );

        $t->igual(
            "Cancelado",
            PagamentoRegraService
                ::statusPersistidoAsaas(
                    "Cancelado",
                    "Vencido",
                    "PIX"
                )
        );
    }
);

/*
|--------------------------------------------------------------------------
| Webhook Asaas
|--------------------------------------------------------------------------
*/

$teste->executar(
    "Webhook: token vazio ou diferente é rejeitado",
    static function (
        TesteCritico $t
    ): void {
        $t->falso(
            AsaasWebhookRegraService
                ::tokenValido(
                    "",
                    "abc"
                )
        );

        $t->falso(
            AsaasWebhookRegraService
                ::tokenValido(
                    "abc",
                    ""
                )
        );

        $t->falso(
            AsaasWebhookRegraService
                ::tokenValido(
                    "abc",
                    "ABC"
                )
        );

        $t->verdadeiro(
            AsaasWebhookRegraService
                ::tokenValido(
                    "segredo",
                    "segredo"
                )
        );
    }
);

$teste->executar(
    "Webhook: recebimento e confirmação viram Pago",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "Pago",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_RECEIVED",
                    "RECEIVED"
                )
        );

        $t->igual(
            "Pago",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_CONFIRMED",
                    "CONFIRMED"
                )
        );
    }
);

$teste->executar(
    "Webhook: vencimento, estorno e exclusão são mapeados",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            "Vencido",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_OVERDUE",
                    "OVERDUE"
                )
        );

        $t->igual(
            "Estornado",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_REFUNDED",
                    "REFUNDED"
                )
        );

        $t->igual(
            "Cancelado",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_DELETED",
                    "DELETED"
                )
        );
    }
);

$teste->executar(
    "Webhook: status desconhecido não inventa estado local",
    static function (
        TesteCritico $t
    ): void {
        $t->igual(
            null,
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_UNKNOWN",
                    "UNKNOWN"
                )
        );

        $t->igual(
            "Pendente",
            AsaasWebhookRegraService
                ::statusLocal(
                    "PAYMENT_CREATED",
                    "PENDING"
                )
        );
    }
);

/*
|--------------------------------------------------------------------------
| Contratos de integração
|--------------------------------------------------------------------------
*/

$arquivoPagamento =
    file_get_contents(
        $raiz
        . "/mod/auth/Pagamento.php"
    );

$arquivoInscricao =
    file_get_contents(
        $raiz
        . "/mod/services/InscricaoPublicaService.php"
    );

$arquivoWebhook =
    file_get_contents(
        $raiz
        . "/api/asaas/webhook.php"
    );

$teste->executar(
    "Contrato: Pagamento usa as regras testadas",
    static function (
        TesteCritico $t
    ) use ($arquivoPagamento): void {
        if (
            !is_string(
                $arquivoPagamento
            )
        ) {
            throw new RuntimeException(
                "Pagamento.php não pôde ser lido."
            );
        }

        $t->contem(
            "PagamentoRegraService::statusPersistidoAsaas",
            $arquivoPagamento
        );

        $t->contem(
            "PagamentoRegraService::normalizarValor",
            $arquivoPagamento
        );

        $t->contem(
            "PagamentoRegraService::statusInscricao",
            $arquivoPagamento
        );
    }
);

$teste->executar(
    "Contrato: inscrição pública mantém validação de e-mail e CPF",
    static function (
        TesteCritico $t
    ) use ($arquivoInscricao): void {
        if (
            !is_string(
                $arquivoInscricao
            )
        ) {
            throw new RuntimeException(
                "InscricaoPublicaService.php não pôde ser lido."
            );
        }

        $t->contem(
            "Usuario::normalizarEmail",
            $arquivoInscricao
        );

        $t->contem(
            "Usuario::cpfValido",
            $arquivoInscricao
        );

        $t->contem(
            "buscarUsuarioPorCpf",
            $arquivoInscricao
        );

        $t->contem(
            "buscarUsuarioPorEmail",
            $arquivoInscricao
        );
    }
);

$teste->executar(
    "Contrato: webhook usa token e status das regras testadas",
    static function (
        TesteCritico $t
    ) use ($arquivoWebhook): void {
        if (
            !is_string(
                $arquivoWebhook
            )
        ) {
            throw new RuntimeException(
                "webhook.php não pôde ser lido."
            );
        }

        $t->contem(
            "AsaasWebhookRegraService::tokenValido",
            $arquivoWebhook
        );

        $t->contem(
            "AsaasWebhookRegraService::statusLocal",
            $arquivoWebhook
        );

        $t->contem(
            "INSERT IGNORE INTO asaas_webhook_eventos",
            $arquivoWebhook,
            "A proteção contra webhook duplicado foi removida."
        );
    }
);

$definirUsuario(null);

echo
    PHP_EOL
    . "--------------------------------------"
    . PHP_EOL;

echo
    "OK: "
    . $teste->totalOk()
    . PHP_EOL;

echo
    "FALHAS: "
    . $teste->totalFalhas()
    . PHP_EOL;

echo
    "--------------------------------------"
    . PHP_EOL;

exit(
    $teste->totalFalhas()
        === 0
            ? 0
            : 1
);
