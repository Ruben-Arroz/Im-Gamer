<?php

class Steam
{
    // inventários com itens que dá para vender no mercado: appid => [nome, contexto]
    // o 753 é a própria steam (cartas, fundos de perfil, emoticons) e usa o contexto 6
    const INVENTARIOS = [
        730 => ['Counter-Strike 2', 2],
        440 => ['Team Fortress 2', 2],
        570 => ['Dota 2', 2],
        252490 => ['Rust', 2],
        753 => ['Steam', 6],
    ];

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
        $detalhes = [];

        // tudo o que vem da internet é tratado antes de começar a guardar, para a bd não ficar à espera
        foreach ($jogos as $jogo) {
            $appid = $jogo['appid'];
            $minutosTotal += $jogo['playtime_forever'];
            $valorJogos += $precos[$appid] ?? 0;

            // o jogo fica na tabela Jogos (com géneros e modos de jogo) e só é pedido à loja da primeira vez
            $jogoBd = Jogo::daSteam($appid);

            $conquistas = [0, 0];
            // só vale a pena ver as conquistas dos jogos que já foram jogados
            if ($jogo['playtime_forever'] > 0) {
                $conquistas = self::conquistas($steamId, $appid);
                $conquistasTotal += $conquistas[0];
            }

            // se a loja não tiver imagem fica o ícone pequeno do jogo
            $imagem = $jogoBd['imagem_capa'] ?? null;
            if (!$imagem) {
                $imagem = 'https://media.steampowered.com/steamcommunity/public/images/apps/' . $appid . '/' . $jogo['img_icon_url'] . '.jpg';
            }

            $detalhes[$appid] = [
                'jogo_id' => $jogoBd['id'] ?? null,
                'imagem' => $imagem,
                'conquistas' => $conquistas[0],
                'conquistas_total' => $conquistas[1],
            ];
        }

        $valorInventario = 0;
        foreach ($itens as $item) {
            $valorInventario += ($item['preco'] ?? 0) * $item['quantidade'];
        }

        $pdo = Database::ligar();
        $pdo->beginTransaction();

        try {
            self::guardar($pdo, $conta['id'], $jogos, $detalhes, $precos, $itens);

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
        } catch (PDOException $erro) {
            // se alguma coisa falhar, desfaz tudo e a conta fica como estava
            $pdo->rollBack();
            error_log($erro->getMessage());
            return ['sucesso' => false, 'erro' => 'Erro ao guardar na base de dados: ' . $erro->getMessage()];
        }

        return ['sucesso' => true, 'jogos' => count($jogos), 'itens' => count($itens)];
    }

    // apaga o que havia e volta a guardar tudo, assim jogos e itens que já não existem desaparecem
    private static function guardar(PDO $pdo, int $contaId, array $jogos, array $detalhes, array $precos, array $itens): void
    {
        $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
        $stmt->execute(['conta' => $contaId]);

        $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
            ultima_vez_jogado, conquistas, conquistas_total, valor, ultima_sincronizacao)
            VALUES (:conta, :jogo, :appid, :nome, :imagem, :minutos, :minutos2, :ultima_vez, :conquistas, :conquistas_total, :valor, NOW())');

        foreach ($jogos as $jogo) {
            $detalhe = $detalhes[$jogo['appid']];

            // a steam dá a última vez jogado em segundos desde 1970, e 0 quando nunca foi jogado
            $ultimaVez = null;
            if (!empty($jogo['rtime_last_played'])) {
                $ultimaVez = date('Y-m-d H:i:s', $jogo['rtime_last_played']);
            }

            $stmt->execute([
                'conta' => $contaId,
                'jogo' => $detalhe['jogo_id'],
                'appid' => $jogo['appid'],
                'nome' => $jogo['name'],
                'imagem' => $detalhe['imagem'],
                'minutos' => $jogo['playtime_forever'],
                'minutos2' => $jogo['playtime_2weeks'] ?? 0,
                'ultima_vez' => $ultimaVez,
                'conquistas' => $detalhe['conquistas'],
                'conquistas_total' => $detalhe['conquistas_total'],
                'valor' => $precos[$jogo['appid']] ?? null,
            ]);
        }

        $stmt = $pdo->prepare('DELETE FROM Inventario_Itens WHERE conta_id = :conta');
        $stmt->execute(['conta' => $contaId]);

        $stmt = $pdo->prepare('INSERT INTO Inventario_Itens (conta_id, jogo, nome, imagem, quantidade, preco, ultima_sincronizacao)
            VALUES (:conta, :jogo, :nome, :imagem, :quantidade, :preco, NOW())');

        foreach ($itens as $item) {
            $stmt->execute([
                'conta' => $contaId,
                'jogo' => $item['jogo'],
                'nome' => $item['nome'],
                'imagem' => $item['imagem'],
                'quantidade' => $item['quantidade'],
                'preco' => $item['preco'],
            ]);
        }
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

    // devolve [conquistas desbloqueadas, total de conquistas do jogo]
    private static function conquistas(string $steamId, int $appid): array
    {
        $dados = Api::pedir('https://api.steampowered.com/ISteamUserStats/GetPlayerAchievements/v1/?key=' . STEAM_API_KEY . '&steamid=' . $steamId . '&appid=' . $appid);
        $lista = $dados['playerstats']['achievements'] ?? [];
        $feitas = 0;

        foreach ($lista as $conquista) {
            if ($conquista['achieved'] == 1) {
                $feitas++;
            }
        }

        return [$feitas, count($lista)];
    }

    private static function inventario(string $steamId, array $appidsDaConta): array
    {
        $itens = [];
        $comPreco = 0;

        // os itens que estão à venda no mercado não aparecem no inventário público
        foreach (self::INVENTARIOS as $appid => $inventario) {
            [$nomeJogo, $contexto] = $inventario;

            // só vale a pena pedir o inventário dos jogos que a conta tem (a steam toda a gente tem)
            if ($appid !== 753 && !in_array($appid, $appidsDaConta)) {
                continue;
            }

            $dados = Api::pedir('https://steamcommunity.com/inventory/' . $steamId . '/' . $appid . '/' . $contexto . '?l=english&count=2000');

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
                    'nome' => $descricao['name'],
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
