<?php
// avaliar um jogo pela app (1 a 5 e um comentário opcional) ou, com acao=apagar, tirar a avaliação
// as regras são as mesmas do jogo.php do site
require_once __DIR__ . '/../includes/api.php';

soPost();
$eu = utilizadorDaApp();

$jogo = Jogo::buscarPorId((int) ($_POST['jogo_id'] ?? 0));
if (!$jogo) {
    erro('Este jogo não existe.');
}

if (($_POST['acao'] ?? '') === 'apagar') {
    Avaliacoes::apagar($eu, $jogo['id']);
    responder(['ok' => true]);
}

$pontuacao = (int) ($_POST['pontuacao'] ?? 0);
$comentario = trim($_POST['comentario'] ?? '');

if ($pontuacao < 1 || $pontuacao > 5) {
    erro('Escolhe de 1 a 5 estrelas.');
}

if (mb_strlen($comentario) > 1000) {
    erro('O comentário pode ter no máximo 1000 letras.');
}

Avaliacoes::guardar($eu, $jogo['id'], $pontuacao, $comentario === '' ? null : $comentario);

responder(['ok' => true]);
