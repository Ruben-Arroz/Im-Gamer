<?php

class ContasLigadas
{
    const SERVICOS = [
        'steam' => 'Steam',
        'playstation' => 'PlayStation',
        'xbox' => 'Xbox',
        'epic' => 'Epic Games',
        'gog' => 'GOG',
        'battlenet' => 'Battle.net',
        'nintendo' => 'Nintendo',
    ];

    public static function listar(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Contas_Ligadas WHERE utilizador_id = :id ORDER BY servico');
        $stmt->execute(['id' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    public static function adicionar(int $utilizadorId, string $servico, string $identificador): array
    {
        $pdo = Database::ligar();

        // só pode haver uma conta de cada plataforma por utilizador
        $stmt = $pdo->prepare('SELECT id FROM Contas_Ligadas WHERE utilizador_id = :id AND servico = :servico');
        $stmt->execute(['id' => $utilizadorId, 'servico' => $servico]);

        if ($stmt->fetch()) {
            return ['sucesso' => false, 'erro' => 'Já tens uma conta ' . self::SERVICOS[$servico] . ' ligada.'];
        }

        $stmt = $pdo->prepare('INSERT INTO Contas_Ligadas (utilizador_id, servico, identificador) VALUES (:id, :servico, :identificador)');
        $stmt->execute(['id' => $utilizadorId, 'servico' => $servico, 'identificador' => $identificador]);

        return ['sucesso' => true];
    }

    // o utilizador_id no WHERE impede que alguém apague contas de outra pessoa
    public static function remover(int $contaId, int $utilizadorId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Contas_Ligadas WHERE id = :conta AND utilizador_id = :utilizador');
        $stmt->execute(['conta' => $contaId, 'utilizador' => $utilizadorId]);
    }
}
