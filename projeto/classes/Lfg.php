<?php

// LFG (looking for group): anúncios para encontrar pessoas para jogar e quem entrou em cada um
class Lfg
{
    // para ninguém encher a lista, cada utilizador só pode ter alguns anúncios abertos ao mesmo tempo
    const MAX_ABERTOS = 5;
    const MAX_VAGAS = 10;
    const MAX_DESCRICAO = 500;

    const ABAS = [
        'proximos' => 'Próximos',
        'meus' => 'Os meus',
        'terminados' => 'Terminados',
    ];

    // campos usados em todas as listas, já com o jogo, a plataforma, quem publicou e quantos entraram
    const CAMPOS = 'SELECT p.*, j.nome AS jogo, j.imagem_capa, pl.nome AS plataforma, u.nome_utilizador,
        (SELECT COUNT(*) FROM LFG_Participantes lp WHERE lp.post_id = p.id) AS participantes';

    const DE = ' FROM LFG_Posts p
        JOIN Jogos j ON j.id = p.jogo_id
        JOIN Utilizadores u ON u.id = p.utilizador_id
        LEFT JOIN Plataformas pl ON pl.id = p.plataforma_id';

    // a hora de agora vem do php e não do NOW() da bd, para ficar igual à que o utilizador escreve no formulário
    private static function agora(): string
    {
        return date('Y-m-d H:i:s');
    }

    public static function pesquisar(string $aba, array $filtros, int $utilizadorId, int $pagina, int $porPagina): array
    {
        $condicoes = [];
        $parametros = [];

        // cada aba tem a sua condição e a sua ordem (os nomes vêm da lista ABAS, validada na página)
        if ($aba === 'proximos') {
            $condicoes[] = "p.estado = 'aberto' AND p.data_hora_evento >= :agora";
            $parametros['agora'] = self::agora();
            $ordem = 'p.data_hora_evento ASC';
        } elseif ($aba === 'meus') {
            $condicoes[] = '(p.utilizador_id = :eu OR p.id IN (SELECT post_id FROM LFG_Participantes WHERE utilizador_id = :eu2))';
            $parametros['eu'] = $utilizadorId;
            $parametros['eu2'] = $utilizadorId;
            $ordem = 'p.data_hora_evento DESC';
        } else {
            $condicoes[] = "(p.estado = 'fechado' OR p.data_hora_evento < :agora)";
            $parametros['agora'] = self::agora();
            $ordem = 'p.data_hora_evento DESC';
        }

        if ($filtros['jogo'] !== '') {
            $condicoes[] = 'j.nome LIKE :jogo';
            $parametros['jogo'] = '%' . $filtros['jogo'] . '%';
        }

        if ($filtros['plataforma']) {
            $condicoes[] = 'p.plataforma_id = :plataforma';
            $parametros['plataforma'] = $filtros['plataforma'];
        }

        if ($filtros['vagas']) {
            $condicoes[] = '(SELECT COUNT(*) FROM LFG_Participantes lv WHERE lv.post_id = p.id) < p.vagas';
        }

        $where = ' WHERE ' . implode(' AND ', $condicoes);
        $pdo = Database::ligar();

        $stmt = $pdo->prepare('SELECT COUNT(*)' . self::DE . $where);
        $stmt->execute($parametros);
        $total = (int) $stmt->fetchColumn();

        $stmt = $pdo->prepare(self::CAMPOS . self::DE . $where . ' ORDER BY ' . $ordem . ', p.id DESC LIMIT :limite OFFSET :inicio');
        foreach ($parametros as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('inicio', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['anuncios' => $stmt->fetchAll(), 'total' => $total];
    }

    public static function buscar(int $id): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    // anúncios abertos de um jogo, para a página do jogo
    public static function doJogo(int $jogoId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . " WHERE p.jogo_id = :jogo AND p.estado = 'aberto' AND p.data_hora_evento >= :agora
            ORDER BY p.data_hora_evento ASC LIMIT :limite");
        $stmt->bindValue('jogo', $jogoId, PDO::PARAM_INT);
        $stmt->bindValue('agora', self::agora());
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // próximos anúncios em que o utilizador está (os dele e aqueles em que entrou), para o perfil
    public static function doUtilizador(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . ' WHERE (p.utilizador_id = :eu OR p.id IN (SELECT post_id FROM LFG_Participantes WHERE utilizador_id = :eu2))
            AND p.data_hora_evento >= :agora ORDER BY p.data_hora_evento ASC LIMIT :limite');
        $stmt->bindValue('eu', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('eu2', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('agora', self::agora());
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public static function participantes(int $postId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT lp.utilizador_id, lp.data_entrada, u.nome_utilizador FROM LFG_Participantes lp
            JOIN Utilizadores u ON u.id = lp.utilizador_id
            WHERE lp.post_id = :post
            ORDER BY lp.data_entrada');
        $stmt->execute(['post' => $postId]);

        return $stmt->fetchAll();
    }

    public static function participa(int $postId, int $utilizadorId): bool
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT id FROM LFG_Participantes WHERE post_id = :post AND utilizador_id = :utilizador');
        $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);

        return (bool) $stmt->fetch();
    }

    public static function abertosDoUtilizador(int $utilizadorId): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM LFG_Posts WHERE utilizador_id = :utilizador AND estado = 'aberto' AND data_hora_evento >= :agora");
        $stmt->execute(['utilizador' => $utilizadorId, 'agora' => self::agora()]);

        return (int) $stmt->fetchColumn();
    }

    public static function criar(int $utilizadorId, int $jogoId, ?int $plataformaId, string $descricao, int $vagas, string $dataHora): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO LFG_Posts (utilizador_id, jogo_id, plataforma_id, descricao, vagas, data_hora_evento)
            VALUES (:utilizador, :jogo, :plataforma, :descricao, :vagas, :data_hora)');
        $stmt->execute([
            'utilizador' => $utilizadorId,
            'jogo' => $jogoId,
            'plataforma' => $plataformaId,
            'descricao' => $descricao,
            'vagas' => $vagas,
            'data_hora' => $dataHora,
        ]);

        return (int) $pdo->lastInsertId();
    }

    // o texto do estado do anúncio, que aparece nos cartões e na página dele
    public static function estado(array $anuncio): string
    {
        if ($anuncio['data_hora_evento'] < self::agora()) {
            return 'Terminado';
        }
        if ($anuncio['estado'] === 'fechado') {
            return 'Fechado';
        }
        if ($anuncio['participantes'] >= $anuncio['vagas']) {
            return 'Cheio';
        }

        return $anuncio['participantes'] . ' de ' . $anuncio['vagas'] . ($anuncio['vagas'] == 1 ? ' vaga ocupada' : ' vagas ocupadas');
    }

    public static function entrar(int $postId, int $utilizadorId, string $nome): array
    {
        $pdo = Database::ligar();
        $pdo->beginTransaction();

        try {
            // o FOR UPDATE bloqueia o anúncio até ao commit, assim duas pessoas não ficam com a última vaga ao mesmo tempo
            $stmt = $pdo->prepare('SELECT p.*, j.nome AS jogo FROM LFG_Posts p JOIN Jogos j ON j.id = p.jogo_id WHERE p.id = :id FOR UPDATE');
            $stmt->execute(['id' => $postId]);
            $anuncio = $stmt->fetch();

            $stmt = $pdo->prepare('SELECT COUNT(*) FROM LFG_Participantes WHERE post_id = :post');
            $stmt->execute(['post' => $postId]);
            $ocupadas = (int) $stmt->fetchColumn();

            $erro = null;
            if (!$anuncio) {
                $erro = 'Este anúncio já não existe.';
            } elseif ((int) $anuncio['utilizador_id'] === $utilizadorId) {
                $erro = 'Não podes entrar no teu próprio anúncio.';
            } elseif ($anuncio['estado'] !== 'aberto' || $anuncio['data_hora_evento'] < self::agora()) {
                $erro = 'Este anúncio já não aceita jogadores.';
            } elseif (self::participa($postId, $utilizadorId)) {
                $erro = 'Já estás neste anúncio.';
            } elseif ($ocupadas >= $anuncio['vagas']) {
                $erro = 'Já não há vagas.';
            }

            if ($erro) {
                $pdo->rollBack();
                return ['sucesso' => false, 'erro' => $erro];
            }

            $stmt = $pdo->prepare('INSERT INTO LFG_Participantes (post_id, utilizador_id) VALUES (:post, :utilizador)');
            $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);

            $pdo->commit();
        } catch (PDOException $erro) {
            $pdo->rollBack();
            error_log($erro->getMessage());
            return ['sucesso' => false, 'erro' => 'Não foi possível entrar no anúncio. Tenta outra vez.'];
        }

        Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' entrou no teu anúncio de ' . $anuncio['jogo'] . ' (' . formatarDataEvento($anuncio['data_hora_evento']) . ').');

        return ['sucesso' => true];
    }

