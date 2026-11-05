<?php
// um anúncio de LFG para a app. com POST acao=entrar ou acao=sair entra ou sai da equipa
// as outras ações do dono (fechar, prolongar, apagar, ...) ficam só no site
require_once __DIR__ . '/../includes/api.php';

$eu = utilizadorDaApp();
$nomeEu = Utilizador::buscarPorId($eu)['nome_utilizador'];
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);

if (!Lfg::buscar($id)) {
    erro('Este anúncio não existe ou já foi apagado.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    // a classe Lfg confirma se a pessoa pode entrar (vagas, estado, se já está lá)
    if ($acao === 'entrar') {
        $resultado = Lfg::entrar($id, $eu, $nomeEu);
        if (!$resultado['sucesso']) {
            erro($resultado['erro']);
        }
    } elseif ($acao === 'sair') {
        Lfg::sair($id, $eu, $nomeEu);
    } else {
        erro('Ação desconhecida.');
    }
}

// depois de entrar ou sair devolve o anúncio já atualizado
$anuncio = Lfg::buscar($id);
$participantes = Lfg::participantes($id);
$souDono = (int) $anuncio['utilizador_id'] === $eu;
$participo = in_array($eu, array_map('intval', array_column($participantes, 'utilizador_id')), true);

$lista = [];
foreach ($participantes as $participante) {
    $lista[] = ['id' => (int) $participante['utilizador_id'], 'nome' => $participante['nome_utilizador']];
}

responder(anuncioResumido($anuncio) + [
    'jogo_id' => (int) $anuncio['jogo_id'],
    'descricao' => $anuncio['descricao'],
    'vagas' => (int) $anuncio['vagas'],
    'participantes' => $lista,
    'sou_dono' => $souDono,
    'participo' => $participo,
    'pode_entrar' => !$souDono && !$participo && $anuncio['estado'] === 'aberto' && !Lfg::expirado($anuncio) && $anuncio['participantes'] < $anuncio['vagas'],
    // como no site, o discord do anúncio só aparece ao dono e a quem está na equipa
    'discord' => $souDono || $participo ? $anuncio['discord'] : null,
]);
