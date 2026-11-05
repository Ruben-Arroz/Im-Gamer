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

// as fotos dos utilizadores estão guardadas só com o caminho dentro de uploads, a app precisa do endereço todo
function enderecoUpload(?string $nome): ?string
{
    if (!$nome) {
        return null;
    }

    $protocolo = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
    return $protocolo . $_SERVER['HTTP_HOST'] . BASE_URL . '/uploads/' . $nome;
}

// a moeda de quem está a usar a app, para os valores das contas
function moedaDe(int $utilizadorId): string
{
    $utilizador = Utilizador::buscarPorId($utilizadorId);
    return $utilizador['moeda'] ?? 'EUR';
}

// os campos de um anúncio que vão para as listas da app
function anuncioResumido(array $anuncio): array
{
    return [
        'id' => (int) $anuncio['id'],
        'jogo' => $anuncio['jogo'],
        'imagem' => $anuncio['imagem_capa'],
        'plataforma' => $anuncio['plataforma'] ?? 'Qualquer plataforma',
        'dono' => $anuncio['nome_utilizador'],
        'no_ar_ate' => formatarDia($anuncio['data_fim']),
        'estado' => Lfg::estado($anuncio),
        'saiu_do_ar' => Lfg::expirado($anuncio),
    ];
}

function soPost(): void
{
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        erro('Pedido inválido.');
    }
}
