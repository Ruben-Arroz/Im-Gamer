<?php

// procura de jogadores pelo nick, pelos jogos que têm e pelas horas
class Jogadores
{
    const HORAS = [
        '' => 'Qualquer número de horas',
        '10' => '10 h ou mais',
        '100' => '100 h ou mais',
        '500' => '500 h ou mais',
        '1000' => '1000 h ou mais',
    ];

    const ORDENS = [
        'horas' => 'Mais horas',
        'jogos' => 'Mais jogos',
        'nome' => 'Nome',
    ];

    const TOTAIS = '(SELECT utilizador_id, SUM(minutos_jogados) AS minutos, SUM(num_jogos) AS jogos,
        GROUP_CONCAT(servico ORDER BY servico) AS servicos
        FROM Contas_Ligadas GROUP BY utilizador_id)';

    // horas de cada um nesse jogo, somando as contas todas
    const NO_JOGO = '(SELECT c.utilizador_id, SUM(COALESCE(b.minutos_jogados, 0)) AS minutos
        FROM Biblioteca_Jogos b JOIN Contas_Ligadas c ON c.id = b.conta_id
        WHERE b.nome LIKE :jogo GROUP BY c.utilizador_id)';

    public static function pesquisar(array $filtros, int $utilizadorId, int $pagina, int $porPagina): array
    {
        $condicoes = ['u.id != :eu'];
        $parametros = ['eu' => $utilizadorId];

        $de = ' FROM Utilizadores u
            LEFT JOIN Plataformas p ON p.id = u.plataforma_preferida_id
            LEFT JOIN ' . self::TOTAIS . ' t ON t.utilizador_id = u.id';

        // com um jogo escolhido só aparecem os que o têm, por isso aqui é JOIN e não LEFT JOIN
        if ($filtros['jogo'] !== '') {
            $de .= ' JOIN ' . self::NO_JOGO . ' nj ON nj.utilizador_id = u.id';
            $parametros['jogo'] = '%' . $filtros['jogo'] . '%';
            $minutosJogo = 'nj.minutos';
        } else {
            $minutosJogo = 'NULL';
        }

        if ($filtros['nome'] !== '') {
            $condicoes[] = 'u.nome_utilizador LIKE :nome';
            $parametros['nome'] = '%' . $filtros['nome'] . '%';
        }

        if ($filtros['horas'] !== '') {
            $condicoes[] = 'COALESCE(t.minutos, 0) >= :minutos';
            $parametros['minutos'] = (int) $filtros['horas'] * 60;
        }

        if ($filtros['servico'] !== '') {
            $condicoes[] = 'EXISTS (SELECT 1 FROM Contas_Ligadas cs WHERE cs.utilizador_id = u.id AND cs.servico = :servico)';
            $parametros['servico'] = $filtros['servico'];
        }

        if ($filtros['plataforma']) {
            $condicoes[] = 'u.plataforma_preferida_id = :plataforma';
            $parametros['plataforma'] = $filtros['plataforma'];
        }

        // se escolheu um jogo, o "mais horas" é pelas horas nesse jogo
        if ($filtros['ordem'] === 'jogos') {
            $ordem = 'COALESCE(t.jogos, 0) DESC';
        } elseif ($filtros['ordem'] === 'nome') {
            $ordem = 'u.nome_utilizador';
        } elseif ($filtros['jogo'] !== '') {
            $ordem = 'nj.minutos DESC';
        } else {
            $ordem = 'COALESCE(t.minutos, 0) DESC';
        }

        $where = ' WHERE ' . implode(' AND ', $condicoes);
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT COUNT(*)' . $de . $where);
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare('SELECT u.id, u.nome_utilizador, u.foto_perfil, p.nome AS plataforma,
            COALESCE(t.minutos, 0) AS minutos, COALESCE(t.jogos, 0) AS jogos, t.servicos, ' . $minutosJogo . ' AS minutos_jogo'
            . $de . $where . ' ORDER BY ' . $ordem . ', u.nome_utilizador LIMIT :limite OFFSET :inicio');
        foreach ($parametros as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('inicio', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['jogadores' => $stmt->fetchAll(), 'total' => $total];
    }

    // os jogos que mais gente tem, para as sugestões do campo do jogo
    public static function jogosNasBibliotecas(): array
    {
        $pdo = Database::ligar();
        return $pdo->query('SELECT nome FROM Biblioteca_Jogos GROUP BY nome ORDER BY COUNT(*) DESC, nome LIMIT 200')
            ->fetchAll(PDO::FETCH_COLUMN);
    }
}
