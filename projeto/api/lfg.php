<?php
// lista do LFG para a app, com os mesmos filtros do site
require_once __DIR__ . '/../includes/api.php';

const POR_PAGINA = 20;

$eu = utilizadorDaApp();

$aba = isset(Lfg::ABAS[$_GET['aba'] ?? '']) ? $_GET['aba'] : 'proximos';
$filtros = [
    'jogo' => mb_substr(trim($_GET['jogo'] ?? ''), 0, 100),
    'plataforma' => 0,
    'ordem' => 'recentes',
    // como no site, o "só com vagas" está ligado por defeito
    'vagas' => ($_GET['vagas'] ?? '1') !== '0',
];
$pagina = max(1, (int) ($_GET['pagina'] ?? 1));

$resultado = Lfg::pesquisar($aba, $filtros, $eu, $pagina, POR_PAGINA);

$anuncios = [];
foreach ($resultado['anuncios'] as $anuncio) {
    $anuncios[] = anuncioResumido($anuncio);
}

responder([
    'total' => $resultado['total'],
    'paginas' => max(1, (int) ceil($resultado['total'] / POR_PAGINA)),
    'anuncios' => $anuncios,
]);
