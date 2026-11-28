<?php

class ContasLigadas
{
    const SERVICOS = [
        'steam' => 'Steam',
        'playstation' => 'PlayStation',
        'xbox' => 'Xbox',
        'epic' => 'Epic Games',
        'gog' => 'GOG',
        'battlenet' => 'Battle.net',
        'nintendo' => 'Nintendo',
    ];

    // plataformas sem api, os jogos são adicionados à mão (contas/jogos_manuais.php)
    const MANUAIS = ['nintendo', 'battlenet'];

    // da plataforma mais famosa para a menos famosa, usado para ordenar as contas no perfil
    const FAMA = ['steam', 'playstation', 'xbox', 'epic', 'nintendo', 'battlenet', 'gog'];

    // contas que se atualizam sozinhas, as com mais de 6 horas sem atualizar (e as que nunca foram)
    // a epic e a playstation ficam de fora pq precisam do login do dono
    // as com mais de 150 jogos tbm, pq demoram mais do que um pedido do site aguenta e ficam para o botão da ferramenta
    const DESATUALIZADA = "servico IN ('steam', 'xbox', 'gog') AND num_jogos <= 150
        AND (ultima_sincronizacao IS NULL OR ultima_sincronizacao < NOW() - INTERVAL 6 HOUR)";

    // as contas com jogos primeiro
    public static function ordenarPorFama(array $contas): array
    {
        usort($contas, function ($a, $b) {
            $temA = $a['num_jogos'] > 0 ? 0 : 1;
            $temB = $b['num_jogos'] > 0 ? 0 : 1;

            if ($temA !== $temB) {
                return $temA <=> $temB;
            }

            return array_search($a['servico'], self::FAMA) <=> array_search($b['servico'], self::FAMA);
        });

        return $contas;
    }

    // "steam,xbox" fica "Steam, Xbox" (vem assim do GROUP_CONCAT nas listas de jogadores)
    public static function nomes(string $servicos): string
    {
        $nomes = [];
        foreach (explode(',', $servicos) as $servico) {
            $nomes[] = self::SERVICOS[$servico] ?? $servico;
        }
        return implode(', ', $nomes);
    }

    public static function listar(int $utilizadorId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Contas_Ligadas WHERE utilizador_id = :id ORDER BY servico');
        $stmt->execute(['id' => $utilizadorId]);

        return $stmt->fetchAll();
    }

    public static function adicionar(int $utilizadorId, string $servico, string $identificador): array
    {
        $pdo = Database::ligar();

        // só pode haver uma conta de cada plataforma por utilizador
        $stmt = $pdo->prepare('SELECT id FROM Contas_Ligadas WHERE utilizador_id = :id AND servico = :servico');
        $stmt->execute(['id' => $utilizadorId, 'servico' => $servico]);

        if ($stmt->fetch()) {
            return ['sucesso' => false, 'erro' => 'Já tens uma conta ' . self::SERVICOS[$servico] . ' ligada.'];
        }

        $stmt = $pdo->prepare('INSERT INTO Contas_Ligadas (utilizador_id, servico, identificador) VALUES (:id, :servico, :identificador)');
        $stmt->execute(['id' => $utilizadorId, 'servico' => $servico, 'identificador' => $identificador]);

        return ['sucesso' => true];
    }

    // com o utilizador_id ninguém apaga contas dos outros
    public static function remover(int $contaId, int $utilizadorId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Contas_Ligadas WHERE id = :conta AND utilizador_id = :utilizador');
        $stmt->execute(['conta' => $contaId, 'utilizador' => $utilizadorId]);
    }

    // vai buscar os dados de uma conta à sua plataforma, usado pelo tarefas/sincronizar.php e ao abrir o perfil
    public static function sincronizar(array $conta): array
    {
        if ($conta['servico'] === 'steam') {
            return Steam::sincronizar($conta);
        }
        if ($conta['servico'] === 'gog') {
            return Gog::sincronizar($conta);
        }
        if ($conta['servico'] === 'xbox') {
            return Xbox::sincronizar($conta);
        }
        return ['sucesso' => false, 'erro' => 'Esta conta não se atualiza sozinha.'];
    }

