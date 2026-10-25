<?php

class Jogo
{
    // guarda um jogo escolhido na pesquisa da rawg (se ainda não existir) e devolve o id dele na nossa bd
    public static function guardarDaRawg(int $rawgId, string $nome, string $slug, ?string $imagem, ?string $lancamento): int
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT id FROM Jogos WHERE rawg_id = :rawg');
        $stmt->execute(['rawg' => $rawgId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (int) $id;
        }

        $stmt = $pdo->prepare('INSERT INTO Jogos (rawg_id, nome, slug, imagem_capa, data_lancamento) VALUES (:rawg, :nome, :slug, :imagem, :lancamento)');
        $stmt->execute([
            'rawg' => $rawgId,
            'nome' => $nome,
            'slug' => $slug,
            'imagem' => $imagem ?: null,
            'lancamento' => $lancamento ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    // procura o jogo da steam na nossa bd e, se ainda não existir, vai buscar os dados à loja e guarda-o
    public static function daSteam(int $appid): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, imagem_capa FROM Jogos WHERE steam_app_id = :appid');
        $stmt->execute(['appid' => $appid]);
        $jogo = $stmt->fetch();

        if ($jogo) {
            return $jogo;
        }

        $dados = Api::pedir('https://store.steampowered.com/api/appdetails?l=english&appids=' . $appid);
        $info = $dados[$appid]['data'] ?? null;

        // se a loja não responder fica para a próxima sincronização
        if (!is_array($info)) {
            return null;
        }

        // as "categorias" da steam dizem os modos de jogo, cada uma tem um número
        $categorias = array_column($info['categories'] ?? [], 'id');

        $stmt = $pdo->prepare('INSERT INTO Jogos (steam_app_id, nome, imagem_capa, data_lancamento, descricao, nota_metacritic,
            produtora, editora, modo_um_jogador, modo_multijogador, modo_cooperativo)
            VALUES (:appid, :nome, :imagem, :lancamento, :descricao, :metacritic, :produtora, :editora, :um_jogador, :multijogador, :cooperativo)');

        $stmt->execute([
            'appid' => $appid,
            'nome' => $info['name'],
            'imagem' => $info['header_image'] ?? null,
            'lancamento' => self::converterData($info['release_date']['date'] ?? ''),
            'descricao' => $info['short_description'] ?? null,
            'metacritic' => $info['metacritic']['score'] ?? null,
            // a steam dá listas, fica só a primeira
            'produtora' => $info['developers'][0] ?? null,
            'editora' => $info['publishers'][0] ?? null,
            'um_jogador' => in_array(2, $categorias) ? 1 : 0,
            'multijogador' => array_intersect([1, 36, 49], $categorias) ? 1 : 0,
            'cooperativo' => array_intersect([9, 38, 39], $categorias) ? 1 : 0,
        ]);

        $id = (int) $pdo->lastInsertId();

        foreach ($info['genres'] ?? [] as $genero) {
            self::ligarGenero($id, $genero['description']);
        }

        return ['id' => $id, 'imagem_capa' => $info['header_image'] ?? null];
    }

    // igual ao daSteam mas com o produto da loja da microsoft (vem da classe Xbox)
    public static function daXbox(string $titleId, array $produto): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, imagem_capa FROM Jogos WHERE xbox_id = :titulo');
        $stmt->execute(['titulo' => $titleId]);
        $jogo = $stmt->fetch();

        if ($jogo) {
            return $jogo;
        }

        $info = $produto['LocalizedProperties'][0] ?? null;
        if (!$info) {
            return null;
        }

        // as imagens largas são as mais parecidas com as da steam, a quadrada só se não houver outra
        $imagem = null;
        foreach (['TitledHeroArt', 'SuperHeroArt', 'Screenshot', 'BoxArt'] as $tipo) {
            foreach ($info['Images'] ?? [] as $foto) {
                if (!$imagem && $foto['ImagePurpose'] === $tipo) {
                    // o endereço vem sem o https e com ?w= a loja devolve a imagem mais pequena
                    $imagem = 'https:' . $foto['Uri'] . '?w=460';
                }
            }
        }
        if ($imagem && strlen($imagem) > 255) {
            $imagem = null;
        }

        // os jogos que ainda não saíram vêm com o ano 9998
        $lancamento = substr($produto['MarketProperties'][0]['OriginalReleaseDate'] ?? '', 0, 10);
        if ($lancamento === '' || str_starts_with($lancamento, '9998')) {
            $lancamento = null;
        }

        // os modos de jogo vêm nos atributos do produto
        $atributos = array_column($produto['Properties']['Attributes'] ?? [], 'Name');

        $stmt = $pdo->prepare('INSERT INTO Jogos (xbox_id, nome, imagem_capa, data_lancamento, descricao, editora, produtora,
            modo_um_jogador, modo_multijogador, modo_cooperativo)
            VALUES (:titulo, :nome, :imagem, :lancamento, :descricao, :editora, :produtora, :um_jogador, :multijogador, :cooperativo)');

        $stmt->execute([
            'titulo' => $titleId,
            'nome' => $info['ProductTitle'],
            'imagem' => $imagem,
            'lancamento' => $lancamento,
            'descricao' => ($info['ShortDescription'] ?? '') ?: ($info['ProductDescription'] ?? null),
            'editora' => ($info['PublisherName'] ?? '') ?: null,
            'produtora' => ($info['DeveloperName'] ?? '') ?: null,
            'um_jogador' => in_array('SinglePlayer', $atributos) ? 1 : 0,
            'multijogador' => array_intersect(['XblOnlineMultiPlayer', 'XblLocalMultiPlayer', 'XblCrossPlatformMultiPlayer'], $atributos) ? 1 : 0,
            'cooperativo' => array_intersect(['XblOnlineCoop', 'XblLocalCoop', 'XblCrossPlatformCoop'], $atributos) ? 1 : 0,
        ]);

        $id = (int) $pdo->lastInsertId();

        $generos = $produto['Properties']['Categories'] ?? [$produto['Properties']['Category'] ?? null];
        foreach ($generos as $genero) {
            // a categoria "Other" não diz nada, fica de fora
            if ($genero && $genero !== 'Other') {
                self::ligarGenero($id, $genero);
            }
        }

        return ['id' => $id, 'imagem_capa' => $imagem];
    }

    // igual ao daXbox mas com o produto da api da gog
    public static function daGog(string $gogId, array $produto): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, imagem_capa FROM Jogos WHERE gog_id = :gog');
        $stmt->execute(['gog' => $gogId]);
        $jogo = $stmt->fetch();

        if ($jogo) {
            return $jogo;
        }

        // o endereço da imagem vem sem o https
        $imagem = isset($produto['images']['logo2x']) ? 'https:' . $produto['images']['logo2x'] : null;

        $lancamento = substr($produto['release_date'] ?? '', 0, 10);

        $stmt = $pdo->prepare('INSERT INTO Jogos (gog_id, nome, imagem_capa, data_lancamento, descricao)
            VALUES (:gog, :nome, :imagem, :lancamento, :descricao)');

        $stmt->execute([
            'gog' => $gogId,
            'nome' => $produto['title'],
            'imagem' => $imagem,
            'lancamento' => $lancamento ?: null,
            // a descrição vem com html, por isso só se guarda o texto
            'descricao' => strip_tags($produto['description']['lead'] ?? '') ?: null,
        ]);

        return ['id' => (int) $pdo->lastInsertId(), 'imagem_capa' => $imagem];
    }

    // igual aos outros mas com o produto do catálogo da epic
    public static function daEpic(string $epicId, array $produto): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, imagem_capa FROM Jogos WHERE epic_id = :epic');
        $stmt->execute(['epic' => $epicId]);
        $jogo = $stmt->fetch();

        if ($jogo) {
            return $jogo;
        }

        // as imagens largas primeiro, como nas outras plataformas
        $imagem = null;
        foreach (['DieselGameBox', 'OfferImageWide', 'Thumbnail', 'DieselGameBoxTall'] as $tipo) {
            foreach ($produto['keyImages'] ?? [] as $foto) {
                if (!$imagem && $foto['type'] === $tipo) {
                    $imagem = $foto['url'];
                }
            }
        }
        if ($imagem && strlen($imagem) > 255) {
            $imagem = null;
        }

        $stmt = $pdo->prepare('INSERT INTO Jogos (epic_id, nome, imagem_capa, descricao, produtora)
            VALUES (:epic, :nome, :imagem, :descricao, :produtora)');

        $stmt->execute([
            'epic' => $epicId,
            'nome' => $produto['title'],
            'imagem' => $imagem,
            'descricao' => ($produto['description'] ?? '') ?: null,
            'produtora' => ($produto['developer'] ?? '') ?: null,
        ]);

        return ['id' => (int) $pdo->lastInsertId(), 'imagem_capa' => $imagem];
    }

    private static function ligarGenero(int $jogoId, string $nome): void
    {
        $pdo = Database::ligar();

        // o INSERT IGNORE não dá erro se o género já existir
        $stmt = $pdo->prepare('INSERT IGNORE INTO Generos (nome) VALUES (:nome)');
        $stmt->execute(['nome' => $nome]);

        $stmt = $pdo->prepare('SELECT id FROM Generos WHERE nome = :nome');
        $stmt->execute(['nome' => $nome]);
        $generoId = $stmt->fetchColumn();

        $stmt = $pdo->prepare('INSERT IGNORE INTO Jogos_Generos (jogo_id, genero_id) VALUES (:jogo, :genero)');
        $stmt->execute(['jogo' => $jogoId, 'genero' => $generoId]);
    }

    // a steam dá a data em texto, por exemplo "21 Aug, 2012"
    private static function converterData(string $texto): ?string
    {
        $data = DateTime::createFromFormat('j M, Y', $texto);

        return $data ? $data->format('Y-m-d') : null;
    }

    public static function buscarPorId(int $id): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Jogos WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    public static function generosDoJogo(int $id): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT g.nome FROM Jogos_Generos jg JOIN Generos g ON g.id = jg.genero_id WHERE jg.jogo_id = :id ORDER BY g.nome');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function plataformasDoJogo(int $id): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT p.nome FROM Jogos_Plataformas jp JOIN Plataformas p ON p.id = jp.plataforma_id WHERE jp.jogo_id = :id ORDER BY p.nome');
        $stmt->execute(['id' => $id]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    // procura um jogo que já esteja na bd (pela rawg, pela steam ou pelo nome), para não haver repetidos
    public static function procurar(int $rawgId, string $slug, int $steamAppId, string $nome): ?int
    {
        $pdo = Database::ligar();
        $procuras = [
            ['rawg_id', $rawgId],
            ['slug', $slug],
            ['steam_app_id', $steamAppId],
            ['nome', $nome],
        ];

        // os nomes das colunas vêm desta lista fixa, por isso podem ir diretos para o sql
        foreach ($procuras as $procura) {
            if ($procura[1]) {
                $stmt = $pdo->prepare('SELECT id FROM Jogos WHERE ' . $procura[0] . ' = :valor ORDER BY id LIMIT 1');
                $stmt->execute(['valor' => $procura[1]]);
                $id = $stmt->fetchColumn();

                if ($id) {
                    return (int) $id;
                }
            }
        }

        return null;
    }

    // completa um jogo com os detalhes da rawg, sem apagar o que já lá estava (por exemplo os dados da steam)
    public static function guardarDetalhesRawg(int $id, array $dados): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Jogos SET
            rawg_id = COALESCE(rawg_id, :rawg),
            slug = COALESCE(slug, :slug),
            steam_app_id = COALESCE(steam_app_id, :steam),
            imagem_capa = COALESCE(imagem_capa, :imagem),
            data_lancamento = COALESCE(data_lancamento, :lancamento),
            descricao = COALESCE(descricao, :descricao),
            nota_metacritic = COALESCE(nota_metacritic, :metacritic),
            produtora = COALESCE(produtora, :produtora),
            editora = COALESCE(editora, :editora),
            modo_um_jogador = GREATEST(modo_um_jogador, :um_jogador),
            modo_multijogador = GREATEST(modo_multijogador, :multijogador),
            modo_cooperativo = GREATEST(modo_cooperativo, :cooperativo)
            WHERE id = :id');

        $stmt->execute([
            'rawg' => $dados['rawg_id'],
            'slug' => $dados['slug'],
            'steam' => $dados['steam'],
            'imagem' => $dados['imagem'],
            'lancamento' => $dados['lancamento'],
            'descricao' => $dados['descricao'],
            'metacritic' => $dados['metacritic'],
            'produtora' => $dados['produtora'],
            'editora' => $dados['editora'],
            'um_jogador' => $dados['um_jogador'],
            'multijogador' => $dados['multijogador'],
            'cooperativo' => $dados['cooperativo'],
            'id' => $id,
        ]);

        foreach ($dados['generos'] as $genero) {
            self::ligarGenero($id, $genero);
        }

        foreach ($dados['plataformas'] as $plataforma) {
            self::ligarPlataforma($id, $plataforma);
        }
    }

