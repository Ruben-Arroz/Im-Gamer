<?php

class Steam
{
    // jogos com itens que dá para vender no mercado da steam
    const JOGOS_COM_INVENTARIO = [730 => 'Counter-Strike 2', 440 => 'Team Fortress 2', 570 => 'Dota 2', 252490 => 'Rust'];

    // o mercado da steam bloqueia quem faz muitos pedidos, por isso há um limite de itens com preço
    const MAX_ITENS_COM_PRECO = 30;

    // aceita o link do perfil, o link personalizado (/id/nome), o steamid64 ou só o nome personalizado
    public static function descobrirId(string $identificador): ?string
    {
        if (preg_match('/profiles\/(\d{17})/', $identificador, $partes)) {
            return $partes[1];
        }

        if (preg_match('/^\d{17}$/', $identificador)) {
            return $identificador;
        }

        if (preg_match('/\/id\/([^\/]+)/', $identificador, $partes)) {
            $nome = $partes[1];
        } else {
            $nome = $identificador;
        }

        $dados = Api::pedir('https://api.steampowered.com/ISteamUser/ResolveVanityURL/v1/?key=' . STEAM_API_KEY . '&vanityurl=' . urlencode($nome));

        return $dados['response']['steamid'] ?? null;
    }

    public static function sincronizar(array $conta): array
    {
        $steamId = self::descobrirId(trim($conta['identificador']));

        if (!$steamId) {
            return ['sucesso' => false, 'erro' => 'Não encontrei esta conta Steam.'];
        }

        $dados = Api::pedir('https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=' . STEAM_API_KEY . '&steamids=' . $steamId);
        $perfil = $dados['response']['players'][0] ?? null;

        if (!$perfil) {
            return ['sucesso' => false, 'erro' => 'Não consegui ler o perfil da Steam.'];
        }

        $dados = Api::pedir('https://api.steampowered.com/IPlayerService/GetSteamLevel/v1/?key=' . STEAM_API_KEY . '&steamid=' . $steamId);
        $nivel = $dados['response']['player_level'] ?? null;

        // se os detalhes dos jogos forem privados a lista vem vazia
        $dados = Api::pedir('https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?key=' . STEAM_API_KEY . '&steamid=' . $steamId . '&include_appinfo=1&include_played_free_games=1');
        $jogos = $dados['response']['games'] ?? [];

        $precos = self::precos(array_column($jogos, 'appid'));
        $itens = self::inventario($steamId, array_column($jogos, 'appid'));

        $minutosTotal = 0;
        $conquistasTotal = 0;
        $valorJogos = 0;

        foreach ($jogos as $jogo) {
            $minutosTotal += $jogo['playtime_forever'];
            $valorJogos += $precos[$jogo['appid']] ?? 0;

            // só vale a pena ver as conquistas dos jogos que já foram jogados
            if ($jogo['playtime_forever'] > 0) {
                $conquistasTotal += self::conquistas($steamId, $jogo['appid']);
            }
        }

        $valorInventario = 0;
        foreach ($itens as $item) {
            $valorInventario += ($item['preco'] ?? 0) * $item['quantidade'];
        }

        $pdo = Database::ligar();
        $pdo->beginTransaction();

        // apaga o que havia e volta a guardar tudo, assim jogos e itens que já não existem desaparecem
        $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
        $stmt->execute(['conta' => $conta['id']]);

        $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas, valor, ultima_sincronizacao)
            VALUES (:conta, :appid, :nome, :imagem, :minutos, :minutos2, :valor, NOW())');

        foreach ($jogos as $jogo) {
            $stmt->execute([
                'conta' => $conta['id'],
                'appid' => $jogo['appid'],
                'nome' => $jogo['name'],
                'imagem' => self::imagemJogo($jogo),
                'minutos' => $jogo['playtime_forever'],
                'minutos2' => $jogo['playtime_2weeks'] ?? 0,
                'valor' => $precos[$jogo['appid']] ?? null,
            ]);
        }

        $stmt = $pdo->prepare('DELETE FROM Inventario_Itens WHERE conta_id = :conta');
        $stmt->execute(['conta' => $conta['id']]);

        $stmt = $pdo->prepare('INSERT INTO Inventario_Itens (conta_id, jogo, nome, imagem, quantidade, preco, ultima_sincronizacao)
            VALUES (:conta, :jogo, :nome, :imagem, :quantidade, :preco, NOW())');

        foreach ($itens as $item) {
            $stmt->execute([
                'conta' => $conta['id'],
                'jogo' => $item['jogo'],
                'nome' => $item['nome'],
                'imagem' => $item['imagem'],
                'quantidade' => $item['quantidade'],
                'preco' => $item['preco'],
            ]);
        }

        $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET id_externo = :steamid, nome_publico = :nome, avatar = :avatar, nivel = :nivel,
            num_jogos = :jogos, minutos_jogados = :minutos, num_conquistas = :conquistas, valor_jogos = :valor_jogos,
            valor_inventario = :valor_inventario, ultima_sincronizacao = NOW() WHERE id = :id');

        $stmt->execute([
            'steamid' => $steamId,
            'nome' => $perfil['personaname'],
            'avatar' => $perfil['avatarfull'],
            'nivel' => $nivel,
            'jogos' => count($jogos),
            'minutos' => $minutosTotal,
            'conquistas' => $conquistasTotal,
            'valor_jogos' => $valorJogos,
            'valor_inventario' => $valorInventario,
            'id' => $conta['id'],
        ]);

        $pdo->commit();

        return ['sucesso' => true, 'jogos' => count($jogos), 'itens' => count($itens)];
    }

