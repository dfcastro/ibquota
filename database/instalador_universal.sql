-- =========================================================================
-- INSTALADOR UNIVERSAL IFQUOTA 3 (Nova Instalação ou Atualização)
-- Serve tanto para criar um campus do zero, quanto para atualizar um antigo.
-- =========================================================================

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";

CREATE DATABASE IF NOT EXISTS `ibquota3` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `ibquota3`;

-- ==========================================================
-- PASSO 1: CRIAÇÃO DA ESTRUTURA DE TABELAS
-- ==========================================================

CREATE TABLE IF NOT EXISTS `adm_users` (
  `cod_adm_users` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) DEFAULT NULL,
  `login` varchar(50) NOT NULL,
  `email` varchar(100) DEFAULT NULL,
  `senha` varchar(64) DEFAULT NULL,
  `permissao` int(11) DEFAULT NULL,
  PRIMARY KEY (`cod_adm_users`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `ano_impressoes` (
  `id_ano` int(11) NOT NULL AUTO_INCREMENT,
  `ano` int(11) NOT NULL,
  PRIMARY KEY (`id_ano`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `config_geral` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `base_local` int(10) UNSIGNED NOT NULL,
  `LDAP_server` varchar(250) DEFAULT NULL,
  `LDAP_port` int(10) UNSIGNED DEFAULT NULL,
  `LDAP_filter` varchar(500) DEFAULT NULL,
  `LDAP_base` varchar(250) DEFAULT NULL,
  `LDAP_user` varchar(250) DEFAULT NULL,
  `LDAP_password` varchar(250) DEFAULT NULL,
  `path_pkpgcounter` varchar(255) NOT NULL DEFAULT '/usr/bin/pkpgcounter',
  `path_python` varchar(255) NOT NULL DEFAULT '/usr/bin/python',
  `Debug` int(10) UNSIGNED NOT NULL,
  `auto_aprovar_colorida` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `grupos` (
  `cod_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `grupo` varchar(150) NOT NULL,
  PRIMARY KEY (`cod_grupo`),
  UNIQUE KEY `idx_grupoUnico` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `grupo_usuario` (
  `cod_grupo_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_grupo` int(10) UNSIGNED NOT NULL,
  `cod_usuario` int(10) UNSIGNED NOT NULL,
  PRIMARY KEY (`cod_grupo_usuario`),
  KEY `grupo_usuario_` (`cod_grupo_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `impressoes` (
  `cod_impressoes` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_status_impressao` int(10) UNSIGNED NOT NULL,
  `impressora` varchar(150) NOT NULL,
  `usuario` varchar(150) NOT NULL,
  `data_impressao` date NOT NULL,
  `hora_impressao` time NOT NULL,
  `job_id` int(10) UNSIGNED NOT NULL,
  `nome_documento` varchar(100) NOT NULL,
  `paginas` int(10) UNSIGNED NOT NULL,
  `estacao` varchar(50) DEFAULT NULL,
  `cod_politica` int(10) UNSIGNED DEFAULT NULL,
  PRIMARY KEY (`cod_impressoes`),
  KEY `impressoes_FKIndex1` (`usuario`),
  KEY `impressoes_FKIndex2` (`impressora`),
  KEY `impressoes_FKIndex3` (`cod_status_impressao`),
  KEY `impressoes_FKIndex5` (`cod_politica`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

-- Criada antes da impressoras_config para a chave estrangeira funcionar
CREATE TABLE IF NOT EXISTS `locais` (
  `cod_local` int(11) NOT NULL AUTO_INCREMENT,
  `nome_local` varchar(100) NOT NULL,
  PRIMARY KEY (`cod_local`),
  UNIQUE KEY `nome_local` (`nome_local`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Chave estrangeira embutida diretamente na criação
CREATE TABLE IF NOT EXISTS `impressoras_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_impressora` varchar(100) NOT NULL,
  `cod_local` int(11) DEFAULT NULL,
  `is_colorida` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_impressora` (`nome_impressora`),
  KEY `cod_local` (`cod_local`),
  CONSTRAINT `impressoras_config_ibfk_1` FOREIGN KEY (`cod_local`) REFERENCES `locais` (`cod_local`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `logs_acesso` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `status` varchar(50) NOT NULL,
  `user_agent` text DEFAULT NULL,
  `data_hora` datetime DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `log_ibquota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `mensagem` varchar(255) NOT NULL,
  `datahora` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `mapeamento_ad` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ou_ad` varchar(100) NOT NULL,
  `cargo_ad` varchar(100) DEFAULT NULL,
  `cod_grupo` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `pedidos_coloridos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `arquivo_nome` varchar(255) NOT NULL,
  `arquivo_caminho` varchar(255) NOT NULL,
  `paginas` int(11) NOT NULL,
  `paginas_especificas` varchar(255) DEFAULT NULL,
  `copias` int(11) NOT NULL,
  `impressora` varchar(50) NOT NULL,
  `status` enum('Pendente','Aprovado','Rejeitado') DEFAULT 'Pendente',
  `data_pedido` datetime DEFAULT current_timestamp(),
  `aprovado_por` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `politicas` (
  `cod_politica` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome` varchar(128) NOT NULL,
  `quota_acumulativa` tinyint(1) NOT NULL DEFAULT 0,
  `quota_infinita` tinyint(1) NOT NULL DEFAULT 0,
  `quota_padrao` float DEFAULT NULL,
  `prioridade` int(10) UNSIGNED NOT NULL DEFAULT 0,
  PRIMARY KEY (`cod_politica`),
  KEY `politica_FKIndex1` (`cod_politica`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `politica_grupo` (
  `cod_politica_grupo` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_politica` int(10) UNSIGNED NOT NULL,
  `grupo` varchar(150) NOT NULL,
  PRIMARY KEY (`cod_politica_grupo`),
  KEY `idx_politica_grupo` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `politica_impressora` (
  `cod_politica_impressora` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_politica` int(10) UNSIGNED NOT NULL,
  `impressora` varchar(150) NOT NULL,
  `prioridade` int(10) UNSIGNED NOT NULL,
  `peso` float NOT NULL,
  PRIMARY KEY (`cod_politica_impressora`),
  KEY `idx_politica_Impressora` (`impressora`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `printlogs` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `job_id` int(11) NOT NULL,
  `printer` varchar(255) NOT NULL,
  `user` varchar(255) NOT NULL,
  `server` varchar(255) NOT NULL,
  `title` varchar(255) NOT NULL,
  `copies` int(11) NOT NULL,
  `pages` int(11) NOT NULL,
  `options` varchar(255) NOT NULL,
  `spoolfile` varchar(255) NOT NULL,
  `datetime` datetime NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `quota_adicional` (
  `cod_quota_adicional` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_politica` int(10) UNSIGNED NOT NULL,
  `grupo` varchar(150) NOT NULL,
  `usuario` varchar(150) NOT NULL,
  `quota_adicional` float NOT NULL,
  `motivo` varchar(255) DEFAULT NULL,
  `datahora` datetime NOT NULL,
  `useradmin` varchar(150) NOT NULL,
  PRIMARY KEY (`cod_quota_adicional`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `quota_usuario` (
  `cod_quota_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `cod_politica` int(10) UNSIGNED NOT NULL,
  `grupo` varchar(150) NOT NULL,
  `usuario` varchar(150) NOT NULL,
  `quota` float DEFAULT NULL,
  PRIMARY KEY (`cod_quota_usuario`),
  KEY `quota_usuario_FKIndex1` (`cod_politica`),
  KEY `quota_usuario_FKIndex2` (`grupo`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `solicitacoes_cota` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `usuario` varchar(50) NOT NULL,
  `paginas` int(11) NOT NULL,
  `motivo` text NOT NULL,
  `status` enum('Pendente','Aprovado','Negado') DEFAULT 'Pendente',
  `data_solicitacao` datetime DEFAULT current_timestamp(),
  `data_resposta` datetime DEFAULT NULL,
  `respondido_por` varchar(50) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE IF NOT EXISTS `status_impressao` (
  `cod_status_impressao` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `nome_status` varchar(100) NOT NULL,
  `erro` tinyint(1) DEFAULT NULL,
  PRIMARY KEY (`cod_status_impressao`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;

CREATE TABLE IF NOT EXISTS `usuarios` (
  `cod_usuario` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `usuario` varchar(150) NOT NULL,
  PRIMARY KEY (`cod_usuario`),
  UNIQUE KEY `idx_usuarioUnico` (`usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;


-- ==========================================================
-- PASSO 2: REMENDOS DE ATUALIZAÇÃO (Para bancos antigos)
-- Se as tabelas já existiam, elas vão receber as colunas novas aqui.
-- Se o banco for novo, o MariaDB simplesmente ignora este passo.
-- ==========================================================

ALTER TABLE `config_geral` ADD COLUMN IF NOT EXISTS `auto_aprovar_colorida` tinyint(1) NOT NULL DEFAULT 0;
ALTER TABLE `politicas` ADD COLUMN IF NOT EXISTS `prioridade` int(10) UNSIGNED NOT NULL DEFAULT 0;


-- ==========================================================
-- PASSO 3: DADOS SEMENTE OBRIGATÓRIOS (Sem duplicação)
-- ==========================================================

INSERT IGNORE INTO `config_geral` (`id`, `base_local`, `path_pkpgcounter`, `path_python`, `Debug`, `auto_aprovar_colorida`) 
VALUES (1, 0, '/usr/bin/pkpgcounter', '/usr/bin/python3', 0, 0);

-- ATENÇÃO: Substitua o '$2y$10$...' pelo hash real gerado pelo sistema para a sua senha
INSERT IGNORE INTO `adm_users` (`cod_adm_users`, `nome`, `login`, `senha`, `permissao`) 
VALUES (1, 'Administrador NTI', 'admin', '$2y$10$SUA_SENHA_CRIPTOGRAFADA_AQUI', 2);

INSERT IGNORE INTO `politicas` (`cod_politica`, `nome`, `quota_acumulativa`, `quota_infinita`, `quota_padrao`, `prioridade`) 
VALUES (1, 'Cota Padrão Servidores', 0, 0, 100, 0);

INSERT IGNORE INTO `status_impressao` (`cod_status_impressao`, `nome_status`, `erro`) VALUES
(1, 'Impresso com sucesso', 0),
(2, 'Cancelado pelo usuário', 1),
(3, 'Cota excedida', 1),
(4, 'Erro no CUPS', 1);

COMMIT;