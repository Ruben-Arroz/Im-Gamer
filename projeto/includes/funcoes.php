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

// dia com o dia da semana, por exemplo "sáb, 15/11" (até quando os anúncios de LFG ficam no ar)
function formatarDia(string $data): string
{
    $dias = ['dom', 'seg', 'ter', 'qua', 'qui', 'sex', 'sáb'];
    $dia = new DateTime($data);

    return $dias[(int) $dia->format('w')] . ', ' . $dia->format('d/m');
}
