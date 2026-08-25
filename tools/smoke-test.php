<?php

declare(strict_types=1);

if (PHP_SAPI !== "cli" && PHP_SAPI !== "phpdbg") {
    http_response_code(404);
    exit;
}

$raiz = dirname(__DIR__);

$oks = [];
$avisos = [];
$erros = [];

$ok = static function (string $mensagem) use (&$oks): void {
    $oks[] = $mensagem;
};

$aviso = static function (string $mensagem) use (&$avisos): void {
    $avisos[] = $mensagem;
};

$erro = static function (string $mensagem) use (&$erros): void {
    $erros[] = $mensagem;
};

/*
|--------------------------------------------------------------------------
| Não imprime nada antes de settings.php.
|--------------------------------------------------------------------------
|
| Config::init() inicia a sessão. Qualquer echo anterior provocaria:
|
| session_start(): Session cannot be started after headers have already been sent
|
*/

if (PHP_VERSION_ID >= 80300) {
    $ok(
        "PHP "
        . PHP_VERSION
    );
} elseif (PHP_VERSION_ID >= 80200) {
    $aviso(
        "PHP "
        . PHP_VERSION
        . " local. O CI oficial valida PHP 8.3 e 8.4."
    );
} else {
    $erro(
        "PHP "
        . PHP_VERSION
        . " detectado. Use PHP 8.2+; "
        . "produção/CI devem preferir PHP 8.3+."
    );
}

if (is_file($raiz . "/lib/vendor/autoload.php")) {
    $ok("Composer autoload presente");
} else {
    $erro(
        "lib/vendor/autoload.php ausente. "
        . "Execute composer install em /lib."
    );
}

if (is_file($raiz . "/config/conn.php")) {
    $ok("config/conn.php presente");
} else {
    $erro("config/conn.php ausente");
}

try {
    require_once $raiz . "/config/settings.php";

    $ok(
        "config/settings.php carregado"
    );
} catch (Throwable $e) {
    $erro(
        "Falha ao carregar settings: "
        . $e->getMessage()
    );

    echo "======================================" . PHP_EOL;
    echo "SMOKE TEST - SISTEMA DE EVENTOS" . PHP_EOL;
    echo "======================================" . PHP_EOL;
    echo PHP_EOL;

    foreach ($oks as $m) {
        echo "[OK] {$m}" . PHP_EOL;
    }

    foreach ($avisos as $m) {
        echo "[AVISO] {$m}" . PHP_EOL;
    }

    foreach ($erros as $m) {
        echo "[ERRO] {$m}" . PHP_EOL;
    }

    exit(1);
}

/*
|--------------------------------------------------------------------------
| A partir daqui a sessão/configuração já foi carregada.
|--------------------------------------------------------------------------
*/

echo "======================================" . PHP_EOL;
echo "SMOKE TEST - SISTEMA DE EVENTOS" . PHP_EOL;
echo "======================================" . PHP_EOL;
echo PHP_EOL;

try {
    $db->query("SELECT 1");
    $ok("Conexão com banco");
} catch (Throwable $e) {
    $erro(
        "Banco indisponível: "
        . $e->getMessage()
    );
}

$tabelaExiste =
    static function (
        PDO $db,
        string $tabela
    ): bool {
        /*
         * SHOW TABLES LIKE não usa placeholder
         * preparado de forma portável no MySQL/MariaDB.
         */
        $stmt = $db->query(
            "SHOW TABLES LIKE "
            . $db->quote($tabela)
        );

        return
            $stmt->fetchColumn()
            !== false;
    };

foreach (
    [
        "usuarios",
        "eventos",
        "inscricoes",
        "pagamentos",
        "email_config",
        "configuracoes_bancarias",
        "asaas_webhook_eventos",
        "schema_migrations",
        "sistema_execucoes"
    ]
    as $tabela
) {
    try {
        if ($tabelaExiste($db, $tabela)) {
            $ok(
                "Tabela "
                . $tabela
            );
        } else {
            $erro(
                "Tabela ausente: "
                . $tabela
            );
        }
    } catch (Throwable $e) {
        $erro(
            "Falha ao verificar "
            . $tabela
            . ": "
            . $e->getMessage()
        );
    }
}

