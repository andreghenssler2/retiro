<?php

declare(strict_types=1);

return [
    "descricao" => "Meios de pagamento permitidos por evento",
    "up" => static function (PDO $db): void {
        $stmt = $db->query("SHOW COLUMNS FROM eventos LIKE 'formas_pagamento'");
        if ($stmt === false || $stmt->fetch() === false) {
            $db->exec("ALTER TABLE eventos ADD COLUMN formas_pagamento VARCHAR(100) NOT NULL DEFAULT 'PIX,Cartao,Boleto' AFTER pagamento_obrigatorio");
        }
    }
];
