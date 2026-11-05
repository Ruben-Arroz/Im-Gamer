<?php
// pesquisa da app: jogadores pelo nome e jogos já guardados no site
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();
$texto = mb_substr(trim($_GET['q'] ?? ''), 0, 100);

if (mb_strlen($texto) < 2) {
    responder(['jogadores' => [], 'jogos' => []]);
}

$jogadores = [];
foreach (Utilizador::pesquisar($texto, $eu, 10) as $jogador) {
    $jogadores[] = [
        'nome' => $jogador['nome_utilizador'],
        'foto' => enderecoUpload($jogador['foto_perfil']),
    ];
}

$jogos = [];
foreach (Jogo::pesquisarNome($texto, 20) as $jogo) {
    $jogos[] = [
        'id' => (int) $jogo['id'],
        'nome' => $jogo['nome'],
        'imagem' => $jogo['imagem_capa'],
    ];
}

responder(['jogadores' => $jogadores, 'jogos' => $jogos]);
