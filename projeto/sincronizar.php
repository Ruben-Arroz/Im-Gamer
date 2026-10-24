<?php
// vai buscar os dados das contas steam e as taxas de câmbio e guarda tudo na bd
// por agora corre no pc (o servidor da UA não tem acesso à internet): php projeto/sincronizar.php

if (php_sapi_name() !== 'cli') {
    exit('Este script só corre na linha de comandos.');
}

require_once __DIR__ . '/includes/bootstrap.php';

echo 'Taxas de câmbio: ' . (Moeda::atualizarTaxas() ? 'atualizadas' : 'falhou') . "\n";

$pdo = Database::ligar();
$contas = $pdo->query("SELECT * FROM Contas_Ligadas WHERE servico = 'steam'")->fetchAll();

foreach ($contas as $conta) {
    echo 'Steam ' . $conta['identificador'] . ' ... ';
    $resultado = Steam::sincronizar($conta);

    if ($resultado['sucesso']) {
        echo $resultado['jogos'] . ' jogos, ' . $resultado['itens'] . " itens no inventário\n";
    } else {
        echo $resultado['erro'] . "\n";
    }
}

echo "Feito.\n";
