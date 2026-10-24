<?php
// vai buscar os dados das contas steam e as taxas de câmbio e guarda tudo na bd
// por agora corre no pc (o servidor da UA não tem acesso à internet) php projeto/sincronizar.php

if (php_sapi_name() !== 'cli') {
    exit('Este script só corre na linha de comandos.');
}

require_once __DIR__ . '/includes/bootstrap.php';

Steam::$mostrarProgresso = true;

echo 'Taxas de câmbio: ' . (Moeda::atualizarTaxas() ? 'atualizadas' : 'falhou') . "\n";

$pdo = Database::ligar();
$contas = $pdo->query("SELECT * FROM Contas_Ligadas WHERE servico = 'steam'")->fetchAll();

foreach ($contas as $conta) {
    echo "\nSteam: " . $conta['identificador'] . "\n";

    // se a ligação à bd ou à internet falhar, mostra o erro e passa à conta seguinte
    try {
        $resultado = Steam::sincronizar($conta);
    } catch (Exception $erro) {
        $resultado = ['sucesso' => false, 'erro' => 'Falhou (' . $erro->getMessage() . '). Vê se a VPN está ligada e tenta outra vez.'];
    }

    if ($resultado['sucesso']) {
        echo '  Conta atualizada: ' . $resultado['jogos'] . ' jogos, ' . $resultado['itens'] . " itens no inventário\n";
    } else {
        echo '  ERRO: ' . $resultado['erro'] . "\n";
    }
}

echo "\nFeito.\n";
