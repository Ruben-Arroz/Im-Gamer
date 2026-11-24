<?php

// LFG (looking for group): anúncios para completar equipas
class Lfg
{
    // limites para ninguém encher a lista
    const MAX_ABERTOS = 5;
    const MAX_VAGAS = 10;
    const MAX_DESCRICAO = 500;

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

    public static function criar(int $utilizadorId, int $jogoId, ?int $plataformaId, string $descricao, int $vagas, string $dataFim, ?string $discord, bool $aprovar): int
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO LFG_Posts (utilizador_id, jogo_id, plataforma_id, descricao, discord, vagas, aprovar, data_fim)
            VALUES (:utilizador, :jogo, :plataforma, :descricao, :discord, :vagas, :aprovar, :data_fim)');
        $stmt->execute([
            'utilizador' => $utilizadorId,
            'jogo' => $jogoId,
            'plataforma' => $plataformaId,
            'descricao' => $descricao,
            'discord' => $discord,
            'vagas' => $vagas,
            'aprovar' => $aprovar ? 1 : 0,
            'data_fim' => $dataFim,
        ]);

        return (int) $pdo->lastInsertId();
    }

    public static function expirado(array $anuncio): bool
    {
        return $anuncio['data_fim'] < self::hoje();
    }

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

    // entrar logo na equipa, só nos anúncios sem aprovação
    public static function entrar(int $postId, int $utilizadorId, string $nome): array
    {
        $anuncio = self::buscar($postId);
        if ($anuncio && $anuncio['aprovar']) {
            return ['sucesso' => false, 'erro' => 'Neste anúncio o dono aprova quem entra, faz um pedido.'];
        }

        $erro = self::juntar($postId, $utilizadorId);
        if ($erro) {
            return ['sucesso' => false, 'erro' => $erro];
        }

        Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' entrou na tua equipa de ' . $anuncio['jogo'] . '.');

        return ['sucesso' => true];
    }

    // mete a pessoa na equipa, devolve o erro ou null se correu bem
    private static function juntar(int $postId, int $utilizadorId): ?string
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
                return $erro;
            }

            $stmt = $pdo->prepare('INSERT INTO LFG_Participantes (post_id, utilizador_id) VALUES (:post, :utilizador)');
            $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);

            // se tinha um pedido à espera já não precisa dele
            $stmt = $pdo->prepare('DELETE FROM LFG_Pedidos WHERE post_id = :post AND utilizador_id = :utilizador');
            $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);

            $pdo->commit();
        } catch (PDOException $erro) {
            $pdo->rollBack();
            error_log($erro->getMessage());
            return 'Não foi possível entrar na equipa. Tenta outra vez.';
        }

        return null;
    }

    // pedido para entrar num anúncio em que o dono aprova quem entra
    public static function pedir(int $postId, int $utilizadorId, string $nome, ?string $mensagem): array
    {
        $anuncio = self::buscar($postId);
        $meuPedido = self::meuPedido($postId, $utilizadorId);

        $erro = null;
        if (!$anuncio) {
            $erro = 'Este anúncio já não existe.';
        } elseif ((int) $anuncio['utilizador_id'] === $utilizadorId) {
            $erro = 'Não podes pedir para entrar na tua própria equipa.';
        } elseif ($anuncio['estado'] !== 'aberto' || self::expirado($anuncio)) {
            $erro = 'Este anúncio já não aceita jogadores.';
        } elseif (self::participa($postId, $utilizadorId)) {
            $erro = 'Já estás nesta equipa.';
        } elseif ($meuPedido === 'pendente') {
            $erro = 'Já pediste para entrar, espera pela resposta do dono.';
        } elseif ($meuPedido === 'recusado') {
            $erro = 'O dono já recusou o teu pedido para esta equipa.';
        } elseif ($anuncio['participantes'] >= $anuncio['vagas']) {
            $erro = 'A equipa já está completa.';
        }

        if ($erro) {
            return ['sucesso' => false, 'erro' => $erro];
        }

        // o IGNORE é pq a bd só deixa um pedido por pessoa em cada anúncio
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT IGNORE INTO LFG_Pedidos (post_id, utilizador_id, mensagem) VALUES (:post, :utilizador, :mensagem)');
        $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId, 'mensagem' => $mensagem]);

        if ($stmt->rowCount()) {
            Notificacoes::criar($anuncio['utilizador_id'], 'lfg', $nome . ' pediu para entrar na tua equipa de ' . $anuncio['jogo'] . '.');
        }

        return ['sucesso' => true];
    }

    // 'pendente', 'recusado' ou null se a pessoa não fez nenhum pedido
    public static function meuPedido(int $postId, int $utilizadorId): ?string
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT estado FROM LFG_Pedidos WHERE post_id = :post AND utilizador_id = :utilizador');
        $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);
        $estado = $stmt->fetchColumn();

        return $estado === false ? null : $estado;
    }

    // os pedidos à espera de resposta, para o dono ver
    public static function pedidos(int $postId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT pe.id, pe.mensagem, pe.data_criacao, u.nome_utilizador FROM LFG_Pedidos pe
            JOIN Utilizadores u ON u.id = pe.utilizador_id
            WHERE pe.post_id = :post AND pe.estado = 'pendente'
            ORDER BY pe.data_criacao");
        $stmt->execute(['post' => $postId]);

        return $stmt->fetchAll();
    }

    public static function cancelarPedido(int $postId, int $utilizadorId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare("DELETE FROM LFG_Pedidos WHERE post_id = :post AND utilizador_id = :utilizador AND estado = 'pendente'");
        $stmt->execute(['post' => $postId, 'utilizador' => $utilizadorId]);
    }

    // o dono aceita o pedido, devolve o erro ou null se correu bem
    public static function aceitarPedido(int $postId, int $donoId, int $pedidoId): ?string
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio || (int) $anuncio['utilizador_id'] !== $donoId) {
            return null;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT utilizador_id FROM LFG_Pedidos WHERE id = :id AND post_id = :post AND estado = 'pendente'");
        $stmt->execute(['id' => $pedidoId, 'post' => $postId]);
        $candidato = $stmt->fetchColumn();
        if (!$candidato) {
            return null;
        }

        // usa o mesmo código do entrar, assim a última vaga continua protegida
        $erro = self::juntar($postId, (int) $candidato);
        if ($erro) {
            return $erro;
        }

        Notificacoes::criar((int) $candidato, 'lfg', 'Entraste na equipa de ' . $anuncio['jogo'] . ' de ' . $anuncio['nome_utilizador'] . ', o dono aceitou o teu pedido.');

        return null;
    }

    public static function recusarPedido(int $postId, int $donoId, int $pedidoId): void
    {
        $anuncio = self::buscar($postId);
        if (!$anuncio || (int) $anuncio['utilizador_id'] !== $donoId) {
            return;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare("SELECT utilizador_id FROM LFG_Pedidos WHERE id = :id AND post_id = :post AND estado = 'pendente'");
        $stmt->execute(['id' => $pedidoId, 'post' => $postId]);
        $candidato = $stmt->fetchColumn();
        if (!$candidato) {
            return;
        }

        // fica guardado como recusado para a pessoa não poder pedir outra vez
        $stmt = $pdo->prepare("UPDATE LFG_Pedidos SET estado = 'recusado' WHERE id = :id");
        $stmt->execute(['id' => $pedidoId]);

        Notificacoes::criar((int) $candidato, 'lfg', 'O teu pedido para a equipa de ' . $anuncio['jogo'] . ' de ' . $anuncio['nome_utilizador'] . ' foi recusado.');
    }

    // o dono liga ou desliga a aprovação de quem entra
    public static function mudarAprovacao(int $postId, int $donoId, bool $aprovar): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE LFG_Posts SET aprovar = :aprovar WHERE id = :id AND utilizador_id = :dono');
        $stmt->execute(['aprovar' => $aprovar ? 1 : 0, 'id' => $postId, 'dono' => $donoId]);
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
