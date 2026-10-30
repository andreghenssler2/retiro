<?php

declare(strict_types=1);

return [
    'descricao' => 'Avisos por e-mail para novos cadastros, inscricoes e pagamentos recebidos',
    'up' => static function (PDO $db): void {
        $db->exec(<<<'SQL'
            CREATE TABLE IF NOT EXISTS email_sistema_alertas (
                id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                tipo VARCHAR(30) NOT NULL,
                idReferencia BIGINT UNSIGNED NOT NULL,
                criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                enviadoEm DATETIME NULL DEFAULT NULL,
                tentativas INT UNSIGNED NOT NULL DEFAULT 0,
                ultimaTentativaEm DATETIME NULL DEFAULT NULL,
                ultimoErro VARCHAR(255) NULL DEFAULT NULL,
                PRIMARY KEY (id),
                UNIQUE KEY uq_email_sistema_tipo_ref (tipo, idReferencia),
                KEY idx_email_sistema_pendentes (enviadoEm, ultimaTentativaEm)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        SQL);

        /*
         * Registros existentes antes da instalacao sao apenas marcados como
         * anteriores: NAO geram disparos retroativos de testes antigos.
         */
        $db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas
                (tipo, idReferencia, criadoEm, enviadoEm)
            SELECT 'usuario', id, NOW(), NOW()
            FROM usuarios
        SQL);

        $db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas
                (tipo, idReferencia, criadoEm, enviadoEm)
            SELECT 'inscricao', idInscricao, NOW(), NOW()
            FROM inscricoes
        SQL);

        $db->exec(<<<'SQL'
            INSERT IGNORE INTO email_sistema_alertas
                (tipo, idReferencia, criadoEm, enviadoEm)
            SELECT 'pagamento', idPagamento, NOW(), NOW()
            FROM pagamentos
            WHERE status = 'Pago'
        SQL);
    }
];