    // condição das contas desatualizadas de um utilizador (ou do site todo com null), sem as plataformas de $saltar
    // o perfil manda as que já tentou, assim uma conta que falha sempre não trava as outras
    private static function condicao(?int $utilizadorId, array $saltar): array
    {
        $sql = self::DESATUALIZADA;
        $parametros = [];
        if ($utilizadorId !== null) {
            $sql .= ' AND utilizador_id = :id';
            $parametros['id'] = $utilizadorId;
        }

        $marcadores = [];
        foreach (array_values($saltar) as $i => $servico) {
            $marcadores[] = ':saltar' . $i;
            $parametros['saltar' . $i] = $servico;
        }
        if ($marcadores) {
            $sql .= ' AND servico NOT IN (' . implode(', ', $marcadores) . ')';
        }

        return [$sql, $parametros];
    }

    public static function porAtualizar(?int $utilizadorId, array $saltar = []): int
    {
        [$sql, $parametros] = self::condicao($utilizadorId, $saltar);

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Contas_Ligadas WHERE ' . $sql);
        $stmt->execute($parametros);

        return (int) $stmt->fetchColumn();
    }

    // atualiza uma conta desatualizada do utilizador ou do site (a mais antiga) e devolve o resultado, ou null se não houver nenhuma
    // é uma de cada vez pq no servidor cada pedido só pode durar pouco tempo
    public static function atualizarUma(?int $utilizadorId, array $saltar = []): ?array
    {
        [$sql, $parametros] = self::condicao($utilizadorId, $saltar);

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Contas_Ligadas WHERE ' . $sql . ' ORDER BY ultima_sincronizacao LIMIT 1');
        $stmt->execute($parametros);
        $conta = $stmt->fetch();

        if (!$conta) {
            return null;
        }

        // marca a conta como atualizada há 5 horas antes de começar, assim duas pessoas não a atualizam ao mesmo tempo
        // se correr bem fica com a hora de agora, se falhar ou o servidor cortar o pedido só volta a ser tentada daqui a 1 hora
        $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET ultima_sincronizacao = NOW() - INTERVAL 5 HOUR WHERE id = :id AND ' . self::DESATUALIZADA);
        $stmt->execute(['id' => $conta['id']]);
        if (!$stmt->rowCount()) {
            return ['sucesso' => true, 'servico' => $conta['servico']];
        }

        try {
            $resultado = self::sincronizar($conta);
            // na steam vem tbm a lista de itens do inventário (os preços vêm depois aos poucos)
            if ($resultado['sucesso'] && $conta['servico'] === 'steam') {
                Steam::atualizarItens($conta['id']);
            }
        } catch (Throwable $erro) {
            error_log($erro->getMessage());
            $resultado = ['sucesso' => false, 'erro' => 'Não foi possível falar com a ' . self::SERVICOS[$conta['servico']] . '.'];
        }

        $resultado['servico'] = $conta['servico'];
        return $resultado;
    }

    // faz a próxima coisa que falta, primeiro as contas desatualizadas e depois os preços do inventário da steam
    // com $utilizadorId é só desse utilizador (ao abrir o perfil), com null é do site todo (tarefa.js)
    public static function proximoPasso(?int $utilizadorId, array $saltar = []): ?array
    {
        $resultado = self::atualizarUma($utilizadorId, $saltar);
        if ($resultado !== null) {
            return $resultado;
        }

        $contaId = Steam::contaComItensPorAvaliar($utilizadorId);
        if ($contaId !== null) {
            Steam::avaliarItens($contaId, 5);
            return ['sucesso' => true, 'servico' => 'inventario'];
        }

        return null;
    }

    // quantas coisas faltam, contas desatualizadas mais itens do inventário sem preço
    public static function faltam(?int $utilizadorId, array $saltar = []): int
    {
        return self::porAtualizar($utilizadorId, $saltar) + Steam::itensPorAvaliar($utilizadorId);
    }
}
