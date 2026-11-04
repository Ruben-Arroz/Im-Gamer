<?php

// a playstation não tem api oficial para jogadores, por isso usa-se a mesma que a app da playstation usa (não oficial)
// o utilizador entra na conta no browser e cola o código npsso no site, que vai logo buscar os jogos (nada fica guardado)
class Playstation
{
    // dados públicos da app da playstation, são os mesmos que as bibliotecas não oficiais usam
    const CLIENTE_ID = '09515159-7237-4370-9b40-3806e67c0891';
    const CLIENTE_SEGREDO = 'ucPjka5tntB2KqsP';
    const REDIRECIONAR = 'com.scee.psxandroid.scecompcall://redirect';

    // o npsso é uma chave da conta, por isso só se mostram os passos e nunca o valor
    const LINK_NPSSO = 'https://ca.account.sony.com/api/v1/ssocookie';

    // o utilizador pode colar só o código (64 letras e números) ou o texto todo {"npsso":"..."}
    public static function extrairNpsso(string $texto): ?string
    {
        if (preg_match('/[A-Za-z0-9]{64}/', $texto, $partes)) {
            return $partes[0];
        }

        return null;
    }

    private static function pedir(string $url, string $acesso): ?array
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Authorization: Bearer ' . $acesso, 'Accept-Language: pt-PT']);

