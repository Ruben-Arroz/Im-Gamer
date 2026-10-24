-- I'm Gamer
-- Script de criação das tabelas (MariaDB 10.11 / MySQL)

SET FOREIGN_KEY_CHECKS = 0;

CREATE TABLE `Jogos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `rawg_id` int(11) DEFAULT NULL,
  `steam_app_id` int(11) DEFAULT NULL,
  `xbox_id` varchar(20) DEFAULT NULL,
  `gog_id` varchar(20) DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `slug` varchar(255) DEFAULT NULL,
  `imagem_capa` varchar(255) DEFAULT NULL,
  `data_lancamento` date DEFAULT NULL,
  `descricao` text DEFAULT NULL,
  `nota_metacritic` tinyint(4) DEFAULT NULL,
  `modo_um_jogador` tinyint(1) NOT NULL DEFAULT 0,
  `modo_multijogador` tinyint(1) NOT NULL DEFAULT 0,
  `modo_cooperativo` tinyint(1) NOT NULL DEFAULT 0,
  `data_cache` datetime NOT NULL DEFAULT current_timestamp(),
  `editora` varchar(255) DEFAULT NULL,
  `produtora` varchar(255) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `rawg_id` (`rawg_id`),
  UNIQUE KEY `steam_app_id` (`steam_app_id`),
  UNIQUE KEY `xbox_id` (`xbox_id`),
  UNIQUE KEY `gog_id` (`gog_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Generos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Plataformas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome` varchar(50) NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome` (`nome`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Utilizadores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `nome_utilizador` varchar(50) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password_hash` varchar(255) NOT NULL,
  `steam_id` varchar(50) DEFAULT NULL,
  `foto_perfil` varchar(255) DEFAULT NULL,
  `foto_capa` varchar(255) DEFAULT NULL,
  `bio` text DEFAULT NULL,
  `jogo_favorito_id` int(11) DEFAULT NULL,
  `plataforma_preferida_id` int(11) DEFAULT NULL,
  `moeda` char(3) NOT NULL DEFAULT 'EUR',
  `esconder_valores` tinyint(1) NOT NULL DEFAULT 0,
  `is_admin` tinyint(1) NOT NULL DEFAULT 0,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `nome_utilizador` (`nome_utilizador`),
  UNIQUE KEY `email` (`email`),
  UNIQUE KEY `steam_id` (`steam_id`),
  KEY `fk_utilizadores_jogo` (`jogo_favorito_id`),
  KEY `fk_utilizadores_plataforma` (`plataforma_preferida_id`),
  CONSTRAINT `fk_utilizadores_jogo` FOREIGN KEY (`jogo_favorito_id`) REFERENCES `Jogos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_utilizadores_plataforma` FOREIGN KEY (`plataforma_preferida_id`) REFERENCES `Plataformas` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Avaliacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `pontuacao` tinyint(4) NOT NULL,
  `comentario` text DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `utilizador_id` (`utilizador_id`,`jogo_id`),
  KEY `fk_avaliacoes_jogo` (`jogo_id`),
  CONSTRAINT `fk_avaliacoes_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_avaliacoes_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `chk_avaliacoes_pontuacao` CHECK (`pontuacao` between 1 and 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Biblioteca_Jogos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `jogo_id` int(11) DEFAULT NULL,
  `id_externo` varchar(50) DEFAULT NULL,
  `nome` varchar(255) NOT NULL,
  `imagem` varchar(255) DEFAULT NULL,
  `minutos_jogados` int(11) DEFAULT NULL,
  `minutos_2_semanas` int(11) NOT NULL DEFAULT 0,
  `ultima_vez_jogado` datetime DEFAULT NULL,
  `conquistas` int(11) NOT NULL DEFAULT 0,
  `conquistas_total` int(11) NOT NULL DEFAULT 0,
  `valor` decimal(10,2) DEFAULT NULL,
  `ultima_sincronizacao` datetime DEFAULT NULL,
  `pontuacao` int(11) DEFAULT NULL,
  `pontuacao_total` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `conta_id` (`conta_id`,`id_externo`),
  KEY `fk_biblioteca_jogo` (`jogo_id`),
  CONSTRAINT `fk_biblioteca_conta` FOREIGN KEY (`conta_id`) REFERENCES `Contas_Ligadas` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_biblioteca_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Contas_Ligadas` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `servico` varchar(30) NOT NULL,
  `identificador` varchar(255) NOT NULL,
  `id_externo` varchar(100) DEFAULT NULL,
  `nome_publico` varchar(100) DEFAULT NULL,
  `avatar` varchar(255) DEFAULT NULL,
  `nivel` int(11) DEFAULT NULL,
  `pontuacao` int(11) DEFAULT NULL,
  `num_jogos` int(11) NOT NULL DEFAULT 0,
  `minutos_jogados` int(11) NOT NULL DEFAULT 0,
  `num_conquistas` int(11) NOT NULL DEFAULT 0,
  `valor_jogos` decimal(10,2) NOT NULL DEFAULT 0.00,
  `valor_inventario` decimal(10,2) NOT NULL DEFAULT 0.00,
  `ultima_sincronizacao` datetime DEFAULT NULL,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `utilizador_id` (`utilizador_id`,`servico`),
  CONSTRAINT `fk_contas_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Convites` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `remetente_id` int(11) NOT NULL,
  `destinatario_id` int(11) NOT NULL,
  `jogo_id` int(11) DEFAULT NULL,
  `mensagem` varchar(255) DEFAULT NULL,
  `estado` enum('pendente','aceite','recusado') NOT NULL DEFAULT 'pendente',
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_convites_remetente` (`remetente_id`),
  KEY `fk_convites_destinatario` (`destinatario_id`),
  KEY `fk_convites_jogo` (`jogo_id`),
  CONSTRAINT `fk_convites_destinatario` FOREIGN KEY (`destinatario_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_convites_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_convites_remetente` FOREIGN KEY (`remetente_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Inventario_Itens` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `conta_id` int(11) NOT NULL,
  `jogo` varchar(100) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `imagem` varchar(1000) DEFAULT NULL,
  `quantidade` int(11) NOT NULL DEFAULT 1,
  `preco` decimal(10,2) DEFAULT NULL,
  `ultima_sincronizacao` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `fk_inventario_conta` (`conta_id`),
  CONSTRAINT `fk_inventario_conta` FOREIGN KEY (`conta_id`) REFERENCES `Contas_Ligadas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Jogos_Generos` (
  `jogo_id` int(11) NOT NULL,
  `genero_id` int(11) NOT NULL,
  PRIMARY KEY (`jogo_id`,`genero_id`),
  KEY `fk_jogos_generos_genero` (`genero_id`),
  CONSTRAINT `fk_jogos_generos_genero` FOREIGN KEY (`genero_id`) REFERENCES `Generos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jogos_generos_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Jogos_Plataformas` (
  `jogo_id` int(11) NOT NULL,
  `plataforma_id` int(11) NOT NULL,
  PRIMARY KEY (`jogo_id`,`plataforma_id`),
  KEY `fk_jogos_plataformas_plataforma` (`plataforma_id`),
  CONSTRAINT `fk_jogos_plataformas_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_jogos_plataformas_plataforma` FOREIGN KEY (`plataforma_id`) REFERENCES `Plataformas` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `LFG_Participantes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `post_id` int(11) NOT NULL,
  `utilizador_id` int(11) NOT NULL,
  `data_entrada` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `post_id` (`post_id`,`utilizador_id`),
  KEY `fk_lfgparticipantes_utilizador` (`utilizador_id`),
  CONSTRAINT `fk_lfgparticipantes_post` FOREIGN KEY (`post_id`) REFERENCES `LFG_Posts` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lfgparticipantes_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `LFG_Posts` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `plataforma_id` int(11) DEFAULT NULL,
  `descricao` text NOT NULL,
  `vagas` tinyint(4) NOT NULL DEFAULT 1,
  `data_hora_evento` datetime NOT NULL,
  `estado` enum('aberto','fechado') NOT NULL DEFAULT 'aberto',
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_lfgposts_utilizador` (`utilizador_id`),
  KEY `fk_lfgposts_jogo` (`jogo_id`),
  KEY `fk_lfgposts_plataforma` (`plataforma_id`),
  CONSTRAINT `fk_lfgposts_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_lfgposts_plataforma` FOREIGN KEY (`plataforma_id`) REFERENCES `Plataformas` (`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_lfgposts_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Lista_Desejos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `jogo_id` int(11) NOT NULL,
  `data_adicionado` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `utilizador_id` (`utilizador_id`,`jogo_id`),
  KEY `fk_listadesejos_jogo` (`jogo_id`),
  CONSTRAINT `fk_listadesejos_jogo` FOREIGN KEY (`jogo_id`) REFERENCES `Jogos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_listadesejos_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Notificacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `utilizador_id` int(11) NOT NULL,
  `tipo` varchar(50) NOT NULL,
  `conteudo` varchar(255) NOT NULL,
  `lida` tinyint(1) NOT NULL DEFAULT 0,
  `data_criacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `fk_notificacoes_utilizador` (`utilizador_id`),
  CONSTRAINT `fk_notificacoes_utilizador` FOREIGN KEY (`utilizador_id`) REFERENCES `Utilizadores` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE `Taxas_Cambio` (
  `moeda` char(3) NOT NULL,
  `taxa` decimal(12,6) NOT NULL,
  `data_atualizacao` datetime NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`moeda`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

SET FOREIGN_KEY_CHECKS = 1;
