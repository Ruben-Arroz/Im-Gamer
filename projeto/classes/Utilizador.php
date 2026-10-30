<?php

class Utilizador
{
    const MOEDAS = [
        'EUR' => 'Euro (€)',
        'USD' => 'Dólar americano ($)',
        'GBP' => 'Libra (£)',
        'BRL' => 'Real brasileiro (R$)',
    ];

    // campos usados nas duas pesquisas de utilizador, já com o nome do jogo favorito e da plataforma preferida
    const SELECT = 'SELECT u.id, u.nome_utilizador, u.email, u.bio, u.foto_perfil, u.foto_capa, u.moeda, u.esconder_valores, u.esconder_reputacao,
        u.is_admin, u.data_criacao, u.jogo_favorito_id, j.rawg_id AS jogo_favorito_rawg_id, j.nome AS jogo_favorito,
        u.plataforma_preferida_id, p.nome AS plataforma_preferida
        FROM Utilizadores u
        LEFT JOIN Jogos j ON j.id = u.jogo_favorito_id
        LEFT JOIN Plataformas p ON p.id = u.plataforma_preferida_id';

    public static function buscarPorId(int $id): array|false
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::SELECT . ' WHERE u.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch();
    }

    public static function buscarPorNome(string $nome): array|false
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::SELECT . ' WHERE u.nome_utilizador = :nome');
        $stmt->execute(['nome' => $nome]);

        return $stmt->fetch();
    }

    // pesquisa de jogadores pelo nome, para os convites (sem o próprio utilizador)
    public static function pesquisar(string $texto, int $excetoId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, nome_utilizador, foto_perfil FROM Utilizadores
            WHERE nome_utilizador LIKE :nome AND id != :eu
            ORDER BY nome_utilizador LIMIT :limite');
        $stmt->bindValue('nome', '%' . $texto . '%');
        $stmt->bindValue('eu', $excetoId, PDO::PARAM_INT);
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function nomeEmUso(string $nome, int $id): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM Utilizadores WHERE nome_utilizador = :nome AND id != :id');
        $stmt->execute(['nome' => $nome, 'id' => $id]);

        return (bool) $stmt->fetch();
    }

    public static function passwordCorreta(int $id, string $password): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT password_hash FROM Utilizadores WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $hash = $stmt->fetchColumn();

        return $hash && password_verify($password, $hash);
    }

    public static function atualizarPerfil(int $id, array $dados): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Utilizadores SET nome_utilizador = :nome, bio = :bio, foto_perfil = :foto, foto_capa = :capa,
            jogo_favorito_id = :jogo, plataforma_preferida_id = :plataforma, esconder_valores = :esconder,
            esconder_reputacao = :esconder_reputacao WHERE id = :id');

        $stmt->execute([
            'nome' => $dados['nome'],
            'bio' => $dados['bio'] !== '' ? $dados['bio'] : null,
            'foto' => $dados['foto'],
            'capa' => $dados['capa'],
            'jogo' => $dados['jogo'],
            'plataforma' => $dados['plataforma'],
            'esconder' => $dados['esconder'],
            'esconder_reputacao' => $dados['esconder_reputacao'],
            'id' => $id,
        ]);
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

    // trata da foto de perfil ou da capa: remover, manter a atual ou guardar uma nova
    // $pasta é "perfis" ou "capas", as subpastas de uploads
    public static function trocarImagem(?array $ficheiro, bool $remover, ?string $atual, string $pasta, int $id): array
    {
        if ($remover) {
            self::apagarFoto($atual);
            return ['sucesso' => true, 'nome' => null];
        }

        if (!$ficheiro || $ficheiro['error'] === UPLOAD_ERR_NO_FILE) {
            return ['sucesso' => true, 'nome' => $atual];
        }

        $resultado = self::guardarImagem($ficheiro, $pasta, $id);

        if ($resultado['sucesso']) {
            self::apagarFoto($atual);
        }

        return $resultado;
    }

    public static function guardarImagem(array $ficheiro, string $pasta, int $id): array
    {
        if ($ficheiro['error'] !== UPLOAD_ERR_OK) {
            return ['sucesso' => false, 'erro' => 'Não foi possível enviar a imagem.'];
        }

        if ($ficheiro['size'] > 4 * 1024 * 1024) {
            return ['sucesso' => false, 'erro' => 'A imagem não pode ter mais de 4 MB.'];
        }

        // confirma que é mesmo uma imagem e não só um ficheiro com a extensão trocada
        $info = getimagesize($ficheiro['tmp_name']);
        $tipos = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];

        if ($info === false || !isset($tipos[$info['mime']])) {
            return ['sucesso' => false, 'erro' => 'A imagem tem de ser JPG, PNG ou WEBP.'];
        }

        // na bd fica guardado o caminho dentro de uploads, por exemplo "capas/3_1792816377.jpg"
        $nome = $pasta . '/' . $id . '_' . time() . '.' . $tipos[$info['mime']];

        // a subpasta é criada na primeira vez que é precisa
        if (!is_dir(PASTA_UPLOADS . $pasta)) {
            mkdir(PASTA_UPLOADS . $pasta, 0755, true);
        }

        if (!move_uploaded_file($ficheiro['tmp_name'], PASTA_UPLOADS . $nome)) {
            return ['sucesso' => false, 'erro' => 'Não foi possível guardar a imagem.'];
        }

        return ['sucesso' => true, 'nome' => $nome];
    }

    public static function apagarFoto(?string $nome): void
    {
        // só apaga ficheiros das pastas perfis e capas, nunca nada fora delas
        if ($nome && preg_match('/^(perfis|capas)\/[A-Za-z0-9_.]+$/', $nome) && file_exists(PASTA_UPLOADS . $nome)) {
            unlink(PASTA_UPLOADS . $nome);
        }
    }
}
