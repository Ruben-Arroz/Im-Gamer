<?php

// LFG (looking for group): anúncios para completar equipas e quem entrou em cada um
// cada anúncio fica no ar até ao dia que o dono escolher (data_fim)
class Lfg
{
    // limites para ninguém encher a lista
    const MAX_ABERTOS = 5;
    const MAX_VAGAS = 10;
    const MAX_DESCRICAO = 500;

    // por defeito o anúncio fica 14 dias no ar, no máximo 60
    const DIAS_NO_AR = 14;
    const MAX_DIAS = 60;

    const ABAS = [
        'proximos' => 'Anúncios',
        'meus' => 'Os meus',
    ];

    const ORDENS = [
        'recentes' => ['Publicados há menos tempo', 'p.data_criacao DESC'],
        'vagas' => ['Mais vagas livres', '(p.vagas - (SELECT COUNT(*) FROM LFG_Participantes lo WHERE lo.post_id = p.id)) DESC, p.data_criacao DESC'],
        'fim' => ['A sair do ar em breve', 'p.data_fim ASC, p.data_criacao DESC'],
    ];

    // campos de todas as listas (com o jogo, a plataforma, o dono e quantos entraram)
    const CAMPOS = 'SELECT p.*, j.nome AS jogo, j.imagem_capa, pl.nome AS plataforma, u.nome_utilizador,
        (SELECT COUNT(*) FROM LFG_Participantes lp WHERE lp.post_id = p.id) AS participantes';

    const DE = ' FROM LFG_Posts p
        JOIN Jogos j ON j.id = p.jogo_id
        JOIN Utilizadores u ON u.id = p.utilizador_id
        LEFT JOIN Plataformas pl ON pl.id = p.plataforma_id';

    // o dia de hoje vem do php e não do CURDATE() da bd, para bater certo com o do formulário
    private static function hoje(): string
    {
        return date('Y-m-d');
    }

    public static function pesquisar(string $aba, array $filtros, int $utilizadorId, int $pagina, int $porPagina): array
    {
        $condicoes = [];
        $parametros = [];
        // os da ordem ficam à parte porque a query do COUNT não tem ORDER BY
        $parametrosOrdem = [];

        if ($aba === 'meus') {
            $condicoes[] = '(p.utilizador_id = :eu OR p.id IN (SELECT post_id FROM LFG_Participantes WHERE utilizador_id = :eu2))';
            $parametros['eu'] = $utilizadorId;
            $parametros['eu2'] = $utilizadorId;

            // primeiro os que ainda estão no ar, depois os que já saíram
            $ordem = 'p.data_fim < :hoje_ordem, p.data_criacao DESC';
            $parametrosOrdem['hoje_ordem'] = self::hoje();
        } else {
            $condicoes[] = "p.estado = 'aberto' AND p.data_fim >= :hoje";
            $parametros['hoje'] = self::hoje();
            $ordem = self::ORDENS[$filtros['ordem']][1];
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
        foreach (array_merge($parametros, $parametrosOrdem) as $nome => $valor) {
            $stmt->bindValue($nome, $valor);
        }
        $stmt->bindValue('limite', $porPagina, PDO::PARAM_INT);
        $stmt->bindValue('inicio', ($pagina - 1) * $porPagina, PDO::PARAM_INT);
        $stmt->execute();

        return ['anuncios' => $stmt->fetchAll(), 'total' => $total];
    }

    // nomes dos jogos com anúncios no ar, para as sugestões da pesquisa
    public static function jogosComAnuncios(): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT DISTINCT j.nome FROM LFG_Posts p JOIN Jogos j ON j.id = p.jogo_id
            WHERE p.estado = 'aberto' AND p.data_fim >= :hoje ORDER BY j.nome LIMIT 100");
        $stmt->execute(['hoje' => self::hoje()]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public static function buscar(int $id): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . ' WHERE p.id = :id');
        $stmt->execute(['id' => $id]);

        return $stmt->fetch() ?: null;
    }

