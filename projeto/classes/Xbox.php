<?php

// a xbox não tem api pública, por isso usa-se o openxbl (xbl.io), que é um serviço não oficial
// não dá horas jogadas nem preços, só os jogos, as conquistas e a última vez que se jogou
class Xbox
{
    private static function pedir(string $caminho): ?array
    {
        $curl = curl_init('https://xbl.io/api/v2/' . $caminho);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, [
            'X-Authorization: ' . OPENXBL_API_KEY,
            'Accept: application/json',
            'Accept-Language: pt-PT',
        ]);

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

        $conquistasTotal = 0;
        foreach ($jogos as $jogo) {
            $conquistasTotal += $jogo['achievement']['currentAchievements'];
            self::progresso($jogo['name'] . ': ' . $jogo['achievement']['currentAchievements'] . ' conquistas');
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

            $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
                ultima_vez_jogado, conquistas, conquistas_total, ultima_sincronizacao)
                VALUES (:conta, :titulo, :nome, :imagem, 0, 0, :ultima_vez, :conquistas, :conquistas_total, NOW())');

            foreach ($jogos as $jogo) {
                // a imagem vem em http e o site é https
                $imagem = str_replace('http://', 'https://', $jogo['displayImage'] ?? '');
                if ($imagem === '' || strlen($imagem) > 255) {
                    $imagem = null;
                }

                $feitas = $jogo['achievement']['currentAchievements'];

                $stmt->execute([
                    'conta' => $conta['id'],
                    'titulo' => $jogo['titleId'],
                    'nome' => $jogo['name'],
                    'imagem' => $imagem,
                    'ultima_vez' => self::converterData($jogo['titleHistory']['lastTimePlayed'] ?? ''),
                    'conquistas' => $feitas,
                    // há jogos em que a xbox não diz o total, por isso nunca fica menor que as já feitas
                    'conquistas_total' => max($feitas, $jogo['achievement']['totalAchievements']),
                ]);
            }

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET id_externo = :xuid, nome_publico = :nome, avatar = :avatar, nivel = NULL,
                num_jogos = :jogos, minutos_jogados = 0, num_conquistas = :conquistas, valor_jogos = 0, valor_inventario = 0,
                ultima_sincronizacao = NOW() WHERE id = :id');

            $stmt->execute([
                'xuid' => $xuid,
                'nome' => $perfil['gamertag'],
                'avatar' => $avatar,
                'jogos' => count($jogos),
                'conquistas' => $conquistasTotal,
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
