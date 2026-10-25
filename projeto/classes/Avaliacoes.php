<?php

// avaliações dos jogos feitas pelos utilizadores do site (1 a 5 estrelas e um comentário opcional)
class Avaliacoes
{
    public static function doJogo(int $jogoId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT a.*, u.nome_utilizador, u.foto_perfil FROM Avaliacoes a
            JOIN Utilizadores u ON u.id = a.utilizador_id
            WHERE a.jogo_id = :jogo
            ORDER BY a.data_criacao DESC');
        $stmt->execute(['jogo' => $jogoId]);

        return $stmt->fetchAll();
    }

    public static function media(int $jogoId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT AVG(pontuacao) AS media, COUNT(*) AS total FROM Avaliacoes WHERE jogo_id = :jogo');
        $stmt->execute(['jogo' => $jogoId]);

        return $stmt->fetch();
    }

    public static function doUtilizador(int $utilizadorId, int $jogoId): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Avaliacoes WHERE utilizador_id = :utilizador AND jogo_id = :jogo');
        $stmt->execute(['utilizador' => $utilizadorId, 'jogo' => $jogoId]);

        return $stmt->fetch() ?: null;
    }

    // cada utilizador só tem uma avaliação por jogo, se já existir é atualizada
    public static function guardar(int $utilizadorId, int $jogoId, int $pontuacao, ?string $comentario): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO Avaliacoes (utilizador_id, jogo_id, pontuacao, comentario)
            VALUES (:utilizador, :jogo, :pontuacao, :comentario)
            ON DUPLICATE KEY UPDATE pontuacao = :pontuacao2, comentario = :comentario2, data_criacao = NOW()');
        $stmt->execute([
            'utilizador' => $utilizadorId,
            'jogo' => $jogoId,
            'pontuacao' => $pontuacao,
            'comentario' => $comentario,
            'pontuacao2' => $pontuacao,
            'comentario2' => $comentario,
        ]);
    }

    public static function apagar(int $utilizadorId, int $jogoId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Avaliacoes WHERE utilizador_id = :utilizador AND jogo_id = :jogo');
        $stmt->execute(['utilizador' => $utilizadorId, 'jogo' => $jogoId]);
    }
}
