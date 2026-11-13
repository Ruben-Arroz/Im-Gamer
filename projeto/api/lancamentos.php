<?php
// última lista de lançamentos da steam que ficou guardada (para quando a rawg não responde)
// os campos têm os mesmos nomes da rawg, assim o js usa os mesmos cartões
require_once __DIR__ . '/../includes/api.php';

$tipo = ($_GET['tipo'] ?? '') === 'breve' ? 'breve' : 'recentes';

$jogos = [];
$atualizado = null;

foreach (Lancamentos::listar($tipo, 40) as $jogo) {
    $atualizado = $jogo['data_sincronizacao'];

    $generos = [];
    foreach (Jogo::generosDoJogo($jogo['id']) as $genero) {
        $generos[] = ['name' => $genero];
    }

    $jogos[] = [
        'id_site' => (int) $jogo['id'],
        'name' => $jogo['nome'],
        'background_image' => $jogo['imagem_capa'],
        'released' => $jogo['data_lancamento'],
        'steam' => $jogo['steam_app_id'] ? (int) $jogo['steam_app_id'] : null,
        'parent_platforms' => [['platform' => ['name' => 'PC']]],
        'genres' => $generos,
    ];
}

responder([
    'atualizado' => $atualizado ? (new DateTime($atualizado))->format('d/m/Y \à\s H:i') : null,
    'jogos' => $jogos,
]);
