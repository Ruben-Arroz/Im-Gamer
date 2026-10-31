<?php

// convites diretos para jogar, de um utilizador para outro
class Convites
{
    // para ninguém encher os outros de convites
    const MAX_PENDENTES = 20;

    public static function enviar(int $remetenteId, int $destinatarioId, ?int $jogoId, ?string $mensagem): array
    {
        if ($remetenteId === $destinatarioId) {
            return ['sucesso' => false, 'erro' => 'Não te podes convidar a ti próprio.'];
        }

        $pdo = Database::ligar();

        $stmt = $pdo->prepare("SELECT id FROM Convites WHERE remetente_id = :remetente AND destinatario_id = :destinatario AND estado = 'pendente'");
        $stmt->execute(['remetente' => $remetenteId, 'destinatario' => $destinatarioId]);
        if ($stmt->fetch()) {
            return ['sucesso' => false, 'erro' => 'Já tens um convite à espera de resposta desta pessoa.'];
        }

        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Convites WHERE remetente_id = :remetente AND estado = 'pendente'");
        $stmt->execute(['remetente' => $remetenteId]);
        if ($stmt->fetchColumn() >= self::MAX_PENDENTES) {
            return ['sucesso' => false, 'erro' => 'Tens demasiados convites à espera de resposta. Espera que respondam ou cancela alguns.'];
        }

        $stmt = $pdo->prepare('INSERT INTO Convites (remetente_id, destinatario_id, jogo_id, mensagem) VALUES (:remetente, :destinatario, :jogo, :mensagem)');
        $stmt->execute([
            'remetente' => $remetenteId,
            'destinatario' => $destinatarioId,
            'jogo' => $jogoId,
            'mensagem' => $mensagem,
        ]);

        return ['sucesso' => true];
    }

    public static function recebidos(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT c.*, u.nome_utilizador AS remetente, j.nome AS jogo FROM Convites c
            JOIN Utilizadores u ON u.id = c.remetente_id
            LEFT JOIN Jogos j ON j.id = c.jogo_id
            WHERE c.destinatario_id = :utilizador
            ORDER BY c.estado = 'pendente' DESC, c.data_criacao DESC
            LIMIT 50");
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    public static function enviados(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT c.*, u.nome_utilizador AS destinatario, j.nome AS jogo FROM Convites c
            JOIN Utilizadores u ON u.id = c.destinatario_id
            LEFT JOIN Jogos j ON j.id = c.jogo_id
            WHERE c.remetente_id = :utilizador
            ORDER BY c.estado = 'pendente' DESC, c.data_criacao DESC
            LIMIT 50");
        $stmt->execute(['utilizador' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    // convites recebidos ainda sem resposta
    public static function pendentes(int $utilizadorId): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM Convites WHERE destinatario_id = :utilizador AND estado = 'pendente'");
        $stmt->execute(['utilizador' => $utilizadorId]);

        return (int) $stmt->fetchColumn();
    }

    // só quem recebeu responde, e só enquanto está pendente
    public static function responder(int $conviteId, int $destinatarioId, string $nomeDestinatario, bool $aceitar): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT c.*, j.nome AS jogo FROM Convites c LEFT JOIN Jogos j ON j.id = c.jogo_id
            WHERE c.id = :id AND c.destinatario_id = :destinatario AND c.estado = 'pendente'");
        $stmt->execute(['id' => $conviteId, 'destinatario' => $destinatarioId]);
        $convite = $stmt->fetch();

        if (!$convite) {
            return;
        }

        $stmt = $pdo->prepare('UPDATE Convites SET estado = :estado WHERE id = :id');
        $stmt->execute(['estado' => $aceitar ? 'aceite' : 'recusado', 'id' => $conviteId]);

        $texto = $nomeDestinatario . ($aceitar ? ' aceitou' : ' recusou') . ' o teu convite';
        if ($convite['jogo']) {
            $texto .= ' para jogar ' . $convite['jogo'];
        }
        Notificacoes::criar($convite['remetente_id'], 'convite', $texto . '.');
    }

    // quem enviou pode cancelar enquanto não há resposta
    public static function cancelar(int $conviteId, int $remetenteId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("DELETE FROM Convites WHERE id = :id AND remetente_id = :remetente AND estado = 'pendente'");
        $stmt->execute(['id' => $conviteId, 'remetente' => $remetenteId]);
    }
}
