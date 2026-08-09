-- Módulo de certificados
-- Compatível com MySQL/MariaDB e phpMyAdmin.
-- Não utiliza information_schema, PROCEDURE, DELIMITER ou CALL.

CREATE TABLE IF NOT EXISTS certificado_modelos (
    idModelo INT UNSIGNED NOT NULL AUTO_INCREMENT,
    idEvento INT NOT NULL,
    nome VARCHAR(150) NOT NULL,
    titulo VARCHAR(200) NOT NULL DEFAULT 'CERTIFICADO',
    texto TEXT NOT NULL,
    cargaHoraria DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    localEmissao VARCHAR(150) DEFAULT NULL,
    corTitulo CHAR(7) NOT NULL DEFAULT '#0d6efd',
    corTexto CHAR(7) NOT NULL DEFAULT '#1f2937',
    imagemFundo VARCHAR(255) DEFAULT NULL,
    logo VARCHAR(255) DEFAULT NULL,
    assinatura1Imagem VARCHAR(255) DEFAULT NULL,
    assinatura1Nome VARCHAR(150) DEFAULT NULL,
    assinatura1Cargo VARCHAR(150) DEFAULT NULL,
    assinatura2Imagem VARCHAR(255) DEFAULT NULL,
    assinatura2Nome VARCHAR(150) DEFAULT NULL,
    assinatura2Cargo VARCHAR(150) DEFAULT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criadoPor INT DEFAULT NULL,
    criadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizadoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (idModelo),
    UNIQUE KEY uk_certificado_modelo_evento (idEvento),
    KEY idx_certificado_modelo_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS certificados_emitidos (
    idCertificado BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    idModelo INT UNSIGNED NOT NULL,
    idInscricao INT NOT NULL,
    idEvento INT NOT NULL,
    idUsuario INT NOT NULL,
    codigo VARCHAR(40) NOT NULL,
    tokenDownload CHAR(64) NOT NULL,
    arquivo VARCHAR(500) NOT NULL,
    hashArquivo CHAR(64) NOT NULL,
    nomeParticipante VARCHAR(150) NOT NULL,
    emailDestino VARCHAR(150) NOT NULL,
    eventoTitulo VARCHAR(200) NOT NULL,
    cargaHoraria DECIMAL(6,2) NOT NULL DEFAULT 0.00,
    dataEvento VARCHAR(100) DEFAULT NULL,
    status ENUM('Emitido','Enviado','Revogado') NOT NULL DEFAULT 'Emitido',
    emitidoEm DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    enviadoEm DATETIME DEFAULT NULL,
    emitidoPor INT DEFAULT NULL,
    revogadoEm DATETIME DEFAULT NULL,
    revogadoPor INT DEFAULT NULL,
    motivoRevogacao VARCHAR(500) DEFAULT NULL,
    PRIMARY KEY (idCertificado),
    UNIQUE KEY uk_certificado_codigo (codigo),
    UNIQUE KEY uk_certificado_token (tokenDownload),
    KEY idx_certificado_inscricao (idInscricao),
    KEY idx_certificado_evento (idEvento),
    KEY idx_certificado_usuario (idUsuario),
    KEY idx_certificado_status (status),
    KEY idx_certificado_emitido_em (emitidoEm)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

SELECT 'Módulo de certificados instalado com sucesso.' AS resultado;