        $resposta = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        return json_decode($resposta, true);
    }

    // primeiro o npsso troca-se por um código (a sony responde com um redirecionamento), depois o código por um acesso
    private static function pedirAcesso(string $npsso): ?string
    {
        $curl = curl_init('https://ca.account.sony.com/api/authz/v3/oauth/authorize?' . http_build_query([
            'access_type' => 'offline',
            'client_id' => self::CLIENTE_ID,
            'response_type' => 'code',
            'scope' => 'psn:mobile.v2.core psn:clientapp',
            'redirect_uri' => self::REDIRECIONAR,
        ]));
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HEADER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_HTTPHEADER, ['Cookie: npsso=' . $npsso]);
        $resposta = curl_exec($curl);

        // o código vem no endereço do redirecionamento
        if ($resposta === false || !preg_match('/[?&]code=([A-Za-z0-9._-]+)/', $resposta, $partes)) {
            return null;
        }

        $curl = curl_init('https://ca.account.sony.com/api/authz/v3/oauth/token');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        curl_setopt($curl, CURLOPT_USERPWD, self::CLIENTE_ID . ':' . self::CLIENTE_SEGREDO);
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
            'code' => $partes[1],
            'redirect_uri' => self::REDIRECIONAR,
            'grant_type' => 'authorization_code',
            'token_format' => 'jwt',
        ]));
        $resposta = json_decode((string) curl_exec($curl), true);

        return $resposta['access_token'] ?? null;
    }

    // a sony dá o tempo de jogo como PT12H34M56S
    private static function minutos(?string $duracao): ?int
    {
        if (!$duracao || !preg_match('/^PT(?:(\d+)H)?(?:(\d+)M)?(?:(\d+)S)?$/', $duracao, $p)) {
            return null;
        }

        return ((int) ($p[1] ?? 0)) * 60 + (int) ($p[2] ?? 0);
    }

    // para juntar os jogos com os troféus pelo nome (sem ligar a maiúsculas, espaços e símbolos)
    private static function simplificar(string $nome): string
    {
        return preg_replace('/[^a-z0-9]/', '', strtolower($nome));
    }

    // jogos jogados com as horas, de 200 em 200
    private static function jogos(string $acesso): ?array
    {
        $jogos = [];

        for ($inicio = 0; $inicio < 2000; $inicio += 200) {
            $dados = self::pedir('https://m.np.playstation.com/api/gamelist/v2/users/me/titles?categories=ps4_game,ps5_native_game&limit=200&offset=' . $inicio, $acesso);

            if ($dados === null) {
                return $inicio === 0 ? null : $jogos;
            }

            $jogos = array_merge($jogos, $dados['titles'] ?? []);

            if (count($dados['titles'] ?? []) < 200) {
                break;
            }
        }

        return $jogos;
    }

    // jogos com troféus (inclui os mais antigos, como os da ps3)
    private static function trofeus(string $acesso): array
    {
        $titulos = [];

        for ($inicio = 0; $inicio < 2000; $inicio += 800) {
            $dados = self::pedir('https://m.np.playstation.com/api/trophy/v1/users/me/trophyTitles?limit=800&offset=' . $inicio, $acesso);

            if ($dados === null) {
                break;
            }

            $titulos = array_merge($titulos, $dados['trophyTitles'] ?? []);

            if (count($dados['trophyTitles'] ?? []) < 800) {
                break;
            }
        }

        return $titulos;
    }

    // o nome da conta vem do perfil, se a sony não o der fica um nome genérico
    private static function nomeDaConta(string $acesso): string
    {
        $dados = self::pedir('https://us-prof.np.community.playstation.net/userProfile/v1/users/me/profile2?fields=onlineId', $acesso);

        return $dados['profile']['onlineId'] ?? 'Conta PlayStation';
    }

    // troca o npsso por um acesso, liga a conta ao utilizador e vai logo buscar os jogos
    public static function ligar(int $utilizadorId, string $texto): array
    {
        $npsso = self::extrairNpsso($texto);

        if (!$npsso) {
            return ['sucesso' => false, 'erro' => 'Não foi encontrado nenhum código. Cole o texto apresentado pela PlayStation depois do login.'];
        }

        $acesso = self::pedirAcesso($npsso);

        if (!$acesso) {
            return ['sucesso' => false, 'erro' => 'O código não é válido ou já expirou. Faça o login novamente e copie o código outra vez.'];
        }

        // com muitos jogos pode demorar
        set_time_limit(300);

        $jogos = self::jogos($acesso);
        if ($jogos === null) {
            return ['sucesso' => false, 'erro' => 'Não foi possível ler a biblioteca da PlayStation. Tente novamente mais tarde.'];
        }

        $nome = self::nomeDaConta($acesso);
        $trofeus = self::trofeus($acesso);

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM Contas_Ligadas WHERE utilizador_id = :id AND servico = :servico');
        $stmt->execute(['id' => $utilizadorId, 'servico' => 'playstation']);
        $contaId = $stmt->fetchColumn();

        if ($contaId) {
            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET identificador = :nome, nome_publico = :nome2 WHERE id = :id');
            $stmt->execute(['nome' => $nome, 'nome2' => $nome, 'id' => $contaId]);
        } else {
            $stmt = $pdo->prepare('INSERT INTO Contas_Ligadas (utilizador_id, servico, identificador, nome_publico) VALUES (:utilizador, :servico, :nome, :nome2)');
            $stmt->execute(['utilizador' => $utilizadorId, 'servico' => 'playstation', 'nome' => $nome, 'nome2' => $nome]);
            $contaId = $pdo->lastInsertId();
        }

        return self::guardar((int) $contaId, $jogos, $trofeus);
    }

    private static function guardar(int $contaId, array $jogos, array $trofeus): array
    {
        $linhas = [];
        $vistos = [];
        $minutosTotal = 0;
        $conquistasTotal = 0;

        // os troféus por nome do jogo, para juntar aos jogos da lista
        $trofeusPorNome = [];
        foreach ($trofeus as $titulo) {
            $trofeusPorNome[self::simplificar($titulo['trophyTitleName'])] = $titulo;
        }

        foreach ($jogos as $jogo) {
            $chave = self::simplificar($jogo['name']);
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;

            $titulo = $trofeusPorNome[$chave] ?? null;
            $linhas[] = self::linha($jogo['name'], $jogo['imageUrl'] ?? null, self::minutos($jogo['playDuration'] ?? null), $jogo['lastPlayedDateTime'] ?? null, $titulo, $jogo['titleId'] ?? $chave);
        }

        // jogos que só aparecem nos troféus (por exemplo ps3 e vita)
        foreach ($trofeus as $titulo) {
            $chave = self::simplificar($titulo['trophyTitleName']);
            if (isset($vistos[$chave])) {
                continue;
            }
            $vistos[$chave] = true;

            $linhas[] = self::linha($titulo['trophyTitleName'], $titulo['trophyTitleIconUrl'] ?? null, null, $titulo['lastUpdatedDateTime'] ?? null, $titulo, $titulo['npCommunicationId'] ?? $chave);
        }

        foreach ($linhas as $linha) {
            $minutosTotal += $linha['minutos'] ?? 0;
            $conquistasTotal += $linha['conquistas'];
        }

        $pdo = Database::religar();
        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE conta_id = :conta');
            $stmt->execute(['conta' => $contaId]);

            $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, minutos_2_semanas,
                ultima_vez_jogado, conquistas, conquistas_total, ultima_sincronizacao)
                VALUES (:conta, :jogo, :id, :nome, :imagem, :minutos, 0, :ultima_vez, :conquistas, :conquistas_total, NOW())');

            foreach ($linhas as $linha) {
                $stmt->execute([
                    'conta' => $contaId,
                    // o jogo fica na tabela Jogos para ter página própria (pelo nome, se já existir usa-se esse)
                    'jogo' => self::jogoDaBd($linha['nome'], $linha['imagem']),
                    'id' => substr($linha['id'], 0, 50),
                    'nome' => mb_substr($linha['nome'], 0, 255),
                    'imagem' => $linha['imagem'],
                    'minutos' => $linha['minutos'],
                    'ultima_vez' => $linha['ultima_vez'],
                    'conquistas' => $linha['conquistas'],
                    'conquistas_total' => $linha['conquistas_total'],
                ]);
            }

            $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET nivel = NULL, num_jogos = :jogos, minutos_jogados = :minutos, num_conquistas = :conquistas,
                valor_jogos = 0, valor_inventario = 0, ultima_sincronizacao = NOW() WHERE id = :id');
            $stmt->execute(['jogos' => count($linhas), 'minutos' => $minutosTotal, 'conquistas' => $conquistasTotal, 'id' => $contaId]);

            $pdo->commit();
        } catch (PDOException $erro) {
            // se alguma coisa falhar, desfaz tudo e a conta fica como estava
            $pdo->rollBack();
            error_log($erro->getMessage());
            return ['sucesso' => false, 'erro' => 'Não foi possível guardar os dados da PlayStation. Tente novamente mais tarde.'];
        }

        return ['sucesso' => true, 'jogos' => count($linhas)];
    }

    // uma linha da biblioteca, já com os troféus (conquistas) se o jogo os tiver
    private static function linha(string $nome, ?string $imagem, ?int $minutos, ?string $ultimaVez, ?array $titulo, string $id): array
    {
        $feitos = 0;
        $total = 0;

        if ($titulo) {
            $feitos = array_sum($titulo['earnedTrophies'] ?? []);
            $total = array_sum($titulo['definedTrophies'] ?? []);
        }

        if ($imagem && strlen($imagem) > 255) {
            $imagem = null;
        }

        return [
            'id' => $id,
            'nome' => $nome,
            'imagem' => $imagem,
            'minutos' => $minutos,
            'ultima_vez' => $ultimaVez ? (new DateTime($ultimaVez))->setTimezone(new DateTimeZone(date_default_timezone_get()))->format('Y-m-d H:i:s') : null,
            'conquistas' => $feitos,
            'conquistas_total' => max($feitos, $total),
        ];
    }

    private static function jogoDaBd(string $nome, ?string $imagem): int
    {
        return Jogo::procurar(0, '', 0, $nome) ?? Jogo::guardarSimples(mb_substr($nome, 0, 255), $imagem, null);
    }
}
