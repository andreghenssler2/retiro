CREATE TABLE IF NOT EXISTS atividades_usuarios (
    idAtividade BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idUsuario INT NOT NULL,
    nomeUsuario VARCHAR(150) NOT NULL,
    tipoUsuario TINYINT NOT NULL DEFAULT 0,
    acesso VARCHAR(180) NOT NULL,
    rota VARCHAR(500) NOT NULL,
    descricao VARCHAR(500) NOT NULL,
    metodo VARCHAR(10) NOT NULL DEFAULT 'GET',
    ip VARCHAR(45) NOT NULL,
    userAgent VARCHAR(500) DEFAULT NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (idAtividade),
    KEY idx_atividades_usuario (idUsuario),
    KEY idx_atividades_data (criadoEm),
    KEY idx_atividades_nome (nomeUsuario),
    KEY idx_atividades_acesso (acesso),
    KEY idx_atividades_ip (ip)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;
