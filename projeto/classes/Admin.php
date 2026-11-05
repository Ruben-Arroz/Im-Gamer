<?php

// painel de administração: números do site e apagar utilizadores, anúncios, avaliações e jogos
class Admin
{
    const POR_PAGINA = 30;

    // separadores do painel, a chave é o ficheiro da página
    const ABAS = [
        'admin.php' => 'Resumo',
        'admin_utilizadores.php' => 'Utilizadores',
        'admin_lfg.php' => 'Anúncios LFG',
        'admin_avaliacoes.php' => 'Avaliações',
        'admin_jogos.php' => 'Jogos',
    ];

    // ids de outras plataformas que passam para o jogo que fica quando se juntam dois jogos
    const IDS_JOGO = ['rawg_id', 'slug', 'steam_app_id', 'xbox_id', 'gog_id', 'epic_id'];

    // quem não é administrador volta à página inicial
    public static function verificar(): void
    {
        if (!Auth::admin()) {
            header('Location: ' . BASE_URL . '/projeto/index.php');
            exit;
        }
    }

    // faz a contagem e vai buscar só a página pedida
    private static function paginar(string $campos, string $resto, string $ordem, array $parametros, int $pagina): array
    {
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT COUNT(*) ' . $resto);
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare($campos . ' ' . $resto . ' ORDER BY ' . $ordem . ' LIMIT :limite OFFSET :inicio');
        foreach ($parametros as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue('limite', self::POR_PAGINA, PDO::PARAM_INT);
        $stmt->bindValue('inicio', ($pagina - 1) * self::POR_PAGINA, PDO::PARAM_INT);
        $stmt->execute();

        return ['linhas' => $stmt->fetchAll(), 'total' => $total];
    }

    // números para o resumo
    public static function resumo(): array
    {
        $pdo = Database::ligar();
        $hoje = date('Y-m-d');
        $semanaPassada = date('Y-m-d H:i:s', strtotime('-7 days'));

        $stmt = $pdo->prepare("SELECT
            (SELECT COUNT(*) FROM Utilizadores) AS utilizadores,
            (SELECT COUNT(*) FROM Utilizadores WHERE data_criacao >= :semana) AS novos,
            (SELECT COUNT(*) FROM Contas_Ligadas) AS contas,
            (SELECT COUNT(*) FROM Jogos) AS jogos,
            (SELECT COUNT(*) FROM LFG_Posts WHERE estado = 'aberto' AND data_fim >= :hoje) AS anuncios,
            (SELECT COUNT(*) FROM Avaliacoes) AS avaliacoes,
            (SELECT COUNT(*) FROM Convites WHERE estado = 'pendente') AS convites");
        $stmt->execute(['semana' => $semanaPassada, 'hoje' => $hoje]);

        return $stmt->fetch();
    }

    public static function contasPorPlataforma(): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->query('SELECT servico, COUNT(*) AS total FROM Contas_Ligadas GROUP BY servico ORDER BY total DESC');

        return $stmt->fetchAll();
    }

    public static function ultimosRegistos(int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id, nome_utilizador, email, data_criacao FROM Utilizadores ORDER BY data_criacao DESC LIMIT :limite');
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // utilizadores com as plataformas ligadas e quantos anúncios e avaliações têm
    public static function utilizadores(string $texto, int $pagina): array
    {
        $campos = 'SELECT u.id, u.nome_utilizador, u.email, u.is_admin, u.data_criacao,
            (SELECT GROUP_CONCAT(c.servico ORDER BY c.servico) FROM Contas_Ligadas c WHERE c.utilizador_id = u.id) AS contas,
            (SELECT COUNT(*) FROM LFG_Posts l WHERE l.utilizador_id = u.id) AS anuncios,
            (SELECT COUNT(*) FROM Avaliacoes a WHERE a.utilizador_id = u.id) AS avaliacoes';
        $resto = 'FROM Utilizadores u';
        $parametros = [];

        if ($texto !== '') {
            $resto .= ' WHERE u.nome_utilizador LIKE :nome OR u.email LIKE :email';
            $parametros = ['nome' => '%' . $texto . '%', 'email' => '%' . $texto . '%'];
        }

        return self::paginar($campos, $resto, 'u.data_criacao DESC, u.id DESC', $parametros, $pagina);
    }

    // devolve um erro, ou null se o utilizador foi apagado
    public static function apagarUtilizador(int $id, int $adminId): ?string
    {
        if ($id === $adminId) {
            return 'A tua conta apaga-se nas definições, não aqui.';
        }

        $utilizador = Utilizador::buscarPorId($id);
        if (!$utilizador) {
            return 'Esse utilizador já não existe.';
        }

        if ($utilizador['is_admin']) {
            return 'Não dá para apagar outro administrador.';
        }

        // as fotos ficam na pasta uploads, o resto (contas, anúncios, avaliações, ...) vai com o ON DELETE CASCADE
        Utilizador::apagarFoto($utilizador['foto_perfil']);
        Utilizador::apagarFoto($utilizador['foto_capa']);
        Utilizador::apagar($id);

        return null;
    }

    // todos os anúncios, também os fechados e os que já saíram do ar
    public static function anuncios(string $texto, int $pagina): array
    {
        $resto = Lfg::DE;
        $parametros = [];

        if ($texto !== '') {
            $resto .= ' WHERE j.nome LIKE :jogo OR u.nome_utilizador LIKE :nome';
            $parametros = ['jogo' => '%' . $texto . '%', 'nome' => '%' . $texto . '%'];
        }

        return self::paginar(Lfg::CAMPOS, $resto, 'p.data_criacao DESC, p.id DESC', $parametros, $pagina);
    }

    public static function apagarAnuncio(int $id): ?string
    {
        $anuncio = Lfg::buscar($id);
        if (!$anuncio) {
            return 'Esse anúncio já não existe.';
        }

        // o Lfg::apagar só deixa o dono apagar, por isso vai o id do dono. os participantes recebem o aviso de lá
        Lfg::apagar($id, (int) $anuncio['utilizador_id']);
        Notificacoes::criar($anuncio['utilizador_id'], 'admin', 'O teu anúncio de ' . $anuncio['jogo'] . ' foi apagado por um administrador.');

        return null;
    }

    public static function avaliacoes(string $texto, bool $soComentario, int $pagina): array
    {
        $campos = 'SELECT a.*, u.nome_utilizador, j.nome AS jogo';
        $resto = 'FROM Avaliacoes a
            JOIN Utilizadores u ON u.id = a.utilizador_id
            JOIN Jogos j ON j.id = a.jogo_id';
        $condicoes = [];
        $parametros = [];

        if ($texto !== '') {
            $condicoes[] = '(j.nome LIKE :jogo OR u.nome_utilizador LIKE :nome OR a.comentario LIKE :comentario)';
            $parametros = ['jogo' => '%' . $texto . '%', 'nome' => '%' . $texto . '%', 'comentario' => '%' . $texto . '%'];
        }

        if ($soComentario) {
            $condicoes[] = "a.comentario IS NOT NULL AND a.comentario != ''";
        }

        if ($condicoes) {
            $resto .= ' WHERE ' . implode(' AND ', $condicoes);
        }

        return self::paginar($campos, $resto, 'a.data_criacao DESC, a.id DESC', $parametros, $pagina);
    }

    public static function apagarAvaliacao(int $id): ?string
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT a.utilizador_id, a.jogo_id, j.nome AS jogo FROM Avaliacoes a JOIN Jogos j ON j.id = a.jogo_id WHERE a.id = :id');
        $stmt->execute(['id' => $id]);
        $avaliacao = $stmt->fetch();

        if (!$avaliacao) {
            return 'Essa avaliação já não existe.';
        }

        Avaliacoes::apagar($avaliacao['utilizador_id'], $avaliacao['jogo_id']);
        Notificacoes::criar($avaliacao['utilizador_id'], 'admin', 'A tua avaliação de ' . $avaliacao['jogo'] . ' foi apagada por um administrador.');

        return null;
    }

    // jogos guardados na bd e onde são usados, para encontrar os repetidos
    public static function jogos(string $texto, int $pagina): array
    {
        $campos = 'SELECT j.id, j.nome, j.imagem_capa, j.data_lancamento, j.rawg_id, j.steam_app_id, j.xbox_id, j.gog_id, j.epic_id,
            (SELECT COUNT(*) FROM Biblioteca_Jogos b WHERE b.jogo_id = j.id) AS bibliotecas,
            (SELECT COUNT(*) FROM LFG_Posts l WHERE l.jogo_id = j.id) AS anuncios,
            (SELECT COUNT(*) FROM Lista_Desejos d WHERE d.jogo_id = j.id) AS desejos,
            (SELECT COUNT(*) FROM Avaliacoes a WHERE a.jogo_id = j.id) AS avaliacoes';
        $resto = 'FROM Jogos j';
        $parametros = [];

        // só com algarismos procura pelo número do jogo, senão pelo nome
        if (ctype_digit($texto)) {
            $resto .= ' WHERE j.id = :id';
            $parametros = ['id' => $texto];
        } elseif ($texto !== '') {
            $resto .= ' WHERE j.nome LIKE :nome';
            $parametros = ['nome' => '%' . $texto . '%'];
        }

        return self::paginar($campos, $resto, 'j.nome ASC, j.id ASC', $parametros, $pagina);
    }

    // apaga o jogo e, por causa do ON DELETE CASCADE, também os anúncios, desejos e avaliações dele
    public static function apagarJogo(int $id): ?string
    {
        if (!Jogo::buscarPorId($id)) {
            return 'Esse jogo já não existe.';
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Jogos WHERE id = :id');
        $stmt->execute(['id' => $id]);

        return null;
    }

    // passa tudo o que aponta para o jogo repetido para o outro e depois apaga o repetido
    public static function juntarJogos(int $repetidoId, int $ficaId): ?string
    {
        if ($repetidoId === $ficaId) {
            return 'Escolhe um jogo diferente para juntar.';
        }

        $repetido = Jogo::buscarPorId($repetidoId);
        $fica = Jogo::buscarPorId($ficaId);
        if (!$repetido || !$fica) {
            return 'Um dos jogos já não existe. Confirma o número do jogo.';
        }

        $pdo = Database::ligar();
        $pdo->beginTransaction();

        try {
            $ids = ['velho' => $repetidoId, 'novo' => $ficaId];

            // estas tabelas não têm limite de uma linha por jogo, passam todas
            $pdo->prepare('UPDATE Biblioteca_Jogos SET jogo_id = :novo WHERE jogo_id = :velho')->execute($ids);
            $pdo->prepare('UPDATE LFG_Posts SET jogo_id = :novo WHERE jogo_id = :velho')->execute($ids);
            $pdo->prepare('UPDATE Convites SET jogo_id = :novo WHERE jogo_id = :velho')->execute($ids);
            $pdo->prepare('UPDATE Utilizadores SET jogo_favorito_id = :novo WHERE jogo_favorito_id = :velho')->execute($ids);

            // aqui cada utilizador só pode ter o jogo uma vez. o IGNORE salta quem já tinha os dois,
            // e essas linhas que ficam no repetido são apagadas com ele
            $pdo->prepare('UPDATE IGNORE Lista_Desejos SET jogo_id = :novo WHERE jogo_id = :velho')->execute($ids);
            $pdo->prepare('UPDATE IGNORE Avaliacoes SET jogo_id = :novo WHERE jogo_id = :velho')->execute($ids);

            $pdo->prepare('DELETE FROM Jogos WHERE id = :id')->execute(['id' => $repetidoId]);

            // os ids das plataformas que o repetido tinha e o outro não passam para o que fica,
            // para a sincronização não voltar a criar o repetido. os nomes das colunas vêm da lista IDS_JOGO
            foreach (self::IDS_JOGO as $coluna) {
                if ($repetido[$coluna] !== null && $fica[$coluna] === null) {
                    $stmt = $pdo->prepare('UPDATE Jogos SET ' . $coluna . ' = :valor WHERE id = :id');
                    $stmt->execute(['valor' => $repetido[$coluna], 'id' => $ficaId]);
                }
            }

            $pdo->commit();
        } catch (PDOException $erro) {
            $pdo->rollBack();
            error_log($erro->getMessage());
            return 'Não foi possível juntar os jogos.';
        }

        return null;
    }
}
