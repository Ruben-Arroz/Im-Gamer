<?php

// a epic não tem perfis públicos, por isso usa-se o mesmo login que o launcher da epic usa (api não oficial)
// o utilizador entra na página da epic e cola o código no site, que vai logo buscar os jogos (o acesso não fica guardado)
class Epic
{
    // dados públicos do launcher, são os mesmos que as ferramentas não oficiais (como o legendary) usam
    const CLIENTE_ID = '34a02cf8f4414e29b15921876da36f9a';
    const CLIENTE_SEGREDO = 'daafbccc737745039dffe53d94fc76cf';

    private static function progresso(string $texto): void
    {
        if (php_sapi_name() === 'cli') {
            echo '  ' . $texto . "\n";
        }
    }

    // depois do login a epic mostra uma página com o código
    public static function linkLogin(): string
    {
        return 'https://www.epicgames.com/id/login?redirectUrl='
            . urlencode('https://www.epicgames.com/id/api/redirect?clientId=' . self::CLIENTE_ID . '&responseType=code');
    }

    // o utilizador pode colar só o código ou o texto todo da página
    public static function extrairCodigo(string $texto): ?string
    {
        if (preg_match('/[0-9a-f]{32}/', $texto, $partes)) {
            return $partes[0];
        }

        return null;
    }

