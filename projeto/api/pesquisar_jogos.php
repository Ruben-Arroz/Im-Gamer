<?php
// jogos já guardados no site, para as sugestões dos formulários quando a rawg não responde
require_once __DIR__ . '/../includes/api.php';

$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

if (mb_strlen($texto) < 2) {
    responder(['jogos' => []]);
}

$jogos = [];
foreach (Jogo::pesquisarNome($texto, 6) as $jogo) {
    $jogos[] = [
        'id' => (int) $jogo['id'],
        'nome' => $jogo['nome'],
        'ano' => $jogo['data_lancamento'] ? substr($jogo['data_lancamento'], 0, 4) : null,
    ];
}

responder(['jogos' => $jogos]);