    // preço normal de cada jogo na loja portuguesa da steam (em euros), os jogos grátis não têm preço
    private static function precos(array $appids): array
    {
        $precos = [];

        // a loja aceita vários jogos de uma vez quando só se pede o preço
        foreach (array_chunk($appids, 100) as $grupo) {
            $dados = Api::pedir('https://store.steampowered.com/api/appdetails?cc=pt&filters=price_overview&appids=' . implode(',', $grupo));

            foreach ($dados ?? [] as $appid => $info) {
                if (isset($info['data']['price_overview'])) {
                    $precos[$appid] = $info['data']['price_overview']['initial'] / 100;
                }
            }
        }

        return $precos;
    }

    private static function imagemJogo(array $jogo): string
    {
        $imagem = 'https://shared.akamai.steamstatic.com/store_item_assets/steam/apps/' . $jogo['appid'] . '/header.jpg';

        if (Api::existe($imagem)) {
            return $imagem;
        }

        // nos jogos mais recentes o endereço da imagem tem um código, por isso é preciso pedir à loja
        $dados = Api::pedir('https://store.steampowered.com/api/appdetails?appids=' . $jogo['appid'] . '&filters=basic');

        if (isset($dados[$jogo['appid']]['data']['header_image'])) {
            return $dados[$jogo['appid']]['data']['header_image'];
        }

        // se nada der, fica o ícone pequeno do jogo
        return 'https://media.steampowered.com/steamcommunity/public/images/apps/' . $jogo['appid'] . '/' . $jogo['img_icon_url'] . '.jpg';
    }

    private static function conquistas(string $steamId, int $appid): int
    {
        $dados = Api::pedir('https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/?key=' . STEAM_API_KEY . '&steamid=' . $steamId . '&appid=' . $appid);
        $total = 0;

        foreach ($dados['playerstats']['achievements'] ?? [] as $conquista) {
            if ($conquista['achieved'] == 1) {
                $total++;
            }
        }

        return $total;
    }

    private static function inventario(string $steamId, array $appidsDaConta): array
    {
        $itens = [];
        $comPreco = 0;

        foreach (self::JOGOS_COM_INVENTARIO as $appid => $nomeJogo) {
            if (!in_array($appid, $appidsDaConta)) {
                continue;
            }

            $dados = Api::pedir('https://steamcommunity.com/inventory/' . $steamId . '/' . $appid . '/2?l=english&count=2000');

            if (!$dados || empty($dados['assets'])) {
                continue;
            }

            // os "assets" são os itens e as "descriptions" têm o nome e a imagem de cada tipo de item
            $descricoes = [];
            foreach ($dados['descriptions'] as $descricao) {
                $descricoes[$descricao['classid'] . '_' . $descricao['instanceid']] = $descricao;
            }

            // junta os itens repetidos para contar a quantidade
            $quantidades = [];
            foreach ($dados['assets'] as $asset) {
                $chave = $asset['classid'] . '_' . $asset['instanceid'];
                $quantidades[$chave] = ($quantidades[$chave] ?? 0) + (int) $asset['amount'];
            }

            foreach ($quantidades as $chave => $quantidade) {
                $descricao = $descricoes[$chave] ?? null;

                if (!$descricao || !$descricao['marketable']) {
                    continue;
                }

                $preco = null;
                if ($comPreco < self::MAX_ITENS_COM_PRECO) {
                    $preco = self::precoMercado($appid, $descricao['market_hash_name']);
                    $comPreco++;
                    sleep(3);
                }

                $itens[] = [
                    'jogo' => $nomeJogo,
                    'nome' => $descricao['market_hash_name'],
                    'imagem' => 'https://community.cloudflare.steamstatic.com/economy/image/' . $descricao['icon_url'],
                    'quantidade' => $quantidade,
                    'preco' => $preco,
                ];
            }
        }

        return $itens;
    }

    private static function precoMercado(int $appid, string $nomeItem): ?float
    {
        // currency=3 é o euro
        $dados = Api::pedir('https://steamcommunity.com/market/priceoverview/?appid=' . $appid . '&currency=3&market_hash_name=' . rawurlencode($nomeItem));
        $texto = $dados['lowest_price'] ?? $dados['median_price'] ?? null;

        if (!$texto) {
            return null;
        }

        // o preço vem como texto, por exemplo "1.234,56€"
        $texto = str_replace('.', '', $texto);
        $texto = str_replace(',', '.', $texto);

        return (float) preg_replace('/[^0-9.]/', '', $texto);
    }
}
