<?php
// convites da app, com POST aceita ou recusa
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    if ($acao !== 'aceitar' && $acao !== 'recusar') {
        erro('Ação desconhecida.');
    }

    $nomeEu = Utilizador::buscarPorId($eu)['nome_utilizador'];
    Convites::responder((int) ($_POST['convite_id'] ?? 0), $eu, $nomeEu, $acao === 'aceitar');
}

$estados = ['pendente' => 'À espera de resposta', 'aceite' => 'Aceite', 'recusado' => 'Recusado'];

$recebidos = [];
foreach (Convites::recebidos($eu) as $convite) {
    $recebidos[] = [
        'id' => (int) $convite['id'],
        'de' => $convite['remetente'],
        'jogo' => $convite['jogo'],
        'jogo_id' => $convite['jogo_id'] ? (int) $convite['jogo_id'] : null,
        'mensagem' => $convite['mensagem'],
        'discord' => $convite['discord'],
        'pendente' => $convite['estado'] === 'pendente',
        'estado' => $estados[$convite['estado']],
        'data' => (new DateTime($convite['data_criacao']))->format('d/m/Y H:i'),
    ];
}

$enviados = [];
foreach (Convites::enviados($eu) as $convite) {
    $enviados[] = [
        'id' => (int) $convite['id'],
        'para' => $convite['destinatario'],
        'jogo' => $convite['jogo'],
        'estado' => $estados[$convite['estado']],
        'data' => (new DateTime($convite['data_criacao']))->format('d/m/Y H:i'),
    ];
}

$avisos = [];
foreach (Notificacoes::listar($eu, 30) as $aviso) {
    $avisos[] = [
        'id' => (int) $aviso['id'],
        'texto' => $aviso['conteudo'],
        'novo' => !$aviso['lida'],
        'data' => (new DateTime($aviso['data_criacao']))->format('d/m/Y H:i'),
    ];
}

// como no site, os avisos ficam lidos quando se abrem os convites
Notificacoes::marcarLidas($eu);

responder(['recebidos' => $recebidos, 'enviados' => $enviados, 'avisos' => $avisos]);
