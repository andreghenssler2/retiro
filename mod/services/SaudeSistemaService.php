<?php

declare(strict_types=1);

final class SaudeSistemaService
{
    private PDO $db;
    private string $raiz;

    public function __construct(PDO $db, ?string $raiz = null)
    {
        $this->db = $db;
        $this->raiz = $raiz
            ?? (defined("ROOT_PATH") ? (string) ROOT_PATH : dirname(__DIR__, 2));
    }

    public static function registrarExecucao(
        PDO $db,
        string $chave,
        string $status,
        mixed $detalhes = null
    ): void {
        try {
            $chave = trim($chave);

            if ($chave === "") {
                return;
            }

            $status = strtolower(trim($status));

            if ($status === "") {
                $status = "desconhecido";
            }

            if (is_array($detalhes)) {
                $detalhes = json_encode(
                    $detalhes,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                );
            } elseif ($detalhes !== null && !is_scalar($detalhes)) {
                $detalhes = get_debug_type($detalhes);
            }

            $stmt = $db->prepare("
                INSERT INTO sistema_execucoes (
                    chave,
                    status,
                    executadoEm,
                    detalhes,
                    atualizadoEm
                ) VALUES (
                    :chave,
                    :status,
                    NOW(),
                    :detalhes,
                    NOW()
                )
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    executadoEm = VALUES(executadoEm),
                    detalhes = VALUES(detalhes),
                    atualizadoEm = NOW()
            ");

            $stmt->execute([
                ":chave" => mb_substr($chave, 0, 100),
                ":status" => mb_substr($status, 0, 30),
                ":detalhes" => $detalhes !== null
                    ? mb_substr((string) $detalhes, 0, 16000)
                    : null
            ]);
        } catch (Throwable $erro) {
            error_log(
                "Falha ao registrar saúde do sistema: "
                . $erro->getMessage()
            );
        }
    }

    public function resumo(): array
    {
        return [
            "aplicacao" => $this->aplicacao(),
            "banco" => $this->banco(),
            "migrations" => $this->migrations(),
            "asaas" => $this->asaas(),
            "smtp" => $this->smtp(),
            "webhook" => $this->webhook(),
            "crons" => $this->crons(),
            "disco" => $this->disco()
        ];
    }

    private function aplicacao(): array
    {
        $version = [];
        $arquivo = $this->raiz . "/mod/version.php";

        if (is_file($arquivo)) {
            try {
                $dados = require $arquivo;

                if (is_array($dados)) {
                    $version = $dados;
                }
            } catch (Throwable) {
                $version = [];
            }
        }

        return [
            "status" => "ok",
            "php" => PHP_VERSION,
            "sapi" => PHP_SAPI,
            "versao" => (string) (
                $version["version"]
                ?? (defined("VERSION") ? VERSION : "—")
            ),
            "build" => (int) ($version["build"] ?? 0),
            "commit" => (string) ($version["commit"] ?? "—"),
            "branch" => (string) ($version["branch"] ?? "—")
        ];
    }

    private function banco(): array
    {
        try {
            $stmt = $this->db->query("
                SELECT
                    DATABASE() AS banco,
                    VERSION() AS versao
            ");

            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->db->query("SELECT 1");

            return [
                "status" => "ok",
                "banco" => (string) ($linha["banco"] ?? "—"),
                "versao" => (string) ($linha["versao"] ?? "—")
            ];
        } catch (Throwable $erro) {
            return [
                "status" => "erro",
                "mensagem" => $erro->getMessage()
            ];
        }
    }

    private function migrations(): array
    {
        $diretorio = $this->raiz . "/database/migrations";

        $arquivos = glob(
            $diretorio . "/[0-9]*_*.php"
        );

        $arquivos = is_array($arquivos)
            ? $arquivos
            : [];

        sort($arquivos, SORT_STRING);

        try {
            if (!$this->tabelaExiste("schema_migrations")) {
                return [
                    "status" => $arquivos === []
                        ? "ok"
                        : "atencao",
                    "total" => count($arquivos),
                    "aplicadas" => 0,
                    "pendentes" => count($arquivos),
                    "alteradas" => 0,
                    "itensPendentes" => array_map(
                        static fn (string $arquivo): string =>
                            pathinfo($arquivo, PATHINFO_FILENAME),
                        $arquivos
                    ),
                    "itensAlterados" => []
                ];
            }

            $stmt = $this->db->query("
                SELECT
                    idMigration,
                    checksum
                FROM schema_migrations
            ");

            $aplicadas = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
                $id = (string) ($linha["idMigration"] ?? "");

                if ($id !== "") {
                    $aplicadas[$id] =
                        (string) ($linha["checksum"] ?? "");
                }
            }
        } catch (Throwable $erro) {
            return [
                "status" => "erro",
                "mensagem" => $erro->getMessage(),
                "total" => count($arquivos),
                "aplicadas" => 0,
                "pendentes" => 0,
                "alteradas" => 0,
                "itensPendentes" => [],
                "itensAlterados" => []
            ];
        }

        $pendentes = [];
        $alteradas = [];
        $aplicadasLocais = 0;

        foreach ($arquivos as $arquivo) {
            $id = pathinfo(
                $arquivo,
                PATHINFO_FILENAME
            );

            if (!array_key_exists($id, $aplicadas)) {
                $pendentes[] = $id;
                continue;
            }

            $aplicadasLocais++;

            $checksum = hash_file(
                "sha256",
                $arquivo
            );

            if (
                is_string($checksum)
                && $aplicadas[$id] !== ""
                && !hash_equals(
                    $aplicadas[$id],
                    $checksum
                )
            ) {
                $alteradas[] = $id;
            }
        }

        return [
            "status" => $alteradas !== []
                ? "erro"
                : ($pendentes !== [] ? "atencao" : "ok"),
            "total" => count($arquivos),
            "aplicadas" => $aplicadasLocais,
            "pendentes" => count($pendentes),
            "alteradas" => count($alteradas),
            "itensPendentes" => $pendentes,
            "itensAlterados" => $alteradas
        ];
    }

    private function asaas(): array
    {
        try {
            $config = new ConfiguracaoBancaria($this->db);
            $ambiente = $config->ambiente();
            $ativo = $config->ativo();

            $api = $config->credencialConfigurada(
                "api",
                $ambiente
            );

            $webhook = $config->credencialConfigurada(
                "webhook",
                $ambiente
            );

            return [
                "status" => !$ativo
                    ? "atencao"
                    : (($api && $webhook) ? "ok" : "erro"),
                "ativo" => $ativo,
                "ambiente" => $ambiente,
                "apiConfigurada" => $api,
                "webhookConfigurado" => $webhook,
                "apiOrigem" => $config->credencialOrigem(
                    "api",
                    $ambiente
                ),
                "webhookOrigem" => $config->credencialOrigem(
                    "webhook",
                    $ambiente
                )
            ];
        } catch (Throwable $erro) {
            return [
                "status" => "erro",
                "mensagem" => $erro->getMessage()
            ];
        }
    }

    private function smtp(): array
    {
        try {
            if (!$this->tabelaExiste("email_config")) {
                return [
                    "status" => "erro",
                    "configurado" => false,
                    "mensagem" => "Tabela email_config não encontrada."
                ];
            }

            $stmt = $this->db->query("
                SELECT
                    host,
                    username,
                    senha,
                    porta,
                    encryption,
                    remetente,
                    ativo,
                    selecionado
                FROM email_config
                WHERE selecionado = 1
                ORDER BY idEmailConfig DESC
                LIMIT 1
            ");

            $linha = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!is_array($linha)) {
                return [
                    "status" => "atencao",
                    "configurado" => false,
                    "mensagem" => "Nenhum ambiente SMTP selecionado."
                ];
            }

            $host = trim((string) ($linha["host"] ?? ""));
            $username = trim((string) ($linha["username"] ?? ""));
            $senha = (string) ($linha["senha"] ?? "");
            $porta = (int) ($linha["porta"] ?? 0);

            $completo =
                $host !== ""
                && $username !== ""
                && $senha !== ""
                && $porta > 0;

            return [
                "status" => $completo ? "ok" : "erro",
                "configurado" => $completo,
                "ambiente" => (int) ($linha["ativo"] ?? 1) === 0
                    ? "sandbox"
                    : "producao",
                "host" => $host !== "" ? $host : "—",
                "porta" => $porta,
                "encryption" => trim(
                    (string) ($linha["encryption"] ?? "")
                )
            ];
        } catch (Throwable $erro) {
            return [
                "status" => "erro",
                "configurado" => false,
                "mensagem" => $erro->getMessage()
            ];
        }
    }

    private function webhook(): array
    {
        try {
            if (!$this->tabelaExiste("asaas_webhook_eventos")) {
                return [
                    "status" => "erro",
                    "mensagem" => "Tabela asaas_webhook_eventos não encontrada.",
                    "ultimo" => null,
                    "erros24h" => 0
                ];
            }

            $stmt = $this->db->query("
                SELECT
                    evento,
                    recebidoEm,
                    processadoEm,
                    erro
                FROM asaas_webhook_eventos
                ORDER BY id DESC
                LIMIT 1
            ");

            $ultimo = $stmt->fetch(PDO::FETCH_ASSOC);

            $erros24h = (int) (
                $this->db->query("
                    SELECT COUNT(*)
                    FROM asaas_webhook_eventos
                    WHERE erro IS NOT NULL
                      AND TRIM(erro) <> ''
                      AND recebidoEm >= DATE_SUB(
                          NOW(),
                          INTERVAL 24 HOUR
                      )
                ")->fetchColumn()
                ?: 0
            );

            if (!is_array($ultimo)) {
                return [
                    "status" => "atencao",
                    "ultimo" => null,
                    "erros24h" => $erros24h,
                    "mensagem" => "Nenhum webhook recebido ainda."
                ];
            }

            $erro = trim(
                (string) ($ultimo["erro"] ?? "")
            );

            $processado = trim(
                (string) ($ultimo["processadoEm"] ?? "")
            );

            return [
                "status" => $erro !== "" || $erros24h > 0
                    ? "erro"
                    : ($processado !== "" ? "ok" : "atencao"),
                "ultimo" => [
                    "evento" => (string) ($ultimo["evento"] ?? "—"),
                    "recebidoEm" => (string) ($ultimo["recebidoEm"] ?? ""),
                    "processadoEm" => $processado,
                    "teveErro" => $erro !== ""
                ],
                "erros24h" => $erros24h
            ];
        } catch (Throwable $erro) {
            return [
                "status" => "erro",
                "mensagem" => $erro->getMessage(),
                "ultimo" => null,
                "erros24h" => 0
            ];
        }
    }

    private function crons(): array
    {
        $chaves = [
            "cron.boletos" => "Boletos vencidos",
            "cron.credenciamentos" => "Finalizar credenciamentos"
        ];

        $resultado = [];

        try {
            if (!$this->tabelaExiste("sistema_execucoes")) {
                foreach ($chaves as $chave => $rotulo) {
                    $resultado[] = [
                        "chave" => $chave,
                        "rotulo" => $rotulo,
                        "status" => "atencao",
                        "executadoEm" => null
                    ];
                }

                return $resultado;
            }

            $stmt = $this->db->query("
                SELECT
                    chave,
                    status,
                    executadoEm
                FROM sistema_execucoes
                WHERE chave IN (
                    'cron.boletos',
                    'cron.credenciamentos'
                )
            ");

            $linhas = [];

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $linha) {
                $linhas[(string) $linha["chave"]] = $linha;
            }

            foreach ($chaves as $chave => $rotulo) {
                $linha = $linhas[$chave] ?? null;

                if (!is_array($linha)) {
                    $resultado[] = [
                        "chave" => $chave,
                        "rotulo" => $rotulo,
                        "status" => "atencao",
                        "executadoEm" => null
                    ];
                    continue;
                }

                $status = strtolower(
                    trim((string) ($linha["status"] ?? ""))
                );

                $resultado[] = [
                    "chave" => $chave,
                    "rotulo" => $rotulo,
                    "status" => in_array(
                        $status,
                        ["ok", "sucesso"],
                        true
                    )
                        ? "ok"
                        : "erro",
                    "executadoEm" => (string) (
                        $linha["executadoEm"] ?? ""
                    )
                ];
            }

            return $resultado;
        } catch (Throwable) {
            foreach ($chaves as $chave => $rotulo) {
                $resultado[] = [
                    "chave" => $chave,
                    "rotulo" => $rotulo,
                    "status" => "erro",
                    "executadoEm" => null
                ];
            }

            return $resultado;
        }
    }

    private function disco(): array
    {
        $total = @disk_total_space(
            $this->raiz
        );

        $livre = @disk_free_space(
            $this->raiz
        );

        $total = is_numeric($total)
            ? (float) $total
            : 0.0;

        $livre = is_numeric($livre)
            ? (float) $livre
            : 0.0;

        $percentual = $total > 0
            ? ($livre / $total * 100)
            : 0.0;

        return [
            "status" => $total <= 0
                ? "atencao"
                : (
                    $percentual < 10
                        ? "erro"
                        : (
                            $percentual < 20
                                ? "atencao"
                                : "ok"
                        )
                ),
            "total" => $total,
            "livre" => $livre,
            "percentualLivre" => $percentual
        ];
    }

    private function tabelaExiste(string $tabela): bool
    {
        /*
         * MySQL/MariaDB não aceita placeholder PDO
         * em SHOW TABLES LIKE em todos os ambientes.
         *
         * O valor é protegido com PDO::quote().
         */
        $stmt = $this->db->query(
            "SHOW TABLES LIKE "
            . $this->db->quote($tabela)
        );

        return $stmt->fetchColumn() !== false;
    }
}
