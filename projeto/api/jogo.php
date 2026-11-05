<?php
// um jogo para a app: dados, média e avaliações (com a de quem está a usar a app à parte)
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();
$jogo = Jogo::buscarPorId((int) ($_GET['id'] ?? 0));

if (!$jogo) {
    erro('Este jogo não existe.');
}

$media = Avaliacoes::media($jogo['id']);
$minha = Avaliacoes::doUtilizador($eu, $jogo['id']);

$avaliacoes = [];
foreach (Avaliacoes::doJogo($jogo['id']) as $avaliacao) {
    $avaliacoes[] = [
        'nome' => $avaliacao['nome_utilizador'],
        'pontuacao' => (int) $avaliacao['pontuacao'],
        'comentario' => $avaliacao['comentario'],
        'data' => (new DateTime($avaliacao['data_criacao']))->format('d/m/Y'),
    ];
}

responder([
    'id' => (int) $jogo['id'],
    'nome' => $jogo['nome'],
    'imagem' => $jogo['imagem_capa'],
    'lancamento' => $jogo['data_lancamento'] ? (new DateTime($jogo['data_lancamento']))->format('d/m/Y') : null,
    'descricao' => $jogo['descricao'],
    'produtora' => $jogo['produtora'],
    'editora' => $jogo['editora'],
    'media' => $media['total'] > 0 ? round((float) $media['media'], 1) : null,
    'total_avaliacoes' => (int) $media['total'],
    'minha_pontuacao' => $minha ? (int) $minha['pontuacao'] : null,
    'meu_comentario' => $minha['comentario'] ?? null,
    'avaliacoes' => $avaliacoes,
]);
