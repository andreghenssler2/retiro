CREATE TABLE IF NOT EXISTS notificacoes (
    idNotificacao BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    tipo VARCHAR(30) NOT NULL,
    idReferencia BIGINT UNSIGNED NOT NULL,
    idUsuarioRelacionado INT DEFAULT NULL,
    titulo VARCHAR(150) NOT NULL,
    mensagem VARCHAR(500) NOT NULL,
    url VARCHAR(500) NOT NULL,
    tituloUsuario VARCHAR(150) DEFAULT NULL,
    mensagemUsuario VARCHAR(500) DEFAULT NULL,
    urlUsuario VARCHAR(500) DEFAULT NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (idNotificacao),
    UNIQUE KEY uq_notificacao_referencia (
        tipo,
        idReferencia
    ),
    KEY idx_notificacao_data (criadoEm),
    KEY idx_notificacao_tipo (tipo),
    KEY idx_notificacao_usuario (
        idUsuarioRelacionado,
        criadoEm
    )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacoes_lidas (
    idNotificacao BIGINT UNSIGNED NOT NULL,
    idUsuario INT NOT NULL,
    lidaEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (
        idNotificacao,
        idUsuario
    ),
    KEY idx_notificacao_lida_usuario (idUsuario),
    KEY idx_notificacao_lida_data (lidaEm)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS notificacao_fontes (
    fonte VARCHAR(40) NOT NULL,
    sincronizadoEm DATETIME NOT NULL,

    PRIMARY KEY (fonte)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO notificacao_fontes (
    fonte,
    sincronizadoEm
) VALUES
    ('usuarios', NOW()),
    ('inscricoes', NOW()),
    ('pagamentos', NOW());
