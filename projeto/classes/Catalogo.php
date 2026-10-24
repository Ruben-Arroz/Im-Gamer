<?php

// listas fixas usadas no catálogo, nos lançamentos e na página inicial
class Catalogo
{
    // lojas ativas na cheapshark: id => [nome, se é a loja oficial da plataforma]
    // as que não são oficiais vendem chaves para ativar noutra plataforma (steam, epic, ...)
    const LOJAS = [
        1 => ['Steam', true],
        7 => ['GOG', true],
        13 => ['Ubisoft Store', true],
        25 => ['Epic Games Store', true],
        2 => ['GamersGate', false],
        3 => ['Green Man Gaming', false],
        11 => ['Humble Store', false],
        15 => ['Fanatical', false],
        21 => ['WinGameStore', false],
        23 => ['GameBillet', false],
        27 => ['Gamesplanet', false],
        28 => ['Gamesload', false],
        30 => ['IndieGala', false],
        35 => ['DreamGame', false],
    ];

    const ORDENS = [
        'Deal Rating' => 'Melhores promoções',
        'Savings' => 'Maior desconto',
        'Price' => 'Menor preço',
        'Metacritic' => 'Nota Metacritic',
        'Reviews' => 'Avaliações da Steam',
        'Release' => 'Lançamento',
        'Recent' => 'Promoções mais recentes',
        'Title' => 'Nome',
    ];

    // ids das plataformas e dos géneros na rawg
    const PLATAFORMAS = [
        1 => 'PC',
        2 => 'PlayStation',
        3 => 'Xbox',
        7 => 'Nintendo',
        5 => 'Mac',
        6 => 'Linux',
        4 => 'iOS',
        8 => 'Android',
    ];

    const GENEROS = [
        4 => 'Ação',
        3 => 'Aventura',
        5 => 'RPG',
        2 => 'Tiros',
        10 => 'Estratégia',
        14 => 'Simulação',
        1 => 'Corrida',
        15 => 'Desporto',
        6 => 'Luta',
        83 => 'Plataformas',
        7 => 'Puzzle',
        11 => 'Arcade',
        40 => 'Casual',
        51 => 'Indie',
        59 => 'Multijogador massivo',
        19 => 'Família',
        28 => 'Tabuleiro',
        17 => 'Cartas',
        34 => 'Educativos',
    ];

    // a rawg dá os géneros em inglês, isto é para os mostrar em português nos cartões
    const GENEROS_INGLES = [
        'Action' => 'Ação', 'Adventure' => 'Aventura', 'RPG' => 'RPG', 'Shooter' => 'Tiros',
        'Strategy' => 'Estratégia', 'Simulation' => 'Simulação', 'Racing' => 'Corrida', 'Sports' => 'Desporto',
        'Fighting' => 'Luta', 'Platformer' => 'Plataformas', 'Puzzle' => 'Puzzle', 'Arcade' => 'Arcade',
        'Casual' => 'Casual', 'Indie' => 'Indie', 'Massively Multiplayer' => 'Multijogador massivo',
        'Family' => 'Família', 'Board Games' => 'Tabuleiro', 'Card' => 'Cartas', 'Educational' => 'Educativos',
    ];

    const PERIODOS = [7 => 'Uma semana', 30 => 'Um mês', 90 => 'Três meses', 365 => 'Um ano'];

    public static function lojasOficiais(): array
    {
        $oficiais = [];

        foreach (self::LOJAS as $id => $loja) {
            if ($loja[1]) {
                $oficiais[] = $id;
            }
        }

        return $oficiais;
    }

    // os preços da cheapshark vêm em dólares: devolve a moeda a mostrar e quanto vale 1 dólar nessa moeda
    public static function conversaoDolar(string $moeda): array
    {
        $taxaDolar = Moeda::taxa('USD');
        $taxaMoeda = Moeda::taxa($moeda);

        // sem taxas na bd mostra em dólares
        if (!$taxaDolar || !$taxaMoeda) {
            return ['moeda' => 'USD', 'fator' => 1];
        }

        return ['moeda' => $moeda, 'fator' => $taxaMoeda / $taxaDolar];
    }
}
