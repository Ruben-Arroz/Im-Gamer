-- I'm Gamer
-- Alterações à BD de 23/10/2026
-- Correr no Workbench com o utilizador dbo (o web não tem permissão para alterar tabelas)

-- moeda escolhida pelo utilizador para ver o valor das contas
ALTER TABLE Utilizadores
    ADD moeda CHAR(3) NOT NULL DEFAULT 'EUR' AFTER bio;

-- id do jogo na Steam, para ir buscar as promoções à CheapShark e os preços à Steam
ALTER TABLE Jogos
    ADD steam_app_id INT DEFAULT NULL UNIQUE AFTER rawg_id;

-- plataforma e número de vagas nos anúncios de LFG
ALTER TABLE LFG_Posts
    ADD plataforma_id INT DEFAULT NULL AFTER jogo_id,
    ADD vagas TINYINT NOT NULL DEFAULT 1 AFTER descricao,
    ADD CONSTRAINT fk_lfgposts_plataforma FOREIGN KEY (plataforma_id) REFERENCES Plataformas (id) ON DELETE SET NULL;

-- contas de cada plataforma (steam, playstation, xbox, ...) que o utilizador liga ao perfil
-- os valores ficam guardados em euros, a conversão para a moeda do utilizador é feita na página
CREATE TABLE Contas_Ligadas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    utilizador_id INT NOT NULL,
    servico VARCHAR(30) NOT NULL,
    identificador VARCHAR(255) NOT NULL,
    id_externo VARCHAR(100) DEFAULT NULL,
    nome_publico VARCHAR(100) DEFAULT NULL,
    avatar VARCHAR(255) DEFAULT NULL,
    num_jogos INT NOT NULL DEFAULT 0,
    minutos_jogados INT NOT NULL DEFAULT 0,
    valor_jogos DECIMAL(10,2) NOT NULL DEFAULT 0,
    valor_inventario DECIMAL(10,2) NOT NULL DEFAULT 0,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    data_criacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE (utilizador_id, servico),
    CONSTRAINT fk_contas_utilizador FOREIGN KEY (utilizador_id) REFERENCES Utilizadores (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- a biblioteca passa a pertencer a uma conta ligada e não só ao utilizador
-- a tabela está vazia, por isso é mais simples apagar e criar de novo
DROP TABLE Biblioteca_Jogos;

CREATE TABLE Biblioteca_Jogos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conta_id INT NOT NULL,
    jogo_id INT DEFAULT NULL,
    id_externo VARCHAR(50) DEFAULT NULL,
    nome VARCHAR(255) NOT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    minutos_jogados INT NOT NULL DEFAULT 0,
    valor DECIMAL(10,2) DEFAULT NULL,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    UNIQUE (conta_id, id_externo),
    CONSTRAINT fk_biblioteca_conta FOREIGN KEY (conta_id) REFERENCES Contas_Ligadas (id) ON DELETE CASCADE,
    CONSTRAINT fk_biblioteca_jogo FOREIGN KEY (jogo_id) REFERENCES Jogos (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- itens dos inventários (skins do CS2, por exemplo), para calcular o valor do inventário
CREATE TABLE Inventario_Itens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    conta_id INT NOT NULL,
    jogo VARCHAR(100) NOT NULL,
    nome VARCHAR(255) NOT NULL,
    imagem VARCHAR(255) DEFAULT NULL,
    quantidade INT NOT NULL DEFAULT 1,
    preco DECIMAL(10,2) DEFAULT NULL,
    ultima_sincronizacao DATETIME DEFAULT NULL,
    CONSTRAINT fk_inventario_conta FOREIGN KEY (conta_id) REFERENCES Contas_Ligadas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- taxas de câmbio a partir do euro, atualizadas uma vez por dia
CREATE TABLE Taxas_Cambio (
    moeda CHAR(3) PRIMARY KEY,
    taxa DECIMAL(12,6) NOT NULL,
    data_atualizacao DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
