<?php

class Biblioteca
{
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
