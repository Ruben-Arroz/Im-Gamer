<?php

// avisos do LFG (alguém entrou, saiu, respondeu ao convite, ...)
// os convites recebidos não criam aviso porque já aparecem na lista deles
class Notificacoes
{
    public static function criar(int $utilizadorId, string $tipo, string $conteudo): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO Notificacoes (utilizador_id, tipo, conteudo) VALUES (:utilizador, :tipo, :conteudo)');
        $stmt->execute([
            'utilizador' => $utilizadorId,
            'tipo' => $tipo,
            // a coluna só tem 255 letras
            'conteudo' => mb_substr($conteudo, 0, 255),
        ]);
    }

    public static function listar(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Notificacoes WHERE utilizador_id = :utilizador ORDER BY data_criacao DESC, id DESC LIMIT :limite');
        $stmt->bindValue('utilizador', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function porLer(int $utilizadorId): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Notificacoes WHERE utilizador_id = :utilizador AND lida = 0');
        $stmt->execute(['utilizador' => $utilizadorId]);

        return (int) $stmt->fetchColumn();
    }

    public static function marcarLidas(int $utilizadorId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Notificacoes SET lida = 1 WHERE utilizador_id = :utilizador AND lida = 0');
        $stmt->execute(['utilizador' => $utilizadorId]);
    }
}
