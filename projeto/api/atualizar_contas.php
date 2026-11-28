<?php
// atualiza as contas steam, xbox e gog de um utilizador e os preços do inventário, chamado pelo atualizar_contas.js ao abrir o perfil
// o js vai chamando enquanto faltar alguma coisa, um passo de cada vez
require_once __DIR__ . '/../includes/inicio.php';

header('Content-Type: application/json');

// vai sempre com o código 200 pq o IIS troca as respostas de erro pelas páginas dele
function responder(array $resposta): void
{
    echo json_encode($resposta);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::autenticado() || !Auth::formularioValido()) {
    responder(['erro' => 'Sem permissão.']);
}

$utilizadorId = (int) filter_input(INPUT_POST, 'utilizador', FILTER_VALIDATE_INT);

// plataformas que o perfil já tentou nesta visita, só ficam as que existem na lista
$saltar = [];
foreach (filter_input(INPUT_POST, 'saltar', FILTER_UNSAFE_RAW, FILTER_REQUIRE_ARRAY) ?: [] as $servico) {
    if (isset(ContasLigadas::SERVICOS[$servico])) {
        $saltar[] = $servico;
    }
}

// a steam com muitos jogos demora, e se a pessoa sair da página o passo acaba na mesma
set_time_limit(120);
ignore_user_abort(true);

$resultado = ContasLigadas::proximoPasso($utilizadorId, $saltar);

if ($resultado === null) {
    responder(['faltam' => 0]);
}

if ($resultado['servico'] === 'inventario') {
    $nome = 'Inventário da Steam';
} else {
    $nome = ContasLigadas::SERVICOS[$resultado['servico']];
    $saltar[] = $resultado['servico'];
}

responder([
    'chave' => $resultado['servico'],
    'servico' => $nome,
    'sucesso' => $resultado['sucesso'],
    'faltam' => ContasLigadas::faltam($utilizadorId, $saltar),
]);
