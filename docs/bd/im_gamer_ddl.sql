-- I'm Gamer
-- Script de criação das tabelas (MariaDB 10.11 / MySQL)

CREATE TABLE Utilizadores (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome_utilizador VARCHAR(50) NOT NULL UNIQUE,
    email VARCHAR(255) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    steam_id VARCHAR(50) DEFAULT NULL UNIQUE,
    foto_perfil VARCHAR(255) DEFAULT NULL,
    bio TEXT DEFAULT NULL,
    is_admin TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    rawg_id INT NOT NULL UNIQUE,
    nome VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    imagem_capa VARCHAR(255) DEFAULT NULL,
    data_lancamento DATE DEFAULT NULL,
    descricao TEXT DEFAULT NULL,
    data_cache DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Generos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Plataformas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Jogos_Generos (
    jogo_id INT NOT NULL,
    genero_id INT NOT NULL,
    PRIMARY KEY (jogo_id, genero_id),
    CONSTRAINT fk_jogos_generos_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE,
    CONSTRAINT fk_jogos_generos_genero FOREIGN KEY (genero_id) REFERENCES Generos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Jogos_Plataformas (
    jogo_id INT NOT NULL,
    plataforma_id INT NOT NULL,
    PRIMARY KEY (jogo_id, plataforma_id),
    CONSTRAINT fk_jogos_plataformas_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE,
    CONSTRAINT fk_jogos_plataformas_plataforma FOREIGN KEY (plataforma_id) REFERENCES Plataformas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Biblioteca_Jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    jogo_id INT NOT NULL,
    plataforma_id INT NOT NULL,
    horas_jogadas INT NOT NULL DEFAULT 0,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    UNIQUE (utilizador_id, jogo_id, plataforma_id),
    CONSTRAINT fk_biblioteca_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_biblioteca_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE,
    CONSTRAINT fk_biblioteca_plataforma FOREIGN KEY (plataforma_id) REFERENCES Plataformas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE LFG_Posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    jogo_id INT NOT NULL,
    descricao TEXT NOT NULL,
    data_hora_evento DATETIME NOT NULL,
    estado ENUM('aberto', 'fechado') NOT NULL DEFAULT 'aberto',
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_lfgposts_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_lfgposts_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE LFG_Participantes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    post_id INT NOT NULL,
    utilizador_id INT NOT NULL,
    data_entrada DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (post_id, utilizador_id),
    CONSTRAINT fk_lfgparticipantes_post FOREIGN KEY (post_id) REFERENCES LFG_Posts (id) ON DELETE CASCADE,
    CONSTRAINT fk_lfgparticipantes_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Convites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    remetente_id INT NOT NULL,
    destinatario_id INT NOT NULL,
    jogo_id INT DEFAULT NULL,
    mensagem VARCHAR(255) DEFAULT NULL,
    estado ENUM('pendente', 'aceite', 'recusado') NOT NULL DEFAULT 'pendente',
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_convites_remetente FOREIGN KEY (remetente_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_convites_destinatario FOREIGN KEY (destinatario_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_convites_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Avaliacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    jogo_id INT NOT NULL,
    pontuacao TINYINT NOT NULL,
    comentario TEXT DEFAULT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (utilizador_id, jogo_id),
    CONSTRAINT fk_avaliacoes_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_avaliacoes_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE,
    CONSTRAINT chk_avaliacoes_pontuacao CHECK (pontuacao BETWEEN 1 AND 5)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Lista_Desejos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    jogo_id INT NOT NULL,
    data_adicionado DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (utilizador_id, jogo_id),
    CONSTRAINT fk_listadesejos_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE,
    CONSTRAINT fk_listadesejos_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE Notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    tipo VARCHAR(50) NOT NULL,
    conteudo VARCHAR(255) NOT NULL,
    lida TINYINT(1) NOT NULL DEFAULT 0,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_notificacoes_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
