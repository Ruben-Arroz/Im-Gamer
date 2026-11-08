<?php
// a app pergunta isto de 30 em 30 s para as notificações
// não marca nada como lido, isso só acontece ao abrir os convites
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();

$convites = [];
foreach (Convites::recebidos($eu) as $convite) {
    if ($convite['estado'] === 'pendente') {
        $convites[] = [
            'id' => (int) $convite['id'],
            'de' => $convite['remetente'],
            'jogo' => $convite['jogo'],
        ];
    }
}

$avisos = [];
foreach (Notificacoes::listar($eu, 30) as $aviso) {
    if (!$aviso['lida']) {
        $avisos[] = ['id' => (int) $aviso['id'], 'texto' => $aviso['conteudo']];
    }
}

responder(['convites' => $convites, 'avisos' => $avisos]);
