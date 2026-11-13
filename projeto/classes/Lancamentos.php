<?php

// plano B para quando a rawg não responde: listas de lançamentos da loja da steam (não precisam de chave)
// o sincronizar.php guarda-as na bd e o site mostra a última que ficou guardada
class Lancamentos
{
    // filtros da pesquisa da loja: os lançamentos mais populares e os que saem em breve
    const LISTAS = [
        'recentes' => 'popularnew',
        'breve' => 'popularcomingsoon',
    ];

    const QUANTOS = 40;

    // nos populares a steam também mete jogos antigos que ainda são muito jogados (dota 2, warframe, ...)
    const DIAS_RECENTES = 90;

    public static function sincronizar(): array
    {
        $resultado = [];

        foreach (self::LISTAS as $tipo => $filtro) {
            $dados = Api::pedir('https://store.steampowered.com/search/results/?filter=' . $filtro . '&json=1&count=' . self::QUANTOS . '&category1=998');

            if (!$dados) {
                $resultado[$tipo] = null;
                continue;
            }

            $ids = [];
            foreach ($dados['items'] ?? [] as $item) {
                // a lista só dá o nome e o logo, o número do jogo na steam vem no endereço do logo
                if (!preg_match('#/apps/(\d+)/#', $item['logo'] ?? '', $partes)) {
                    continue;
                }

                // fica guardado na tabela Jogos com a descrição, a data, os géneros e as capturas
                $jogo = Jogo::daSteam((int) $partes[1]);

                if ($jogo && !in_array($jogo['id'], $ids) && self::serve($tipo, $jogo['id'])) {
                    $ids[] = $jogo['id'];
                }
            }

            self::guardar($tipo, $ids);
            $resultado[$tipo] = count($ids);
        }

        return $resultado;
    }

    private static function serve(string $tipo, int $jogoId): bool
    {
        $data = Jogo::buscarPorId($jogoId)['data_lancamento'] ?? null;
        $hoje = date('Y-m-d');

        if ($tipo === 'breve') {
            // os que ainda não têm dia certo (por exemplo "November 2026") também contam
            return $data === null || $data > $hoje;
        }

        return $data !== null && $data <= $hoje && $data >= date('Y-m-d', strtotime('-' . self::DIAS_RECENTES . ' days'));
    }

    // troca a lista antiga pela nova. se a nova vier vazia fica a antiga
    private static function guardar(string $tipo, array $ids): void
    {
        if (!$ids) {
            return;
        }

        $pdo = Database::ligar();
        $pdo->beginTransaction();

        $stmt = $pdo->prepare('DELETE FROM Lancamentos WHERE tipo = :tipo');
        $stmt->execute(['tipo' => $tipo]);

        $stmt = $pdo->prepare('INSERT INTO Lancamentos (jogo_id, tipo, posicao) VALUES (:jogo, :tipo, :posicao)');
        foreach ($ids as $posicao => $id) {
            $stmt->execute(['jogo' => $id, 'tipo' => $tipo, 'posicao' => $posicao]);
        }

        $pdo->commit();
    }

    // pela ordem da steam (os mais populares primeiro). os "em breve" que entretanto saíram ficam de fora
    public static function listar(string $tipo, int $limite): array
    {
        $condicao = $tipo === 'breve' ? '(j.data_lancamento IS NULL OR j.data_lancamento > :hoje)' : 'j.data_lancamento <= :hoje';

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT j.id, j.nome, j.imagem_capa, j.data_lancamento, j.steam_app_id, l.data_sincronizacao FROM Lancamentos l
            JOIN Jogos j ON j.id = l.jogo_id
            WHERE l.tipo = :tipo AND ' . $condicao . '
            ORDER BY l.posicao LIMIT :limite');
        $stmt->bindValue('tipo', $tipo);
        $stmt->bindValue('hoje', date('Y-m-d'));
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
