CREATE TABLE IF NOT EXISTS `solicitacoes_cancelamento_inscricao` (
    `idSolicitacao` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `idInscricao` INT NOT NULL,
    `idUsuario` INT NOT NULL,
    `motivo` TEXT NOT NULL,
    `status` VARCHAR(20) NOT NULL DEFAULT 'Pendente',
    `idAdministrador` INT NULL,
    `observacao_admin` TEXT NULL,
    `criado_em` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `analisado_em` DATETIME NULL,
    PRIMARY KEY (`idSolicitacao`),
    KEY `idx_cancelamento_inscricao` (`idInscricao`),
    KEY `idx_cancelamento_usuario` (`idUsuario`),
    KEY `idx_cancelamento_status` (`status`),
    KEY `idx_cancelamento_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
