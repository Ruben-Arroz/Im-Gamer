<?php

// contas para a página de estatísticas, juntando os jogos de todas as contas do utilizador
class Estatisticas
{
    const DE = ' FROM Biblioteca_Jogos b
        JOIN Contas_Ligadas c ON c.id = b.conta_id
        LEFT JOIN Jogos j ON j.id = b.jogo_id
        WHERE c.utilizador_id = :utilizador';

    // os totais todos numa só query (o SUM de uma condição conta quantas vezes ela é verdadeira)
    public static function resumo(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT COUNT(*) AS jogos,
            COALESCE(SUM(b.minutos_jogados), 0) AS minutos,
            COALESCE(SUM(b.minutos_2_semanas), 0) AS minutos_recentes,
            COALESCE(SUM(b.minutos_jogados = 0), 0) AS nunca_jogados,
            COALESCE(SUM(b.minutos_jogados IS NOT NULL), 0) AS com_horas,
            COALESCE(SUM(b.conquistas), 0) AS conquistas,
            COALESCE(SUM(b.conquistas_total), 0) AS conquistas_total,
            COALESCE(SUM(b.conquistas_total > 0), 0) AS com_conquistas,
            COALESCE(SUM(b.conquistas_total > 0 AND b.conquistas >= b.conquistas_total), 0) AS completos,
            COALESCE(SUM(b.valor), 0) AS valor_jogos,
            COALESCE(SUM(b.valor = 0), 0) AS gratis,
            COALESCE(SUM(CASE WHEN b.minutos_jogados = 0 THEN b.valor END), 0) AS valor_nunca_jogados'
            . self::DE);
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetch();
    }

    // géneros com mais jogos, e as horas jogadas em cada um
    public static function generos(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT g.nome, COUNT(DISTINCT b.id) AS jogos, COALESCE(SUM(b.minutos_jogados), 0) AS minutos
            FROM Biblioteca_Jogos b
            JOIN Contas_Ligadas c ON c.id = b.conta_id
            JOIN Jogos_Generos jg ON jg.jogo_id = b.jogo_id
            JOIN Generos g ON g.id = jg.genero_id
            WHERE c.utilizador_id = :utilizador
            GROUP BY g.id, g.nome
            ORDER BY jogos DESC, minutos DESC
            LIMIT :limite');
        $stmt->bindValue('utilizador', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // quantos jogos e quantas horas em cada modo (um jogo pode ter vários modos)
    public static function modos(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT
            COALESCE(SUM(j.modo_um_jogador), 0) AS um_jogador,
            COALESCE(SUM(j.modo_multijogador), 0) AS multijogador,
            COALESCE(SUM(j.modo_cooperativo), 0) AS cooperativo,
            COALESCE(SUM(j.modo_um_jogador * b.minutos_jogados), 0) AS minutos_um_jogador,
            COALESCE(SUM(j.modo_multijogador * b.minutos_jogados), 0) AS minutos_multijogador,
            COALESCE(SUM(j.modo_cooperativo * b.minutos_jogados), 0) AS minutos_cooperativo'
            . self::DE);
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetch();
    }

    // jogos por década de lançamento (1990, 2000, ...)
    public static function decadas(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT FLOOR(YEAR(j.data_lancamento) / 10) * 10 AS decada, COUNT(*) AS jogos'
            . self::DE . ' AND j.data_lancamento IS NOT NULL
            GROUP BY decada
            ORDER BY decada');
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    // produtoras ou editoras com mais jogos. o nome da coluna não pode ir como parâmetro, por isso só aceita estas duas
    public static function empresas(int $utilizadorId, string $coluna, int $limite): array
    {
        if (!in_array($coluna, ['produtora', 'editora'])) {
            return [];
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT j.' . $coluna . ' AS nome, COUNT(*) AS jogos'
            . self::DE . ' AND j.' . $coluna . ' IS NOT NULL
            GROUP BY j.' . $coluna . '
            ORDER BY jogos DESC, nome ASC
            LIMIT :limite');
        $stmt->bindValue('utilizador', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // jogos com conquistas por fazer, os que já estão mais perto dos 100% primeiro
    public static function quaseCompletos(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT b.nome, b.imagem, b.conquistas, b.conquistas_total, c.servico'
            . self::DE . ' AND b.conquistas_total > 0 AND b.conquistas > 0 AND b.conquistas < b.conquistas_total
            ORDER BY b.conquistas / b.conquistas_total DESC
            LIMIT :limite');
        $stmt->bindValue('utilizador', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function maisCaro(int $utilizadorId): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT b.nome, b.valor, c.servico' . self::DE . ' AND b.valor > 0 ORDER BY b.valor DESC LIMIT 1');
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetch() ?: null;
    }
}
