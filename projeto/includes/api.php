<?php
// o que é igual em todos os endereços que a app usa (projeto/api/)
require_once __DIR__ . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');

// a resposta vai sempre com o código 200 e os erros vão no campo "erro",
// porque o IIS troca as respostas de erro (404, 403, ...) pelas páginas de erro dele, que não são json
function responder(array $dados): void
{
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

function erro(string $mensagem): void
{
    responder(['erro' => $mensagem]);
}

// a app manda o token no cabeçalho X-Token. devolve o id de quem fez o pedido
function utilizadorDaApp(): int
{
    $id = TokensApi::utilizador($_SERVER['HTTP_X_TOKEN'] ?? '');

    if (!$id) {
        // a app reconhece este campo e volta ao ecrã de login
        responder(['erro' => 'A sessão expirou. Entra outra vez.', 'sem_sessao' => true]);
    }

    return $id;
}

function soPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erro('Pedido inválido.');
    }
}
