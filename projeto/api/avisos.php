<?php
// a app pergunta isto de tempos a tempos para saber se há convites ou avisos novos e mostrar uma notificação
// não marca nada como lido: isso só acontece quando se abrem os convites
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