    // só liga às plataformas que já existem na tabela (PC, PlayStation 5, ...), as outras ficam de fora
    private static function ligarPlataforma(int $jogoId, string $nome): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM Plataformas WHERE nome = :nome');
        $stmt->execute(['nome' => $nome]);
        $plataformaId = $stmt->fetchColumn();

        if ($plataformaId) {
            $stmt = $pdo->prepare('INSERT IGNORE INTO Jogos_Plataformas (jogo_id, plataforma_id) VALUES (:jogo, :plataforma)');
            $stmt->execute(['jogo' => $jogoId, 'plataforma' => $plataformaId]);
        }
    }

    // links para as lojas onde o jogo está, conforme os ids que temos dele
    public static function lojas(array $jogo): array
    {
        $lojas = [];

        if ($jogo['steam_app_id']) {
            $lojas['Steam'] = 'https://store.steampowered.com/app/' . $jogo['steam_app_id'];
        }
        if ($jogo['xbox_id']) {
            $lojas['Xbox'] = 'https://www.xbox.com/pt-PT/search?q=' . urlencode($jogo['nome']);
        }
        if ($jogo['gog_id']) {
            $lojas['GOG'] = 'https://www.gog.com/en/games?query=' . urlencode($jogo['nome']);
        }
        if ($jogo['epic_id']) {
            $lojas['Epic Games'] = 'https://store.epicgames.com/pt/browse?q=' . urlencode($jogo['nome']);
        }

        return $lojas;
    }

    public static function plataformas(): array
    {
        $pdo = Database::ligar();
        return $pdo->query('SELECT id, nome FROM Plataformas ORDER BY nome')->fetchAll();
    }
}
