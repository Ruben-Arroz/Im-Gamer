<?php

class Moeda
{
    const SIMBOLOS = ['EUR' => '€', 'USD' => '$', 'GBP' => '£', 'BRL' => 'R$'];

    private static array $taxas = [];

    // vai buscar as taxas do dia a partir do euro (dados do banco central europeu)
    public static function atualizarTaxas(): bool
    {
        $outras = implode(',', array_diff(array_keys(self::SIMBOLOS), ['EUR']));
        $dados = Api::pedir('https://api.frankfurter.dev/v1/latest?base=EUR&symbols=' . $outras);

        if (!isset($dados['rates'])) {
            return false;
        }

        $pdo = Database::ligar();
        $stmt = $pdo->prepare('INSERT INTO Taxas_Cambio (moeda, taxa) VALUES (:moeda, :taxa)
            ON DUPLICATE KEY UPDATE taxa = :taxa2, data_atualizacao = NOW()');

        foreach ($dados['rates'] as $moeda => $taxa) {
            $stmt->execute(['moeda' => $moeda, 'taxa' => $taxa, 'taxa2' => $taxa]);
        }

        return true;
    }

    private static function taxa(string $moeda): ?float
    {
        if ($moeda === 'EUR') {
            return 1;
        }

        // só lê as taxas da bd uma vez por página
        if (!self::$taxas) {
            $pdo = Database::ligar();
            foreach ($pdo->query('SELECT moeda, taxa FROM Taxas_Cambio') as $linha) {
                self::$taxas[$linha['moeda']] = (float) $linha['taxa'];
            }
        }

        return self::$taxas[$moeda] ?? null;
    }

    // os valores estão guardados em euros, aqui passam para a moeda do utilizador
    public static function formatar(float $valorEuros, string $moeda): string
    {
        $taxa = self::taxa($moeda);

        // se ainda não houver taxa para essa moeda mostra em euros
        if ($taxa === null) {
            $taxa = 1;
            $moeda = 'EUR';
        }

        return number_format($valorEuros * $taxa, 2, ',', ' ') . ' ' . self::SIMBOLOS[$moeda];
    }
}
