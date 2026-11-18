<?php
declare(strict_types=1);
return [
    'descricao' => 'CPF do responsável nas autorizações presenciais completas',
    'up' => static function (PDO $db): void {
        $stmt = $db->query("SHOW COLUMNS FROM inscricao_dados_adicionais LIKE 'responsavel_cpf'");
        if (!$stmt->fetch()) {
            $db->exec("ALTER TABLE inscricao_dados_adicionais ADD COLUMN responsavel_cpf VARCHAR(14) NULL AFTER responsavel_nome");
        }
    },
];
