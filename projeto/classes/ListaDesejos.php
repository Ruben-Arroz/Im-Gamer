<?php

class ListaDesejos
{
    public static function tem(int $utilizadorId, int $jogoId): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM Lista_Desejos WHERE utilizador_id = :utilizador AND jogo_id = :jogo');
        $stmt->execute(['utilizador' => $utilizadorId, 'jogo' => $jogoId]);

        return (bool) $stmt->fetch();
    }

    // o INSERT IGNORE não dá erro se o jogo já estiver na lista
    public static function adicionar(int $utilizadorId, int $jogoId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT IGNORE INTO Lista_Desejos (utilizador_id, jogo_id) VALUES (:utilizador, :jogo)');
        $stmt->execute(['utilizador' => $utilizadorId, 'jogo' => $jogoId]);
    }

    public static function remover(int $utilizadorId, int $jogoId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Lista_Desejos WHERE utilizador_id = :utilizador AND jogo_id = :jogo');
        $stmt->execute(['utilizador' => $utilizadorId, 'jogo' => $jogoId]);
    }

    // o preço vem da cheapshark no browser, por isso essa ordem é feita no desejos.js
    const ORDENS = [
        'data' => ['Adicionados há menos tempo', 'd.data_adicionado DESC'],
        'nome' => ['Nome', 'j.nome ASC'],
        'preco' => ['Mais baratos', 'd.data_adicionado DESC'],
    ];

    public static function listar(int $utilizadorId, string $ordem = 'data'): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT j.id, j.nome, j.steam_app_id, j.imagem_capa, d.data_adicionado FROM Lista_Desejos d
            JOIN Jogos j ON j.id = d.jogo_id
            WHERE d.utilizador_id = :utilizador
            ORDER BY ' . self::ORDENS[$ordem][1]);
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetchAll();
    }
}
