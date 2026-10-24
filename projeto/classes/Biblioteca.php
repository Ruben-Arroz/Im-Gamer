<?php

class Biblioteca
{
    // intervalos do filtro de horas (os minutos é que estão guardados na bd)
    const HORAS = [
        'nunca' => ['Nunca jogado', 'b.minutos_jogados = 0'],
        'menos1' => ['Menos de 1 h', 'b.minutos_jogados > 0 AND b.minutos_jogados < 60'],
        '1a10' => ['1 a 10 h', 'b.minutos_jogados BETWEEN 60 AND 599'],
        '10a50' => ['10 a 50 h', 'b.minutos_jogados BETWEEN 600 AND 2999'],
        '50a100' => ['50 a 100 h', 'b.minutos_jogados BETWEEN 3000 AND 5999'],
        'mais100' => ['Mais de 100 h', 'b.minutos_jogados >= 6000'],
    ];

    const CONQUISTAS = [
        'completas' => ['Todas desbloqueadas', 'b.conquistas_total > 0 AND b.conquistas = b.conquistas_total'],
        'por_fazer' => ['Com conquistas por fazer', 'b.conquistas < b.conquistas_total'],
        'sem' => ['Sem conquistas', 'b.conquistas_total = 0'],
    ];

    const MODOS = [
        'um_jogador' => 'Um jogador',
        'multijogador' => 'Multijogador',
        'cooperativo' => 'Cooperativo',
    ];

    const ORDENS = [
        'horas' => ['Mais jogados', 'b.minutos_jogados DESC'],
        'nome' => ['Nome', 'b.nome ASC'],
        'preco' => ['Mais caros', 'b.valor DESC'],
        'ultima_vez' => ['Jogados há menos tempo', 'b.ultima_vez_jogado DESC'],
        'lancamento' => ['Mais recentes', 'j.data_lancamento DESC'],
        'metacritic' => ['Nota Metacritic', 'j.nota_metacritic DESC'],
    ];

    // monta a pesquisa com os filtros escolhidos, cada filtro junta uma condição ao WHERE
    public static function pesquisar(int $utilizadorId, array $filtros, int $pagina, int $porPagina): array
    {
        $condicoes = ['c.utilizador_id = :utilizador'];
        $parametros = ['utilizador' => $utilizadorId];

        if ($filtros['nome'] !== '') {
            $condicoes[] = 'b.nome LIKE :nome';
            $parametros['nome'] = '%' . $filtros['nome'] . '%';
        }

        // para os IN é preciso um parâmetro para cada valor (:servico0, :servico1, ...)
        if ($filtros['servicos']) {
            $nomes = [];
            foreach ($filtros['servicos'] as $i => $servico) {
                $nomes[] = ':servico' . $i;
                $parametros['servico' . $i] = $servico;
            }
            $condicoes[] = 'c.servico IN (' . implode(', ', $nomes) . ')';
        }

        if ($filtros['generos']) {
            $nomes = [];
            foreach ($filtros['generos'] as $i => $genero) {
                $nomes[] = ':genero' . $i;
                $parametros['genero' . $i] = $genero;
            }
            $condicoes[] = 'b.jogo_id IN (SELECT jogo_id FROM Jogos_Generos WHERE genero_id IN (' . implode(', ', $nomes) . '))';
        }

        // os nomes dos modos vêm da lista MODOS, por isso podem ir diretos para o sql
        foreach ($filtros['modos'] as $modo) {
            $condicoes[] = 'j.modo_' . $modo . ' = 1';
        }

        if ($filtros['horas'] !== '') {
            $condicoes[] = self::HORAS[$filtros['horas']][1];
        }

        if ($filtros['conquistas'] !== '') {
            $condicoes[] = self::CONQUISTAS[$filtros['conquistas']][1];
        }

        if ($filtros['recentes']) {
            $condicoes[] = 'b.minutos_2_semanas > 0';
        }

        if ($filtros['preco_min'] !== null) {
            $condicoes[] = 'b.valor >= :preco_min';
            $parametros['preco_min'] = $filtros['preco_min'];
        }

        if ($filtros['preco_max'] !== null) {
            // os jogos sem preço (grátis) também contam como baratos
            $condicoes[] = '(b.valor <= :preco_max OR b.valor IS NULL)';
            $parametros['preco_max'] = $filtros['preco_max'];
        }

        if ($filtros['metacritic'] !== null) {
            $condicoes[] = 'j.nota_metacritic >= :metacritic';
            $parametros['metacritic'] = $filtros['metacritic'];
        }

        if ($filtros['ano_min'] !== null) {
            $condicoes[] = 'YEAR(j.data_lancamento) >= :ano_min';
            $parametros['ano_min'] = $filtros['ano_min'];
        }

        if ($filtros['ano_max'] !== null) {
            $condicoes[] = 'YEAR(j.data_lancamento) <= :ano_max';
            $parametros['ano_max'] = $filtros['ano_max'];
        }

        $de = ' FROM Biblioteca_Jogos b
            JOIN Contas_Ligadas c ON c.id = b.conta_id
            LEFT JOIN Jogos j ON j.id = b.jogo_id
            WHERE ' . implode(' AND ', $condicoes);

        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT COUNT(*)' . $de);
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT b.*, c.servico, j.nota_metacritic, j.data_lancamento' . $de
            . ' ORDER BY ' . self::ORDENS[$filtros['ordem']][1] . ', b.nome ASC LIMIT :limite OFFSET :inicio');

