<?php

// a app não usa a sessão do site: no login recebe um token e manda-o em todos os pedidos
// na bd fica só o hash do token, assim quem vir a tabela não consegue entrar na conta de ninguém
class TokensApi
{
    // depois deste tempo é preciso entrar outra vez na app
    const DIAS = 30;

    private static function limite(): string
    {
        return date('Y-m-d H:i:s', strtotime('-' . self::DIAS . ' days'));
    }

    public static function criar(int $utilizadorId): string
    {
        $token = bin2hex(random_bytes(32));
        $pdo = Database::ligar();

        // aproveita para limpar os que já passaram do prazo
        $stmt = $pdo->prepare('DELETE FROM Tokens_Api WHERE data_criacao < :limite');
        $stmt->execute(['limite' => self::limite()]);

        $stmt = $pdo->prepare('INSERT INTO Tokens_Api (utilizador_id, token_hash) VALUES (:utilizador, :hash)');
        $stmt->execute(['utilizador' => $utilizadorId, 'hash' => hash('sha256', $token)]);

        return $token;
    }

    // devolve o id do dono do token, ou null se o token não existir ou já tiver passado do prazo
    public static function utilizador(string $token): ?int
    {
        if (!preg_match('/^[a-f0-9]{64}$/', $token)) {
            return null;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT utilizador_id FROM Tokens_Api WHERE token_hash = :hash AND data_criacao >= :limite');
        $stmt->execute(['hash' => hash('sha256', $token), 'limite' => self::limite()]);
        $id = $stmt->fetchColumn();

        return $id ? (int) $id : null;
    }

    public static function apagar(string $token): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Tokens_Api WHERE token_hash = :hash');
        $stmt->execute(['hash' => hash('sha256', $token)]);
    }
}
