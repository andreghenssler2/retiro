<?php

declare(strict_types=1);

return [
    "descricao" => "Criar controle de execução para saúde do sistema",

    "up" => static function (PDO $db): void {
        $db->exec("
            CREATE TABLE IF NOT EXISTS sistema_execucoes (
                chave VARCHAR(100) NOT NULL,
                status VARCHAR(30) NOT NULL,
                executadoEm DATETIME NOT NULL,
                detalhes TEXT DEFAULT NULL,
                atualizadoEm DATETIME NOT NULL,
                PRIMARY KEY (chave),
                KEY idx_sistema_execucoes_status (status),
                KEY idx_sistema_execucoes_executado (executadoEm)
            )
            ENGINE=InnoDB
            DEFAULT CHARSET=utf8mb4
            COLLATE=utf8mb4_unicode_ci
        ");
    }
];
