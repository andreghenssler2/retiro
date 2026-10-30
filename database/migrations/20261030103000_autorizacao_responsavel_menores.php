<?php

declare(strict_types=1);

return [
    'descricao' => 'Dados e autorização obrigatória do responsável legal para menores',
    'up' => static function (PDO $db): void {
        $campos = [
            'responsavel_nome' => 'VARCHAR(160) NULL',
            'responsavel_telefone_1' => 'VARCHAR(20) NULL',
            'responsavel_telefone_2' => 'VARCHAR(20) NULL',
            'responsavel_autorizacao_texto' => 'TEXT NULL',
            'responsavel_autorizacao_aceita_em' => 'DATETIME NULL',
        ];
        foreach ($campos as $campo => $tipo) {
            // Lista fixa de campos, sem entrada externa na montagem do SQL.
            $consulta = $db->prepare(
                'SELECT COUNT(*) FROM information_schema.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() '
                . 'AND TABLE_NAME = :tabela AND COLUMN_NAME = :campo'
            );
            $consulta->execute([
                ':tabela' => 'inscricao_dados_adicionais',
                ':campo' => $campo,
            ]);
            if ((int) $consulta->fetchColumn() === 0) {
                $db->exec('ALTER TABLE `inscricao_dados_adicionais` ADD COLUMN `'
                    . $campo . '` ' . $tipo);
            }
        }
    },
];
