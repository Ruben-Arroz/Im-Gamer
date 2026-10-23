<?php

class Utilizador
{
    const MOEDAS = [
        'EUR' => 'Euro (€)',
        'USD' => 'Dólar americano ($)',
        'GBP' => 'Libra (£)',
        'BRL' => 'Real brasileiro (R$)',
    ];

    public static function buscarPorId(int $id): array|false
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, nome_utilizador, email, bio, foto_perfil, moeda, is_admin, data_criacao FROM Utilizadores WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    public static function buscarPorNome(string $nome): array|false
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, nome_utilizador, email, bio, foto_perfil, moeda, is_admin, data_criacao FROM Utilizadores WHERE nome_utilizador = :nome');
        $stmt->execute(['nome' => $nome]);

        return $stmt->fetch();
    }

    public static function passwordCorreta(int $id, string $password): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT password_hash FROM Utilizadores WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return $hash && password_verify($password, $hash);
    }

    public static function atualizarPerfil(int $id, string $bio, ?string $foto): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Utilizadores SET bio = :bio, foto_perfil = :foto WHERE id = :id');
        $stmt->execute(['bio' => $bio !== '' ? $bio : null, 'foto' => $foto, 'id' => $id]);
    }

    public static function atualizarMoeda(int $id, string $moeda): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Utilizadores SET moeda = :moeda WHERE id = :id');
        $stmt->execute(['moeda' => $moeda, 'id' => $id]);
    }

    public static function atualizarEmail(int $id, string $email): array
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT id FROM Utilizadores WHERE email = :email AND id != :id');
        $stmt->execute(['email' => $email, 'id' => $id]);

        if ($stmt->fetch()) {
            return ['sucesso' => false, 'erro' => 'Esse email já está a ser usado noutra conta.'];
        }

        $stmt = $pdo->prepare('UPDATE Utilizadores SET email = :email WHERE id = :id');
        $stmt->execute(['email' => $email, 'id' => $id]);

        return ['sucesso' => true];
    }

    public static function atualizarPassword(int $id, string $password): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Utilizadores SET password_hash = :hash WHERE id = :id');
        $stmt->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    // com o ON DELETE CASCADE da BD, apaga também tudo o que é do utilizador
    public static function apagar(int $id): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Utilizadores WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }

    public static function guardarFoto(array $ficheiro, int $id): array
    {
        if ($ficheiro['error'] !== UPLOAD_ERR_OK) {
            return ['sucesso' => false, 'erro' => 'Não foi possível enviar a foto.'];
        }

        if ($ficheiro['size'] > 2 * 1024 * 1024) {
            return ['sucesso' => false, 'erro' => 'A foto não pode ter mais de 2 MB.'];
        }

        // confirma que é mesmo uma imagem e não só um ficheiro com a extensão trocada
        $info = getimagesize($ficheiro['tmp_name']);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if ($info === false || !isset($tipos[$info['mime']])) {
            return ['sucesso' => false, 'erro' => 'A foto tem de ser JPG, PNG ou WEBP.'];
        }

        $nome = 'perfil_' . $id . '_' . time() . '.' . $tipos[$info['mime']];

        if (!move_uploaded_file($ficheiro['tmp_name'], PASTA_UPLOADS . $nome)) {
            return ['sucesso' => false, 'erro' => 'Não foi possível guardar a foto.'];
        }

        return ['sucesso' => true, 'nome' => $nome];
    }

    public static function apagarFoto(?string $nome): void
    {
        // o basename garante que só se apagam ficheiros dentro da pasta uploads
        if ($nome && file_exists(PASTA_UPLOADS . basename($nome))) {
            unlink(PASTA_UPLOADS . basename($nome));
        }
    }
}
