<?php

declare(strict_types=1);

require_once __DIR__
    . "/../mod/database/db.php";

$db = Database::connect();

/*
 * "selecionado = 1" define qual configuração SMTP
 * está realmente em uso, independentemente de ser
 * Produção (ativo = 1) ou Sandbox (ativo = 0).
 */
$stmt = $db->query("
    SELECT
        idEmailConfig,
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

$rowsMail = $stmt->fetch(PDO::FETCH_ASSOC);

if (!is_array($rowsMail)) {
    return [];
}

return [
    "host" => trim(
        (string) $rowsMail["host"]
    ),
    "port" => (int) $rowsMail["porta"],
    "encryption" => strtolower(
        trim(
            (string) $rowsMail["encryption"]
        )
    ),
    "username" => trim(
        (string) $rowsMail["username"]
    ),
    "password" => (string) $rowsMail["senha"],
    "from_email" => trim(
        (string) $rowsMail["username"]
    ),
    "from_name" => trim(
        (string) ($rowsMail["remetente"] ?? "")
    ) !== ""
        ? trim(
            (string) $rowsMail["remetente"]
        )
        : "Sistema de Eventos",
    "ambiente" => (int) $rowsMail["ativo"] === 0
        ? "sandbox"
        : "producao"
];
