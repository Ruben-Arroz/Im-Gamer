<?php
// faz uma coisa que esteja por atualizar no site, chamado pelo tarefa.js nas páginas de quem tem sessão
// assim as contas de todos, as taxas de câmbio e os lançamentos atualizam-se sozinhos com as visitas
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

// se a pessoa mudar de página a meio, o servidor acaba o que estava a fazer
set_time_limit(120);
ignore_user_abort(true);

// uma vez por dia as taxas de câmbio e as listas de lançamentos da steam
if (Moeda::reservarAtualizacao()) {
    responder(['feito' => 'taxas', 'sucesso' => Moeda::atualizarTaxas()]);
}

if (Lancamentos::reservarAtualizacao()) {
    Lancamentos::sincronizar();
    responder(['feito' => 'lancamentos']);
}

// depois a conta mais desatualizada do site, ou os preços de um inventário
$resultado = ContasLigadas::proximoPasso(null);
if ($resultado === null) {
    responder(['feito' => 'nada']);
}

responder(['feito' => $resultado['servico'], 'sucesso' => $resultado['sucesso']]);
