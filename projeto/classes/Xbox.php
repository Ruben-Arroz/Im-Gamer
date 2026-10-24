<?php

// a xbox não tem api pública, por isso usa-se o openxbl (xbl.io), que é um serviço não oficial
// dá o perfil, os jogos, as conquistas, o gamerscore e as horas (nem sempre)
// as imagens, os detalhes e os preços vêm do catálogo da loja da microsoft, que é público
class Xbox
{
    private static function pedir(string $caminho, ?array $corpo = null): ?array
    {
        $curl = curl_init('https://xbl.io/api/v2/' . $caminho);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'X-Authorization: ' . OPENXBL_API_KEY,
            'Accept: application/json',
            'Accept-Language: pt-PT',
            'Content-Type: application/json',
        ]);

        // alguns pedidos são POST com um json no corpo
        if ($corpo !== null) {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($corpo));
        }

        $resposta = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        return json_decode($resposta, true);
    }

    private static function progresso(string $texto): void
    {
        if (php_sapi_name() === 'cli') {
            echo '  ' . $texto . "\n";
        }
    }

    // aceita o xuid (número de 16 dígitos), o link do perfil ou só o gamertag
    public static function descobrirXuid(string $identificador): ?string
    {
        if (preg_match('/^\d{16}$/', $identificador)) {
            return $identificador;
        }

        // nos links do site da xbox o gamertag vem no fim: xbox.com/pt-PT/play/user/Nome
        if (preg_match('/\/user\/([^\/?#]+)/', $identificador, $partes)) {
            $identificador = urldecode($partes[1]);
        }

        $dados = self::pedir('search/' . rawurlencode($identificador));
        $pessoas = $dados['content']['people'] ?? [];

        // se houver vários resultados fica o que tem o gamertag igual
        foreach ($pessoas as $pessoa) {
            if (strtolower($pessoa['gamertag'] ?? '') === strtolower($identificador)) {
                return $pessoa['xuid'];
            }
        }

        return $pessoas[0]['xuid'] ?? null;
    }

    // minutos jogados de cada jogo, num só pedido. os jogos que não guardam esta estatística não vêm
    private static function minutosJogados(string $xuid, array $jogos): array
    {
        $estatisticas = [];
        foreach ($jogos as $jogo) {
            $estatisticas[] = ['name' => 'MinutesPlayed', 'titleId' => $jogo['titleId']];
        }

        $dados = self::pedir('player/stats', ['xuids' => [$xuid], 'stats' => $estatisticas]);

        $minutos = [];
        foreach ($dados['content']['statlistscollection'][0]['stats'] ?? [] as $estatistica) {
            if (isset($estatistica['value'])) {
                $minutos[$estatistica['titleid']] = (int) $estatistica['value'];
            }
        }

        return $minutos;
    }

    // vai à loja da microsoft pelo id do jogo na xbox, devolve o produto ou null se não estiver à venda
    public static function produtoDaLoja(string $titleId): ?array
    {
        $dados = Api::pedir('https://displaycatalog.mp.microsoft.com/v7.0/products/lookup?alternateId=XboxTitleId&value='
            . urlencode($titleId) . '&market=PT&languages=pt-pt&fieldsTemplate=details');

        return $dados['Products'][0] ?? null;
    }

    // preço atual na loja portuguesa, em euros (0 se for grátis, null se ainda não estiver à venda)
    public static function precoDaLoja(array $produto): ?float
    {
        $gratis = false;

        foreach ($produto['DisplaySkuAvailabilities'] ?? [] as $sku) {
            foreach ($sku['Availabilities'] ?? [] as $disponivel) {
                if (!in_array('Purchase', $disponivel['Actions'] ?? [])) {
                    continue;
                }

                $preco = $disponivel['OrderManagementData']['Price']['ListPrice'] ?? 0;

                if ($preco > 0) {
                    return (float) $preco;
                }

                // dá para "comprar" a 0 €, mas pode haver outra edição paga, por isso continua a procurar
                $gratis = true;
            }
        }

        return $gratis ? 0 : null;
    }

    public static function sincronizar(array $conta): array
    {
        $xuid = self::descobrirXuid(trim($conta['identificador']));

        if (!$xuid) {
            return ['sucesso' => false, 'erro' => 'Não encontrei esta conta Xbox.'];
        }

        $dados = self::pedir('player/summary/' . $xuid);
        $perfil = $dados['content']['people'][0] ?? null;

        if (!$perfil) {
            return ['sucesso' => false, 'erro' => 'Não consegui ler o perfil da Xbox.'];
        }

        self::progresso('Perfil: ' . $perfil['gamertag'] . ' (gamerscore ' . $perfil['gamerScore'] . ')');

        // se o perfil for privado a lista vem vazia
        $dados = self::pedir('achievements/player/' . $xuid);
        $jogos = $dados['content']['titles'] ?? [];
        self::progresso(count($jogos) . ' jogos');

        $minutos = $jogos ? self::minutosJogados($xuid, $jogos) : [];
        self::progresso(count($minutos) . ' jogos com horas');

        $conquistasTotal = 0;
        $minutosTotal = 0;
        $valorJogos = 0;
        $linhas = [];

        foreach ($jogos as $i => $jogo) {
            $titulo = (string) $jogo['titleId'];
            $produto = self::produtoDaLoja($titulo);
            $jogoBd = $produto ? Jogo::daXbox($titulo, $produto) : null;
            $preco = $produto ? self::precoDaLoja($produto) : null;

            // se a loja não tiver o jogo fica a imagem quadrada que a xbox dá
            $imagem = $jogoBd['imagem_capa'] ?? str_replace('http://', 'https://', $jogo['displayImage'] ?? '');
            if ($imagem === '' || strlen($imagem) > 255) {
                $imagem = null;
            }

            $feitas = $jogo['achievement']['currentAchievements'];
            $conquistasTotal += $feitas;
            $minutosTotal += $minutos[$titulo] ?? 0;
            $valorJogos += $preco ?? 0;

            $linhas[] = [
                'jogo' => $jogoBd['id'] ?? null,
                'titulo' => $titulo,
                'nome' => $jogo['name'],
                'imagem' => $imagem,
                'minutos' => $minutos[$titulo] ?? null,
                'ultima_vez' => self::converterData($jogo['titleHistory']['lastTimePlayed'] ?? ''),
                'conquistas' => $feitas,
                // há jogos em que a xbox não diz o total, por isso nunca fica menor que as já feitas
                'conquistas_total' => max($feitas, $jogo['achievement']['totalAchievements']),
                'pontuacao' => $jogo['achievement']['currentGamerscore'] ?? null,
                'pontuacao_total' => $jogo['achievement']['totalGamerscore'] ?? null,
                'valor' => $preco,
            ];

            self::progresso('[' . ($i + 1) . '/' . count($jogos) . '] ' . $jogo['name']
                . ($preco ? ' = ' . number_format($preco, 2, ',', '') . ' €' : ''));
        }

        // o avatar tem de caber na coluna da bd
        $avatar = $perfil['displayPicRaw'] ?? null;
        if ($avatar && strlen($avatar) > 255) {
            $avatar = null;
        }

        $pdo = Database::religar();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
            $stmt->execute(['conta' => $conta['id']]);

            $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
                ultima_vez_jogado, conquistas, conquistas_total, pontuacao, pontuacao_total, valor, ultima_sincronizacao)
                VALUES (:conta, :jogo, :titulo, :nome, :imagem, :minutos, 0, :ultima_vez, :conquistas, :conquistas_total,
                :pontuacao, :pontuacao_total, :valor, NOW())');

            foreach ($linhas as $linha) {
                $linha['conta'] = $conta['id'];
                $stmt->execute($linha);
            }

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET id_externo = :xuid, nome_publico = :nome, avatar = :avatar, nivel = NULL,
                pontuacao = :pontuacao, num_jogos = :jogos, minutos_jogados = :minutos, num_conquistas = :conquistas,
                valor_jogos = :valor_jogos, valor_inventario = 0, ultima_sincronizacao = NOW() WHERE id = :id');

            $stmt->execute([
                'xuid' => $xuid,
                'nome' => $perfil['gamertag'],
                'avatar' => $avatar,
                'pontuacao' => $perfil['gamerScore'],
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

    // a xbox dá a data como 2026-10-24T13:44:55.04Z e 0001-01-01 quando o jogo nunca foi jogado
    private static function converterData(string $texto): ?string
    {
        if ($texto === '' || str_starts_with($texto, '0001')) {
            return null;
        }

        return (new DateTime($texto))->format('Y-m-d H:i:s');
    }
}
