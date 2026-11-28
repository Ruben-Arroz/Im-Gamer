<?php

class Steam
{
    // inventários com itens que dá para vender no mercado, appid => [nome, contexto]
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

    // itens que precisam de preço, os que nunca tiveram e os que têm um preço com mais de um dia
    const ITEM_SEM_PRECO = 'i.nome_mercado IS NOT NULL AND (i.ultima_sincronizacao IS NULL OR i.ultima_sincronizacao < NOW() - INTERVAL 1 DAY)';

    // o tarefas/sincronizar.php liga isto para ir mostrando o que está a fazer
    public static bool $mostrarProgresso = false;

    private static function progresso(string $texto): void
    {
        if (self::$mostrarProgresso) {
            echo '  ' . $texto . "\n";
        }
    }

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
        self::progresso('Perfil: ' . $perfil['personaname'] . ' (nível ' . ($nivel ?? '?') . ')');

        $reputacao = self::reputacao($steamId);
        self::progresso('Reputação: ' . ($reputacao ?? '?'));

        // se os detalhes dos jogos forem privados a lista vem vazia
        $dados = Api::pedir('https://api.steampowered.com/IPlayerService/GetOwnedGames/v1/?key=' . STEAM_API_KEY . '&steamid=' . $steamId . '&include_appinfo=1&include_played_free_games=1');
        $jogos = $dados['response']['games'] ?? [];
        self::progresso(count($jogos) . ' jogos na biblioteca');

        $precos = self::precos(array_column($jogos, 'appid'));
        self::progresso(count($precos) . ' jogos com preço na loja');

        $minutosTotal = 0;
        $conquistasTotal = 0;
        $valorJogos = 0;
        $detalhes = [];

