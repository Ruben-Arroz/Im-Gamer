<?php
// a epic não entra aqui, atualiza-se no site com o login da epic (contas.php)
// vai buscar os dados das contas steam, xbox e gog e as taxas de câmbio e guarda tudo na bd
// por agora corre no pc (o servidor da UA não tem acesso à internet) php projeto/sincronizar.php

if (php_sapi_name() !== 'cli') {
    exit('Este script só corre na linha de comandos.');
}

require_once __DIR__ . '/includes/bootstrap.php';

Steam::$mostrarProgresso = true;

echo 'Taxas de câmbio: ' . (Moeda::atualizarTaxas() ? 'atualizadas' : 'falhou') . "\n";

$pdo = Database::ligar();
$contas = $pdo->query("SELECT * FROM Contas_Ligadas WHERE servico IN ('steam', 'xbox', 'gog') ORDER BY servico, id")->fetchAll();

foreach ($contas as $conta) {
    echo "\n" . ContasLigadas::SERVICOS[$conta['servico']] . ': ' . $conta['identificador'] . "\n";

    // se a ligação à bd ou à internet falhar, mostra o erro e passa à conta seguinte
    try {
        if ($conta['servico'] === 'steam') {
            $resultado = Steam::sincronizar($conta);
        } elseif ($conta['servico'] === 'gog') {
            $resultado = Gog::sincronizar($conta);
        } else {
            $resultado = Xbox::sincronizar($conta);
        }
    } catch (Exception $erro) {
        $resultado = ['sucesso' => false, 'erro' => 'Falhou (' . $erro->getMessage() . '). Vê se a VPN está ligada e tenta outra vez.'];
    }

    if ($resultado['sucesso']) {
        echo '  Conta atualizada: ' . $resultado['jogos'] . " jogos\n";
    } else {
        echo '  ERRO: ' . $resultado['erro'] . "\n";
    }
}

echo "\nFeito.\n";
