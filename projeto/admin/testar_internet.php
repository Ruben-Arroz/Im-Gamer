<?php
// página só para testar se o servidor da UA já chega às apis (apagar depois de testar)
// não mostra as chaves, só se cada pedido correu bem e quanto tempo demorou
require_once __DIR__ . '/../includes/inicio.php';

Admin::verificar();

function testar(string $url, array $cabecalhos = []): array
{
    $curl = curl_init($url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($curl, CURLOPT_TIMEOUT, 20);
    curl_setopt($curl, CURLOPT_USERAGENT, 'ImGamer/1.0 (projeto escolar ESAN UA)');
    curl_setopt($curl, CURLOPT_HTTPHEADER, $cabecalhos);

    $inicio = microtime(true);
    $resposta = curl_exec($curl);

    return [
        'codigo' => curl_getinfo($curl, CURLINFO_HTTP_CODE),
        'tempo' => round((microtime(true) - $inicio) * 1000),
        'erro' => $resposta === false ? curl_error($curl) : '',
    ];
}

$testes = [
    'Steam (sem chave)' => ['https://api.steampowered.com/ISteamWebAPIUtil/GetServerInfo/v1/'],
    'Steam (com a chave)' => ['https://api.steampowered.com/ISteamUser/GetPlayerSummaries/v2/?key=' . STEAM_API_KEY . '&steamids=76561197960435530'],
    'Loja da Steam' => ['https://store.steampowered.com/api/appdetails?cc=pt&filters=price_overview&appids=730'],
    'RAWG' => ['https://api.rawg.io/api/games?page_size=1&key=' . RAWG_API_KEY],
    'CheapShark' => ['https://www.cheapshark.com/api/1.0/deals?pageSize=1'],
    'Xbox (OpenXBL)' => ['https://xbl.io/api/v2/account', ['X-Authorization: ' . OPENXBL_API_KEY, 'Accept: application/json']],
    'Loja da Microsoft' => ['https://displaycatalog.mp.microsoft.com/v7.0/products/lookup?alternateId=XboxTitleId&value=1717113201&market=PT&languages=pt-PT'],
    'GOG' => ['https://api.gog.com/products/1207658924'],
    'Taxas de câmbio' => ['https://api.frankfurter.dev/v1/latest?base=EUR&symbols=USD'],
];

$resultados = [];
foreach ($testes as $nome => $teste) {
    $resultados[$nome] = testar($teste[0], $teste[1] ?? []);
}

// o tempo máximo de um pedido, antes e depois de pedir mais (o atualizar_contas.php pede 120 s)
$limiteAntes = ini_get('max_execution_time');
set_time_limit(120);
$limiteDepois = ini_get('max_execution_time');

// com ?esperar=40 a página fica 40 s parada, para ver se o servidor a corta antes
$esperar = (int) filter_input(INPUT_GET, 'esperar', FILTER_VALIDATE_INT);
if ($esperar > 0 && $esperar <= 110) {
    sleep($esperar);
}

$tituloPagina = 'Testar internet';
require_once __DIR__ . '/../includes/header.php';
?>

<section class="admin">
    <h1>Testar internet do servidor</h1>

    <ul class="lista-admin">
        <?php foreach ($resultados as $nome => $resultado): ?>
            <li>
                <div class="admin-dados">
                    <span><?= htmlspecialchars($nome) ?></span>
                    <span class="texto-dim">
                        <?= $resultado['codigo'] === 200 ? 'ok' : 'falhou' ?>
                        · código <?= $resultado['codigo'] ?> · <?= $resultado['tempo'] ?> ms
                        <?php if ($resultado['erro']): ?>
                            · <?= htmlspecialchars($resultado['erro']) ?>
                        <?php endif; ?>
                    </span>
                </div>
            </li>
        <?php endforeach; ?>
    </ul>

    <p>Tempo máximo de um pedido: <?= htmlspecialchars($limiteAntes) ?> s, depois do set_time_limit(120) fica <?= htmlspecialchars($limiteDepois) ?> s.</p>
    <?php if ($esperar > 0): ?>
        <p>A página esperou <?= $esperar ?> s e chegou ao fim.</p>
    <?php else: ?>
        <p class="texto-dim">Para ver se o servidor corta pedidos compridos, abre <a href="?esperar=40">esta página com 40 s de espera</a> e depois <a href="?esperar=90">com 90 s</a>.</p>
    <?php endif; ?>
</section>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
