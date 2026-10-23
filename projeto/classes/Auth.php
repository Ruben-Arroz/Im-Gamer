<?php

class Auth
{
    public static function registar(string $nomeUtilizador, string $email, string $password): array
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT id FROM Utilizadores WHERE nome_utilizador = :nome OR email = :email');
        $stmt->execute(['nome' => $nomeUtilizador, 'email' => $email]);

        if ($stmt->fetch()) {
            return ['sucesso' => false, 'erro' => 'Esse nome de utilizador ou email já está em uso.'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);

        $stmt = $pdo->prepare('INSERT INTO Utilizadores (nome_utilizador, email, password_hash) VALUES (:nome, :email, :hash)');
        $stmt->execute(['nome' => $nomeUtilizador, 'email' => $email, 'hash' => $hash]);

        return ['sucesso' => true, 'id' => (int) $pdo->lastInsertId()];
    }

    public static function login(string $email, string $password): array
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT * FROM Utilizadores WHERE email = :email');
        $stmt->execute(['email' => $email]);
        $utilizador = $stmt->fetch();

        if (!$utilizador || !password_verify($password, $utilizador['password_hash'])) {
            return ['sucesso' => false, 'erro' => 'Email ou password incorretos.'];
        }

        session_regenerate_id(true);

        $_SESSION['utilizador_id'] = $utilizador['id'];
        $_SESSION['nome_utilizador'] = $utilizador['nome_utilizador'];
        $_SESSION['is_admin'] = (bool) $utilizador['is_admin'];

        return ['sucesso' => true];
    }

    public static function logout(): void
    {
        $_SESSION = [];
        session_destroy();
    }

    public static function autenticado(): bool
    {
        return isset($_SESSION['utilizador_id']);
    }

    // token escondido nos formulários, para outro site não conseguir enviar pedidos em nome do utilizador
    public static function token(): string
    {
        if (!isset($_SESSION['token'])) {
            $_SESSION['token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['token'];
    }

    public static function tokenValido(string $token): bool
    {
        return isset($_SESSION['token']) && hash_equals($_SESSION['token'], $token);
    }
}