<?php
// recebe os dados de um jogo que o browser foi buscar à rawg, guarda-o na tabela Jogos e devolve o id
// é o browser que fala com a rawg porque o servidor da UA não tem acesso à internet
require_once __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

function responder(array $resposta): void
{
    echo json_encode($resposta);
    exit;
}

if (!Auth::autenticado() || $_SERVER['REQUEST_METHOD'] !== 'POST' || !Auth::tokenValido($_POST['token'] ?? '')) {
    http_response_code(403);
    responder(['erro' => 'Sem permissão.']);
}

// os dados vêm do browser, por isso cada um é validado antes de ir para a bd
function texto(string $campo, int $maximo): ?string
{
    $valor = trim($_POST[$campo] ?? '');
    return $valor === '' ? null : mb_substr($valor, 0, $maximo);
}

// jogos que a rawg não tem: ficam só com o nome, a imagem e o id da steam (se houver), que vêm da cheapshark
if (($_POST['fonte'] ?? '') === 'cheapshark') {
    $nome = texto('nome', 255);
    $steam = (int) ($_POST['steam'] ?? 0);

    if (!$nome) {
        http_response_code(400);
        responder(['erro' => 'Dados do jogo inválidos.']);
    }

    // só imagens por https, as da cheapshark vêm de várias lojas
    $imagem = texto('imagem', 255);
    if ($imagem && !str_starts_with($imagem, 'https://')) {
        $imagem = null;
    }

    $id = Jogo::procurar(0, '', $steam, $nome) ?? Jogo::guardarSimples($nome, $imagem, $steam ?: null);
    responder(['id' => $id]);
}

$rawgId = (int) ($_POST['rawg_id'] ?? 0);
$nome = texto('nome', 255);
$slug = texto('slug', 255);

if ($rawgId <= 0 || !$nome || !$slug || !preg_match('/^[a-z0-9-]+$/', $slug)) {
    http_response_code(400);
    responder(['erro' => 'Dados do jogo inválidos.']);
}

// só aceita imagens da própria rawg
$imagem = texto('imagem', 255);
if ($imagem && !str_starts_with($imagem, 'https://media.rawg.io/')) {
    $imagem = null;
}

$lancamento = texto('lancamento', 10);
if ($lancamento && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $lancamento)) {
    $lancamento = null;
}

$metacritic = (int) ($_POST['metacritic'] ?? 0);

$generos = [];
foreach (array_slice((array) ($_POST['generos'] ?? []), 0, 10) as $genero) {
    if (is_string($genero) && trim($genero) !== '') {
        $generos[] = mb_substr(trim($genero), 0, 50);
    }
}

$plataformas = array_filter(array_slice((array) ($_POST['plataformas'] ?? []), 0, 20), 'is_string');

$dados = [
    'rawg_id' => $rawgId,
    'slug' => $slug,
    'steam' => (int) ($_POST['steam'] ?? 0) ?: null,
    'imagem' => $imagem,
    'lancamento' => $lancamento,
    'descricao' => texto('descricao', 5000),
    'metacritic' => $metacritic >= 1 && $metacritic <= 100 ? $metacritic : null,
    'produtora' => texto('produtora', 255),
    'editora' => texto('editora', 255),
    'um_jogador' => !empty($_POST['um_jogador']) ? 1 : 0,
    'multijogador' => !empty($_POST['multijogador']) ? 1 : 0,
    'cooperativo' => !empty($_POST['cooperativo']) ? 1 : 0,
    'generos' => $generos,
    'plataformas' => $plataformas,
];

// se o jogo já existir (por exemplo vindo da steam) completa-se esse, senão cria-se um novo
$id = Jogo::procurar($rawgId, $slug, (int) $dados['steam'], $nome);

if ($id) {
    // o id da steam só é usado num jogo novo, para não ficar repetido noutra linha
    $dados['steam'] = null;
} else {
    $id = Jogo::guardarDaRawg($rawgId, $nome, $slug, $imagem, $lancamento);
}

Jogo::guardarDetalhesRawg($id, $dados);

responder(['id' => $id]);