    public static function sair(int $postId, int $utilizadorId, string $nome): void
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio) {
            return;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM LFG_Participantes WHERE post_id = :post AND utilizador_id = :utilizador');
        $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);

        // só avisa o dono se a pessoa estava mesmo no anúncio
        if ($stmt->rowCount()) {
            Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' saiu do teu anúncio de ' . $anuncio['jogo'] . ' (' . formatarDataEvento($anuncio['data_hora_evento']) . ').');
        }
    }

    // fechar (não aceita mais ninguém) ou reabrir, só o dono pode
    public static function mudarEstado(int $postId, int $donoId, string $estado): void
    {
        if (!in_array($estado, ['aberto', 'fechado'])) {
            return;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE LFG_Posts SET estado = :estado WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['estado' => $estado, 'id' => $postId, 'dono' => $donoId]);
    }

    public static function apagar(int $postId, int $donoId): void
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio || (int) $anuncio['utilizador_id'] !== $donoId) {
            return;
        }

        // os participantes são guardados antes, porque ao apagar o anúncio a bd apaga-os também (ON DELETE CASCADE)
        $participantes = self::participantes($postId);

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM LFG_Posts WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['id' => $postId, 'dono' => $donoId]);

        foreach ($participantes as $participante) {
            Notificacoes::criar($participante['utilizador_id'], 'lfg', 'O anúncio de ' . $anuncio['jogo'] . ' (' . formatarDataEvento($anuncio['data_hora_evento']) . ') foi apagado.');
        }
    }

    public static function removerParticipante(int $postId, int $donoId, int $participanteId): void
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio || (int) $anuncio['utilizador_id'] !== $donoId) {
            return;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM LFG_Participantes WHERE post_id = :post AND utilizador_id = :utilizador');
        $stmt->execute(['post' => $postId, 'utilizador' => $participanteId]);

        if ($stmt->rowCount()) {
            Notificacoes::criar($participanteId, 'lfg', 'Foste removido do anúncio de ' . $anuncio['jogo'] . ' (' . formatarDataEvento($anuncio['data_hora_evento']) . ').');
        }
    }
}