try {
    $stmt = $db->query(
        "SHOW COLUMNS FROM pagamentos LIKE "
        . $db->quote("recebidoEm")
    );

    if ($stmt->fetch(PDO::FETCH_ASSOC) !== false) {
        $ok(
            "pagamentos.recebidoEm"
        );
    } else {
        $erro(
            "Coluna pagamentos.recebidoEm ausente"
        );
    }
} catch (Throwable $e) {
    $erro(
        "Não foi possível verificar pagamentos.recebidoEm: "
        . $e->getMessage()
    );
}

foreach (
    [
        "Pagamento",
        "ConfiguracaoBancaria",
        "SaudeSistemaService",
        "InscricaoPublicaService",
        "CancelamentoInscricaoNotificacaoService"
    ]
    as $classe
) {
    if (class_exists($classe)) {
        $ok(
            "Classe "
            . $classe
        );
    } else {
        $erro(
            "Classe não carregada: "
            . $classe
        );
    }
}

if (
    class_exists("Pagamento")
    && method_exists(
        "Pagamento",
        "atualizarStatusPeloAsaas"
    )
) {
    $ok(
        "Pagamento::atualizarStatusPeloAsaas"
    );
} else {
    $erro(
        "Método de sincronização Asaas não encontrado"
    );
}

try {
    $resumo =
        (
            new SaudeSistemaService(
                $db
            )
        )->resumo();

    $migrations =
        $resumo["migrations"]
        ?? [];

    if (
        ($migrations["status"] ?? "")
        === "erro"
        && isset($migrations["mensagem"])
    ) {
        $erro(
            "Falha ao consultar migrations: "
            . $migrations["mensagem"]
        );
    } elseif (
        (int) (
            $migrations["alteradas"]
            ?? 0
        ) > 0
    ) {
        $erro(
            "Há migration aplicada "
            . "com checksum alterado."
        );
    } elseif (
        (int) (
            $migrations["pendentes"]
            ?? 0
        ) > 0
    ) {
        $erro(
            "Há "
            . (int) (
                $migrations["pendentes"]
                ?? 0
            )
            . " migration(s) pendente(s)."
        );
    } else {
        $ok(
            "Migrations atualizadas"
        );
    }

    $asaas =
        $resumo["asaas"]
        ?? [];

    if (!empty($asaas["ativo"])) {
        if (
            !empty(
                $asaas["apiConfigurada"]
            )
            && !empty(
                $asaas["webhookConfigurado"]
            )
        ) {
            $ok(
                "Asaas configurado para "
                . (
                    $asaas["ambiente"]
                    ?? "ambiente"
                )
            );
        } else {
            $aviso(
                "Asaas ativo com API key "
                . "ou webhook ausente."
            );
        }
    } else {
        $aviso(
            "Integração Asaas desativada."
        );
    }

    $smtp =
        $resumo["smtp"]
        ?? [];

    if (
        ($smtp["status"] ?? "")
        === "ok"
    ) {
        $ok(
            "SMTP configurado"
        );
    } else {
        $aviso(
            "SMTP não está completamente configurado."
        );
    }
} catch (Throwable $e) {
    $erro(
        "Falha no diagnóstico integrado: "
        . $e->getMessage()
    );
}

$logs =
    $raiz
    . "/logs";

if (!is_dir($logs)) {
    $aviso(
        "/logs não existe"
    );
} elseif (!is_writable($logs)) {
    $aviso(
        "/logs não possui permissão de escrita"
    );
} else {
    $ok(
        "/logs gravável"
    );
}

foreach ($oks as $m) {
    echo "[OK] {$m}" . PHP_EOL;
}

foreach ($avisos as $m) {
    echo "[AVISO] {$m}" . PHP_EOL;
}

foreach ($erros as $m) {
    echo "[ERRO] {$m}" . PHP_EOL;
}

echo PHP_EOL;
echo "--------------------------------------" . PHP_EOL;
echo "OK: " . count($oks) . PHP_EOL;
echo "AVISOS: " . count($avisos) . PHP_EOL;
echo "ERROS: " . count($erros) . PHP_EOL;
echo "--------------------------------------" . PHP_EOL;

exit(
    $erros === []
        ? 0
        : 1
);
