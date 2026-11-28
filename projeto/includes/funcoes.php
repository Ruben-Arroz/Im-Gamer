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

// o discord guarda o nome em minúsculas e às vezes as pessoas escrevem o @ antes
function limparDiscord(string $texto): string
{
    return strtolower(ltrim(trim($texto), '@'));
}

// nome de utilizador do discord, 2 a 32 letras minúsculas, números, pontos ou _ (links não)
function discordValido(string $nome): bool
{
    return (bool) preg_match('/^[a-z0-9._]{2,32}$/', $nome);
}

// valor do url que tem de ser uma das chaves da lista (ordens, abas...), senão fica o valor por defeito
function escolhaGet(string $nome, array $lista, string $porDefeito): string
{
    $valor = filter_input(INPUT_GET, $nome, FILTER_UNSAFE_RAW) ?? '';
    if (is_string($valor) && isset($lista[$valor])) {
        return $valor;
    }
    return $porDefeito;
}

// manda o browser para outra página do projeto e para o resto do script
function irPara(string $pagina): void
{
    header('Location: ' . BASE_URL . '/projeto/' . $pagina);
    exit;
}

// link para outra página da lista, sem perder os filtros que estão no url
function urlPagina(int $numero): string
{
    return '?' . http_build_query(array_merge($_GET, ['pagina' => $numero]));
}