    // anúncios no ar de um jogo, para a página do jogo
    public static function doJogo(int $jogoId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . " WHERE p.jogo_id = :jogo AND p.estado = 'aberto' AND p.data_fim >= :hoje
            ORDER BY p.data_criacao DESC LIMIT :limite");
        $stmt->bindValue('jogo', $jogoId, PDO::PARAM_INT);
        $stmt->bindValue('hoje', self::hoje());
        $stmt->bindValue('limite', $limite, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    // anúncios no ar do utilizador (os dele e onde entrou), para o perfil
    public static function doUtilizador(int $utilizadorId, int $limite): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare(self::CAMPOS . self::DE . ' WHERE (p.utilizador_id = :eu OR p.id IN (SELECT post_id FROM LFG_Participantes WHERE utilizador_id = :eu2))
            AND p.data_fim >= :hoje ORDER BY p.data_criacao DESC LIMIT :limite');
        $stmt->bindValue('eu', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('eu2', $utilizadorId, PDO::PARAM_INT);
        $stmt->bindValue('hoje', self::hoje());
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
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM LFG_Posts WHERE utilizador_id = :utilizador AND estado = 'aberto' AND data_fim >= :hoje");
        $stmt->execute(['utilizador' => $utilizadorId, 'hoje' => self::hoje()]);

        return (int) $stmt->fetchColumn();
    }

    public static function criar(int $utilizadorId, int $jogoId, ?int $plataformaId, string $descricao, int $vagas, string $dataFim): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO LFG_Posts (utilizador_id, jogo_id, plataforma_id, descricao, vagas, data_fim)
            VALUES (:utilizador, :jogo, :plataforma, :descricao, :vagas, :data_fim)');
        $stmt->execute([
            'utilizador' => $utilizadorId,
            'jogo' => $jogoId,
            'plataforma' => $plataformaId,
            'descricao' => $descricao,
            'vagas' => $vagas,
            'data_fim' => $dataFim,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function expirado(array $anuncio): bool
    {
        return $anuncio['data_fim'] < self::hoje();
    }

    // texto do estado que aparece nos cartões
    public static function estado(array $anuncio): string
    {
        if (self::expirado($anuncio)) {
            return 'Já saiu do ar';
        }
        if ($anuncio['estado'] === 'fechado') {
            return 'Fechado';
        }

        $livres = $anuncio['vagas'] - $anuncio['participantes'];
        if ($livres <= 0) {
            return 'Equipa completa';
        }

        return $livres == 1 ? 'Falta 1 jogador' : 'Faltam ' . $livres . ' jogadores';
    }

    public static function entrar(int $postId, int $utilizadorId, string $nome): array
    {
        $pdo = Database::ligar();
        $pdo->beginTransaction();

        try {
            // o FOR UPDATE bloqueia o anúncio até ao commit, para duas pessoas não ficarem com a última vaga
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
                $erro = 'Não podes entrar na tua própria equipa.';
            } elseif ($anuncio['estado'] !== 'aberto' || self::expirado($anuncio)) {
                $erro = 'Este anúncio já não aceita jogadores.';
            } elseif (self::participa($postId, $utilizadorId)) {
                $erro = 'Já estás nesta equipa.';
            } elseif ($ocupadas >= $anuncio['vagas']) {
                $erro = 'A equipa já está completa.';
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
            return ['sucesso' => false, 'erro' => 'Não foi possível entrar na equipa. Tenta outra vez.'];
        }

        Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' entrou na tua equipa de ' . $anuncio['jogo'] . '.');

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

        // só avisa o dono se a pessoa estava mesmo na equipa
        if ($stmt->rowCount()) {
            Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' saiu da tua equipa de ' . $anuncio['jogo'] . '.');
        }
    }

    // fechar ou reabrir, só o dono
    public static function mudarEstado(int $postId, int $donoId, string $estado): void
    {
        if (!in_array($estado, ['aberto', 'fechado'])) {
            return;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE LFG_Posts SET estado = :estado WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['estado' => $estado, 'id' => $postId, 'dono' => $donoId]);
    }

    // o dono pode deixar o anúncio mais 14 dias no ar (a contar de hoje)
    public static function prolongar(int $postId, int $donoId): void
    {
        $novaData = date('Y-m-d', strtotime('+' . self::DIAS_NO_AR . ' days'));

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE LFG_Posts SET data_fim = GREATEST(data_fim, :nova) WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['nova' => $novaData, 'id' => $postId, 'dono' => $donoId]);
    }

    public static function apagar(int $postId, int $donoId): void
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio || (int) $anuncio['utilizador_id'] !== $donoId) {
            return;
        }

        // guarda os participantes antes, porque o ON DELETE CASCADE apaga-os com o anúncio
        $participantes = self::participantes($postId);

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM LFG_Posts WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['id' => $postId, 'dono' => $donoId]);

        foreach ($participantes as $participante) {
            Notificacoes::criar($participante['utilizador_id'], 'lfg', 'A equipa de ' . $anuncio['jogo'] . ' de ' . $anuncio['nome_utilizador'] . ' foi apagada.');
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
            Notificacoes::criar($participanteId, 'lfg', 'Foste removido da equipa de ' . $anuncio['jogo'] . ' de ' . $anuncio['nome_utilizador'] . '.');
        }
    }
}