        foreach ($parametros as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('inicio', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['jogos' => $stmt->fetchAll(), 'total' => $total];
    }

    // géneros dos jogos que o utilizador tem, para mostrar no filtro
    public static function generosDoUtilizador(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT g.id, g.nome, COUNT(DISTINCT b.id) AS quantidade FROM Biblioteca_Jogos b
            JOIN Contas_Ligadas c ON c.id = b.conta_id
            JOIN Jogos_Generos jg ON jg.jogo_id = b.jogo_id
            JOIN Generos g ON g.id = jg.genero_id
            WHERE c.utilizador_id = :utilizador
            GROUP BY g.id, g.nome
            ORDER BY g.nome');
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    // link para a página oficial do jogo na loja de cada plataforma
    public static function linkLoja(array $jogo): ?string
    {
        if ($jogo['servico'] === 'steam' && $jogo['id_externo']) {
            return 'https://store.steampowered.com/app/' . $jogo['id_externo'];
        }

        // a xbox não tem endereço direto sem o código do produto, por isso abre a pesquisa da loja
        if ($jogo['servico'] === 'xbox') {
            return 'https://www.xbox.com/pt-PT/search?q=' . urlencode($jogo['nome']);
        }

        return null;
    }

    public static function maisJogados(int $contaId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Biblioteca_Jogos WHERE conta_id = :conta AND minutos_jogados > 0 ORDER BY minutos_jogados DESC LIMIT :limite');
        $stmt->bindValue('conta', $contaId, PDO::PARAM_INT);
        // o LIMIT tem de ir como número, se for como texto o mysql dá erro
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // para as contas que não dão horas (xbox), mostra os jogos pela última vez que foram jogados
    public static function porUltimaVez(int $contaId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Biblioteca_Jogos WHERE conta_id = :conta ORDER BY ultima_vez_jogado DESC LIMIT :limite');
        $stmt->bindValue('conta', $contaId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function jogadosRecentemente(int $contaId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Biblioteca_Jogos WHERE conta_id = :conta AND minutos_2_semanas > 0 ORDER BY minutos_2_semanas DESC LIMIT :limite');
        $stmt->bindValue('conta', $contaId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // mais jogados juntando todas as contas do utilizador
    public static function maisJogadosDeTodas(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT b.*, c.servico FROM Biblioteca_Jogos b
            JOIN Contas_Ligadas c ON c.id = b.conta_id
            WHERE c.utilizador_id = :utilizador AND b.minutos_jogados > 0
            ORDER BY b.minutos_jogados DESC LIMIT :limite');
        $stmt->bindValue('utilizador', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function inventario(int $contaId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Inventario_Itens WHERE conta_id = :conta ORDER BY preco * quantidade DESC LIMIT :limite');
        $stmt->bindValue('conta', $contaId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }
}