    private static function pedirToken(array $campos): ?array
    {
        $curl = curl_init('https://account-public-service-prod03.ol.epicgames.com/account/api/oauth/token');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_USERPWD, self::CLIENTE_ID . ':' . self::CLIENTE_SEGREDO);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($campos + ['token_type' => 'eg1']));

        $resposta = curl_exec($curl);

        return $resposta ? json_decode($resposta, true) : null;
    }

    private static function pedir(string $url, string $acesso): ?array
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: bearer ' . $acesso]);

        $resposta = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        return json_decode($resposta, true);
    }

    // troca o código do login por um acesso à conta, liga-a ao utilizador e vai logo buscar os jogos
    // o acesso não fica guardado, para atualizar outra vez é preciso fazer o login de novo
    public static function ligar(int $utilizadorId, string $texto): array
    {
        $codigo = self::extrairCodigo($texto);

        if (!$codigo) {
            return ['sucesso' => false, 'erro' => 'Não foi encontrado nenhum código. Cole o texto apresentado pela Epic depois do login.'];
        }

        $resposta = self::pedirToken(['grant_type' => 'authorization_code', 'code' => $codigo]);

        if ($resposta === null) {
            return ['sucesso' => false, 'erro' => 'Não foi possível comunicar com a Epic. Tente novamente mais tarde.'];
        }

        if (!isset($resposta['access_token'])) {
            return ['sucesso' => false, 'erro' => 'O código não é válido ou já expirou. Faça o login novamente e cole o código novo.'];
        }

        $nome = $resposta['displayName'] ?? $resposta['account_id'];

        // se já houver uma conta epic ligada é substituída por esta
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM Contas_Ligadas WHERE utilizador_id = :id AND servico = :servico');
        $stmt->execute(['id' => $utilizadorId, 'servico' => 'epic']);
        $contaId = $stmt->fetchColumn();

        if ($contaId) {
            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET identificador = :nome, id_externo = :conta, nome_publico = :nome2 WHERE id = :id');
            $stmt->execute(['nome' => $nome, 'conta' => $resposta['account_id'], 'nome2' => $nome, 'id' => $contaId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO Contas_Ligadas (utilizador_id, servico, identificador, id_externo, nome_publico)
                VALUES (:utilizador, :servico, :nome, :conta, :nome2)');
            $stmt->execute(['utilizador' => $utilizadorId, 'servico' => 'epic', 'nome' => $nome, 'conta' => $resposta['account_id'], 'nome2' => $nome]);
            $contaId = $pdo->lastInsertId();
        }

        return self::atualizar((int) $contaId, $resposta);
    }

    // a biblioteca vem às páginas, cada uma diz onde começa a seguinte
    private static function biblioteca(string $acesso): ?array
    {
        $itens = [];
        $cursor = null;

        do {
            $url = 'https://library-service.live.use1a.on.epicgames.com/library/api/public/items?includeMetadata=true';
            if ($cursor) {
                $url .= '&cursor=' . urlencode($cursor);
            }

            $dados = self::pedir($url, $acesso);
            if ($dados === null) {
                return null;
            }

            $itens = array_merge($itens, $dados['records'] ?? []);
            $cursor = $dados['responseMetadata']['nextCursor'] ?? null;
        } while ($cursor);

        return $itens;
    }

    // a epic dá o tempo em segundos, por cada jogo (o artifactId é o mesmo que o appName da biblioteca)
    private static function minutosJogados(string $contaId, string $acesso): array
    {
        $dados = self::pedir('https://library-service.live.use1a.on.epicgames.com/library/api/public/playtime/account/' . $contaId . '/all', $acesso);

        $minutos = [];
        foreach ($dados ?? [] as $jogo) {
            $minutos[$jogo['artifactId']] = intdiv((int) $jogo['totalTime'], 60);
        }

        return $minutos;
    }

    // a loja da epic não tem api pública de preços, mas a cheapshark acompanha a loja da epic (loja 25)
    // usa o preço normal (sem promoção), como na steam. 0 é grátis, null é porque a cheapshark não tem o jogo
    private static function preco(string $nome): ?float
    {
        $dados = Api::pedir('https://www.cheapshark.com/api/1.0/deals?storeID=25&exact=1&title=' . urlencode($nome));

        if (!isset($dados[0]['normalPrice'])) {
            return null;
        }

        $dolares = (float) $dados[0]['normalPrice'];

        if ($dolares <= 0) {
            return 0;
        }

        // a cheapshark dá o preço em dólares, os valores são guardados em euros
        $conversao = Catalogo::conversaoDolar('EUR');
        return $conversao['moeda'] === 'EUR' ? round($dolares * $conversao['fator'], 2) : null;
    }

    private static function atualizar(int $contaId, array $resposta): array
    {
        // com muitos jogos pode demorar, cada jogo é um pedido ao catálogo e outro à cheapshark
        set_time_limit(300);
        $acesso = $resposta['access_token'];

        $itens = self::biblioteca($acesso);
        if ($itens === null) {
            return ['sucesso' => false, 'erro' => 'Não foi possível ler a biblioteca da Epic. Tente novamente mais tarde.'];
        }

        $minutos = self::minutosJogados($resposta['account_id'], $acesso);
        self::progresso('Perfil: ' . ($resposta['displayName'] ?? $resposta['account_id']));

        $minutosTotal = 0;
        $valorJogos = 0;
        $linhas = [];
        $vistos = [];

        foreach ($itens as $item) {
            // os ficheiros do unreal engine também aparecem na biblioteca mas não são jogos
            if (($item['namespace'] ?? '') === 'ue' || isset($vistos[$item['catalogItemId']])) {
                continue;
            }
            $vistos[$item['catalogItemId']] = true;

            $dados = self::pedir('https://catalog-public-service-prod06.ol.epicgames.com/catalog/api/shared/namespace/'
                . $item['namespace'] . '/bulk/items?id=' . $item['catalogItemId'] . '&country=PT&locale=pt-PT', $acesso);
            $produto = $dados[$item['catalogItemId']] ?? null;

            // os dlc têm o jogo principal associado, só interessam os jogos
            if (!$produto || isset($produto['mainGameItem'])) {
                continue;
            }

            $jogoBd = Jogo::daEpic($item['catalogItemId'], $produto);
            $preco = self::preco($produto['title']);
            $minutosJogo = $minutos[$item['appName']] ?? null;

            $minutosTotal += $minutosJogo ?? 0;
            $valorJogos += $preco ?? 0;

            $linhas[] = [
                'jogo' => $jogoBd['id'] ?? null,
                'id' => $item['appName'],
                'nome' => $produto['title'],
                'imagem' => $jogoBd['imagem_capa'] ?? null,
                'minutos' => $minutosJogo,
                'valor' => $preco,
            ];

            self::progresso('[' . count($linhas) . '] ' . $produto['title'] . ($preco ? ' = ' . number_format($preco, 2, ',', '') . ' €' : ''));
        }

        self::progresso(count($linhas) . ' jogos');

        $pdo = Database::religar();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
            $stmt->execute(['conta' => $contaId]);

            $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
                conquistas, conquistas_total, valor, ultima_sincronizacao)
                VALUES (:conta, :jogo, :id, :nome, :imagem, :minutos, 0, 0, 0, :valor, NOW())');

            foreach ($linhas as $linha) {
                $linha['conta'] = $contaId;
                $stmt->execute($linha);
            }

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET nivel = NULL, num_jogos = :jogos, minutos_jogados = :minutos,
                num_conquistas = 0, valor_jogos = :valor_jogos, valor_inventario = 0, ultima_sincronizacao = NOW() WHERE id = :id');

            $stmt->execute([
                'jogos' => count($linhas),
                'minutos' => $minutosTotal,
                'valor_jogos' => $valorJogos,
                'id' => $contaId,
            ]);

            $pdo->commit();
        } catch (PDOException $erro) {
            // se alguma coisa falhar, desfaz tudo e a conta fica como estava
            $pdo->rollBack();
            error_log($erro->getMessage());
            return ['sucesso' => false, 'erro' => 'Não foi possível guardar os dados da Epic. Tente novamente mais tarde.'];
        }

        return ['sucesso' => true, 'jogos' => count($linhas)];
    }
}
