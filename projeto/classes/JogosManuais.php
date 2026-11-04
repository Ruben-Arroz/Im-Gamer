<?php

// a nintendo e a battle.net não têm api para os jogos, por isso o utilizador adiciona-os à mão
class JogosManuais
{
    const MAX_JOGOS = 500;
    // 100000 horas já é mais do que alguém consegue jogar
    const MAX_HORAS = 100000;

    // só devolve a conta se for do utilizador e de uma plataforma sem api
    public static function conta(int $contaId, int $utilizadorId): ?array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Contas_Ligadas WHERE id = :conta AND utilizador_id = :utilizador');
        $stmt->execute(['conta' => $contaId, 'utilizador' => $utilizadorId]);
        $conta = $stmt->fetch();

        if (!$conta || !in_array($conta['servico'], ContasLigadas::MANUAIS)) {
            return null;
        }

        return $conta;
    }

    public static function listar(int $contaId): array
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT * FROM Biblioteca_Jogos WHERE conta_id = :conta ORDER BY nome');
        $stmt->execute(['conta' => $contaId]);

        return $stmt->fetchAll();
    }

    // devolve um erro, ou null se correu bem
    public static function adicionar(int $contaId, int $jogoId, ?int $minutos): ?string
    {
        $jogo = Jogo::buscarPorId($jogoId);
        if (!$jogo) {
            return 'Escolhe o jogo da lista de sugestões.';
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM Biblioteca_Jogos WHERE conta_id = :conta');
        $stmt->execute(['conta' => $contaId]);
        if ($stmt->fetchColumn() >= self::MAX_JOGOS) {
            return 'Já tens ' . self::MAX_JOGOS . ' jogos nesta conta.';
        }

        // o id_externo é o id do jogo, assim a chave única da tabela não deixa pôr o mesmo jogo duas vezes
        $stmt = $pdo->prepare('SELECT id FROM Biblioteca_Jogos WHERE conta_id = :conta AND id_externo = :externo');
        $stmt->execute(['conta' => $contaId, 'externo' => 'jogo' . $jogoId]);
        if ($stmt->fetch()) {
            return 'Esse jogo já está nesta conta.';
        }

        $stmt = $pdo->prepare('INSERT INTO Biblioteca_Jogos (conta_id, jogo_id, id_externo, nome, imagem, minutos_jogados, ultima_sincronizacao)
            VALUES (:conta, :jogo, :externo, :nome, :imagem, :minutos, NOW())');
        $stmt->execute([
            'conta' => $contaId,
            'jogo' => $jogoId,
            'externo' => 'jogo' . $jogoId,
            'nome' => $jogo['nome'],
            'imagem' => $jogo['imagem_capa'],
            'minutos' => $minutos,
        ]);

        self::atualizarTotais($contaId);
        return null;
    }

    public static function tirar(int $contaId, int $linhaId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('DELETE FROM Biblioteca_Jogos WHERE id = :linha AND conta_id = :conta');
        $stmt->execute(['linha' => $linhaId, 'conta' => $contaId]);

        self::atualizarTotais($contaId);
    }

    // os números da conta (jogos e horas) que aparecem no perfil
    private static function atualizarTotais(int $contaId): void
    {
        $pdo = Database::ligar();
        $stmt = $pdo->prepare('UPDATE Contas_Ligadas SET
            num_jogos = (SELECT COUNT(*) FROM Biblioteca_Jogos WHERE conta_id = :conta1),
            minutos_jogados = (SELECT COALESCE(SUM(minutos_jogados), 0) FROM Biblioteca_Jogos WHERE conta_id = :conta2),
            ultima_sincronizacao = NOW()
            WHERE id = :conta3');
        $stmt->execute(['conta1' => $contaId, 'conta2' => $contaId, 'conta3' => $contaId]);
    }
}
