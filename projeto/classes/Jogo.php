<?php

class Jogo
{
    // guarda um jogo escolhido na pesquisa da rawg (se ainda não existir) e devolve o id dele na nossa bd
    public static function guardarDaRawg(int $rawgId, string $nome, string $slug, ?string $imagem, ?string $lancamento): int
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT id FROM Jogos WHERE rawg_id = :rawg');
        $stmt->execute(['rawg' => $rawgId]);
        $id = $stmt->fetchColumn();

        if ($id) {
            return (int) $id;
        }

        $stmt = $pdo->prepare('INSERT INTO Jogos (rawg_id, nome, slug, imagem_capa, data_lancamento) VALUES (:rawg, :nome, :slug, :imagem, :lancamento)');
        $stmt->execute([
            'rawg' => $rawgId,
            'nome' => $nome,
            'slug' => $slug,
            'imagem' => $imagem ?: null,
            'lancamento' => $lancamento ?: null,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function plataformas(): array
    {
        $pdo = Database::ligar();
        return $pdo->query('SELECT id, nome FROM Plataformas ORDER BY nome')->fetchAll();
    }
}
