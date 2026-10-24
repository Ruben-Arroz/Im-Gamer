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
            modo_um_jogador, modo_multijogador, modo_cooperativo)
            VALUES (:appid, :nome, :imagem, :lancamento, :descricao, :metacritic, :um_jogador, :multijogador, :cooperativo)');

        $stmt->execute([
            'appid' => $appid,
            'nome' => $info['name'],
            'imagem' => $info['header_image'] ?? null,
            'lancamento' => self::converterData($info['release_date']['date'] ?? ''),
            'descricao' => $info['short_description'] ?? null,
            'metacritic' => $info['metacritic']['score'] ?? null,
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

    public static function plataformas(): array
    {
        $pdo = Database::ligar();
        return $pdo->query('SELECT id, nome FROM Plataformas ORDER BY nome')->fetchAll();
    }
}
