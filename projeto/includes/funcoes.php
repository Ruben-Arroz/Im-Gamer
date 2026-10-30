<?php

function formatarHoras(int $minutos): string
{
    return number_format($minutos / 60, 0, ',', ' ') . ' h';
}

// "1 jogo" e "2 jogos"
function contarJogos(int $quantidade): string
{
    return $quantidade . ($quantidade === 1 ? ' jogo' : ' jogos');
}

// largura das barras das estatísticas, em percentagem do maior valor da lista
function percentagem(float $valor, float $maximo): int
{
    if ($maximo <= 0) {
        return 0;
    }

    return (int) round($valor / $maximo * 100);
}

// data e hora dos anúncios de LFG, por exemplo "sáb, 01/11 às 21:00"
function formatarDataEvento(string $data): string
{
    $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $momento = new DateTime($data);

    return $dias[(int) $momento->format('w')] . ', ' . $momento->format('d/m') . ' às ' . $momento->format('H:i');
}