        // tudo o que vem da internet é tratado antes de começar a guardar, para a bd não ficar à espera
        foreach ($jogos as $numero => $jogo) {
            $appid = $jogo['appid'];
            self::progresso('[' . ($numero + 1) . '/' . count($jogos) . '] ' . $jogo['name']);
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

        self::progresso('A guardar na base de dados...');

        // a parte de ir à internet demora, por isso abre-se uma ligação nova antes de guardar
        $pdo = Database::religar();
        $pdo->beginTransaction();

        try {
            self::guardar($pdo, $conta['id'], $jogos, $detalhes, $precos);

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET id_externo = :steamid, nome_publico = :nome, avatar = :avatar, nivel = :nivel,
                reputacao = :reputacao, num_jogos = :jogos, minutos_jogados = :minutos, num_conquistas = :conquistas, valor_jogos = :valor_jogos,
                ultima_sincronizacao = NOW() WHERE id = :id');

            $stmt->execute([
                'steamid' => $steamId,
                'nome' => $perfil['personaname'],
                'avatar' => $perfil['avatarfull'],
                'nivel' => $nivel,
                'reputacao' => $reputacao,
                'jogos' => count($jogos),
                'minutos' => $minutosTotal,
                'conquistas' => $conquistasTotal,
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

    // bans da conta (vac, de jogos, da comunidade e de trocas) num texto curto, null se a steam não responder
    private static function reputacao(string $steamId): ?string
    {
        $dados = Api::pedir('https://api.steampowered.com/ISteamUser/GetPlayerBans/v1/?key=' . STEAM_API_KEY . '&steamids=' . $steamId);
        $bans = $dados['players'][0] ?? null;

        if (!$bans) {
            return null;
        }

        $partes = [];
        if ($bans['NumberOfVACBans'] > 0) {
            $partes[] = $bans['NumberOfVACBans'] . ' VAC ' . ($bans['NumberOfVACBans'] == 1 ? 'ban' : 'bans');
        }
        if ($bans['NumberOfGameBans'] > 0) {
            $partes[] = $bans['NumberOfGameBans'] . ($bans['NumberOfGameBans'] == 1 ? ' ban' : ' bans') . ' de jogo';
        }
        if ($bans['CommunityBanned']) {
            $partes[] = 'ban da comunidade';
        }
        if ($bans['EconomyBan'] !== 'none') {
            $partes[] = 'ban de trocas';
        }

        if (!$partes) {
            return 'Sem bans';
        }

        $texto = ucfirst(implode(', ', $partes));
        if ($bans['DaysSinceLastBan'] > 0) {
            $texto .= ' (o último há ' . $bans['DaysSinceLastBan'] . ' dias)';
        }

        return $texto;
    }

    private static function guardar(PDO $pdo, int $contaId, array $jogos, array $detalhes, array $precos): void
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
    }

    // preço normal de cada jogo na loja portuguesa da steam (em euros)
    // os grátis vêm sem preço mas com success true (ficam a 0), os que saíram da loja vêm com success false
    private static function precos(array $appids): array
    {
        $precos = [];

        // a loja aceita vários jogos de uma vez quando só se pede o preço
        foreach (array_chunk($appids, 100) as $grupo) {
            $dados = Api::pedir('https://store.steampowered.com/api/appdetails?cc=pt&filters=price_overview&appids=' . implode(',', $grupo));

            foreach ($dados ?? [] as $appid => $info) {
                if (isset($info['data']['price_overview'])) {
                    $precos[$appid] = $info['data']['price_overview']['initial'] / 100;
                } elseif ($info['success'] ?? false) {
                    $precos[$appid] = 0;
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

    // lê os itens do inventário, sem os preços (esses são pedidos aos poucos pelo avaliarItens)
    // devolve null se a steam não respondeu, para não se apagarem os itens que já estavam guardados
    private static function lerInventario(string $steamId, array $appidsDaConta): ?array
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

            if ($dados === null) {
                return null;
            }
            if (empty($dados['assets'])) {
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

                // só os primeiros itens ficam com o nome do mercado, os outros nunca são avaliados
                $nomeMercado = null;
                if ($comPreco < self::MAX_ITENS_COM_PRECO) {
                    $nomeMercado = $descricao['market_hash_name'];
                    $comPreco++;
                }

                $itens[] = [
                    'appid' => $appid,
                    'jogo' => $nomeJogo,
                    'nome' => $descricao['name'],
                    'nome_mercado' => $nomeMercado,
                    'imagem' => 'https://community.cloudflare.steamstatic.com/economy/image/' . $descricao['icon_url'],
                    'quantidade' => $quantidade,
                ];
            }
        }

        return $itens;
    }

    // vai buscar a lista de itens e guarda-a, os itens que já estavam ficam com o preço que tinham
    public static function atualizarItens(int $contaId): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id_externo FROM Contas_Ligadas WHERE id = :id');
        $stmt->execute(['id' => $contaId]);
        $steamId = $stmt->fetchColumn();

        // os appids dos jogos da conta estão no id_externo da biblioteca
        $stmt = $pdo->prepare('SELECT id_externo FROM Biblioteca_Jogos WHERE conta_id = :id');
        $stmt->execute(['id' => $contaId]);
        $appids = array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));

        $itens = $steamId ? self::lerInventario($steamId, $appids) : null;
        if ($itens === null) {
            return false;
        }
        self::progresso(count($itens) . ' itens no inventário');

        // preços que já se sabiam, pelo nome do mercado
        $stmt = $pdo->prepare('SELECT nome_mercado, preco, ultima_sincronizacao FROM Inventario_Itens WHERE conta_id = :id AND nome_mercado IS NOT NULL');
        $stmt->execute(['id' => $contaId]);
        $antigos = [];
        foreach ($stmt->fetchAll() as $antigo) {
            $antigos[$antigo['nome_mercado']] = $antigo;
        }

        $pdo->beginTransaction();
        $stmt = $pdo->prepare('DELETE FROM Inventario_Itens WHERE conta_id = :id');
        $stmt->execute(['id' => $contaId]);

        $stmt = $pdo->prepare('INSERT INTO Inventario_Itens (conta_id, appid, jogo, nome, nome_mercado, imagem, quantidade, preco, ultima_sincronizacao)
            VALUES (:conta, :appid, :jogo, :nome, :nome_mercado, :imagem, :quantidade, :preco, :data)');
        foreach ($itens as $item) {
            $antigo = $antigos[$item['nome_mercado']] ?? null;
            $stmt->execute([
                'conta' => $contaId,
                'appid' => $item['appid'],
                'jogo' => $item['jogo'],
                'nome' => $item['nome'],
                'nome_mercado' => $item['nome_mercado'],
                'imagem' => $item['imagem'],
                'quantidade' => $item['quantidade'],
                'preco' => $antigo['preco'] ?? null,
                'data' => $antigo['ultima_sincronizacao'] ?? null,
            ]);
        }
        $pdo->commit();

        self::guardarValorInventario($contaId);
        return true;
    }

    // pede o preço de alguns itens sem preço ou com o preço antigo e devolve quantos ainda faltam
    // são poucos de cada vez e com 3 s entre pedidos pq o mercado bloqueia quem pede muito
    public static function avaliarItens(int $contaId, int $quantos): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT i.id, i.appid, i.nome, i.nome_mercado FROM Inventario_Itens i
            WHERE i.conta_id = :id AND ' . self::ITEM_SEM_PRECO . ' ORDER BY i.ultima_sincronizacao LIMIT :quantos');
        $stmt->bindValue('id', $contaId, PDO::PARAM_INT);
        $stmt->bindValue('quantos', $quantos, PDO::PARAM_INT);
        $stmt->execute();
        $itens = $stmt->fetchAll();

        $guardar = $pdo->prepare('UPDATE Inventario_Itens SET preco = :preco, ultima_sincronizacao = NOW() WHERE id = :id');
        foreach ($itens as $numero => $item) {
            if ($numero > 0) {
                sleep(3);
            }
            // mesmo sem preço fica com a data, para não se voltar a pedir antes de passar um dia
            $preco = self::precoMercado($item['appid'], $item['nome_mercado']);
            $guardar->execute(['preco' => $preco, 'id' => $item['id']]);
            self::progresso('Inventário: ' . $item['nome'] . ' = ' . ($preco !== null ? number_format($preco, 2, ',', ' ') . ' €' : 'sem preço'));
        }

        self::guardarValorInventario($contaId);
        return self::itensPorAvaliar(null, $contaId);
    }

    // quantos itens precisam de preço, de um utilizador, de uma conta ou do site todo (os dois a null)
    public static function itensPorAvaliar(?int $utilizadorId, ?int $contaId = null): int
    {
        $sql = 'SELECT COUNT(*) FROM Inventario_Itens i JOIN Contas_Ligadas c ON c.id = i.conta_id WHERE ' . self::ITEM_SEM_PRECO;
        $parametros = [];
        if ($utilizadorId !== null) {
            $sql .= ' AND c.utilizador_id = :utilizador';
            $parametros['utilizador'] = $utilizadorId;
        }
        if ($contaId !== null) {
            $sql .= ' AND i.conta_id = :conta';
            $parametros['conta'] = $contaId;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);
        return (int) $stmt->fetchColumn();
    }

    // a conta com itens à espera de preço há mais tempo, de um utilizador ou do site todo
    public static function contaComItensPorAvaliar(?int $utilizadorId): ?int
    {
        $sql = 'SELECT i.conta_id FROM Inventario_Itens i JOIN Contas_Ligadas c ON c.id = i.conta_id WHERE ' . self::ITEM_SEM_PRECO;
        $parametros = [];
        if ($utilizadorId !== null) {
            $sql .= ' AND c.utilizador_id = :utilizador';
            $parametros['utilizador'] = $utilizadorId;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare($sql . ' ORDER BY i.ultima_sincronizacao LIMIT 1');
        $stmt->execute($parametros);
        $contaId = $stmt->fetchColumn();
        return $contaId === false ? null : (int) $contaId;
    }

    // o valor do inventário da conta é a soma dos preços que se sabem
    private static function guardarValorInventario(int $contaId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET valor_inventario =
            (SELECT COALESCE(SUM(preco * quantidade), 0) FROM Inventario_Itens WHERE conta_id = :conta) WHERE id = :id');
        $stmt->execute(['conta' => $contaId, 'id' => $contaId]);
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
