<?php

// a gog não tem api oficial para jogadores, mas o perfil público (tem de estar visível para todos) dá a lista de jogos
// os detalhes e os preços vêm da api da loja, que também é pública
class Gog
{
    private static function progresso(string $texto): void
    {
        if (php_sapi_name() === 'cli') {
            echo '  ' . $texto . "\n";
        }
    }

    // aceita só o nome ou o link do perfil (gog.com/u/Nome)
    public static function descobrirNome(string $identificador): string
    {
        if (preg_match('/\/u\/([^\/?#]+)/', $identificador, $partes)) {
            return urldecode($partes[1]);
        }

        return $identificador;
    }

    // a foto de perfil só aparece no html da página do perfil
    private static function avatar(string $nome): ?string
    {
        $curl = curl_init('https://www.gog.com/u/' . rawurlencode($nome));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        curl_setopt($curl, CURLOPT_USERAGENT, 'ImGamer/1.0 (projeto escolar ESAN UA)');
        $html = curl_exec($curl);

        if ($html && preg_match('/https:\/\/images\.gog\.com\/[a-f0-9]+_prof_avatar_140x140\.jpg/', $html, $partes)) {
            return $partes[0];
        }

        return null;
    }

    // todos os jogos da conta, a gog dá 50 de cada vez
    private static function jogos(string $nome): ?array
    {
        $jogos = [];
        $pagina = 1;

        do {
            $dados = Api::pedir('https://www.gog.com/u/' . rawurlencode($nome) . '/games/stats?sort=title&order=asc&page=' . $pagina);

            if ($dados === null) {
                return null;
            }

            $jogos = array_merge($jogos, $dados['_embedded']['items'] ?? []);
            $pagina++;
        } while ($pagina <= ($dados['pages'] ?? 0));

        return $jogos;
    }

    // preço normal na loja portuguesa, em euros (a gog escreve "19.99 EUR")
    private static function preco(string $id): ?float
    {
        $dados = Api::pedir('https://api.gog.com/products/' . $id . '/prices?countryCode=PT&currency=EUR');
        $texto = $dados['_embedded']['prices'][0]['basePrice'] ?? '';
        $preco = (float) $texto;

        return $preco > 0 ? $preco : null;
    }

    public static function sincronizar(array $conta): array
    {
        $nome = self::descobrirNome(trim($conta['identificador']));
        $jogos = self::jogos($nome);

        if ($jogos === null) {
            return ['sucesso' => false, 'erro' => 'Não consegui ler o perfil da GOG. Vê se o nome está certo.'];
        }

        self::progresso('Perfil: ' . $nome);
        self::progresso(count($jogos) . ' jogos');

        $minutosTotal = 0;
        $valorJogos = 0;
        $linhas = [];

        foreach ($jogos as $i => $item) {
            $id = (string) $item['game']['id'];
            $produto = Api::pedir('https://api.gog.com/products/' . $id . '?expand=description');
            $jogoBd = $produto ? Jogo::daGog($id, $produto) : null;
            $preco = self::preco($id);

            // as estatísticas só existem depois de o jogo ser aberto no gog galaxy
            $estatisticas = $item['stats'] ? reset($item['stats']) : [];
            $minutos = isset($estatisticas['playtime']) ? (int) $estatisticas['playtime'] : null;
            $ultimaVez = !empty($estatisticas['lastSession']) ? (new DateTime($estatisticas['lastSession']))->format('Y-m-d H:i:s') : null;

            $minutosTotal += $minutos ?? 0;
            $valorJogos += $preco ?? 0;

            $linhas[] = [
                'jogo' => $jogoBd['id'] ?? null,
                'id' => $id,
                'nome' => $item['game']['title'],
                'imagem' => $jogoBd['imagem_capa'] ?? $item['game']['image'],
                'minutos' => $minutos,
                'ultima_vez' => $ultimaVez,
                'valor' => $preco,
            ];

            self::progresso('[' . ($i + 1) . '/' . count($jogos) . '] ' . $item['game']['title']
                . ($preco ? ' = ' . number_format($preco, 2, ',', '') . ' €' : ''));
        }

        $pdo = Database::religar();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
            $stmt->execute(['conta' => $conta['id']]);

            $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
                ultima_vez_jogado, conquistas, conquistas_total, valor, ultima_sincronizacao)
                VALUES (:conta, :jogo, :id, :nome, :imagem, :minutos, 0, :ultima_vez, 0, 0, :valor, NOW())');

            foreach ($linhas as $linha) {
                $linha['conta'] = $conta['id'];
                $stmt->execute($linha);
            }

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET id_externo = :nome, nome_publico = :nome, avatar = :avatar, nivel = NULL,
                num_jogos = :jogos, minutos_jogados = :minutos, num_conquistas = 0, valor_jogos = :valor_jogos, valor_inventario = 0,
                ultima_sincronizacao = NOW() WHERE id = :id');

            $stmt->execute([
                'nome' => $nome,
                'avatar' => self::avatar($nome),
                'jogos' => count($jogos),
                'minutos' => $minutosTotal,
                'valor_jogos' => $valorJogos,
                'id' => $conta['id'],
            ]);

            $pdo->commit();
        } catch (PDOException $erro) {
            // se alguma coisa falhar, desfaz tudo e a conta fica como estava
            $pdo->rollBack();
            error_log($erro->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao guardar na base de dados: ' . $erro->getMessage()];
        }

        return ['sucesso' => true, 'jogos' => count($jogos)];
    }
}
