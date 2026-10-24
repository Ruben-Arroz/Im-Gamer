// catálogo de promoções da CheapShark, pedido pelo browser
// (o servidor da UA não deixa fazer pedidos para fora)
// os filtros vêm do url (?q=...&ordem=...), que é o que o formulário envia
// os cartões e a paginação estão no cartoes.js

const POR_PAGINA = 60;

const catalogo = document.querySelector('#catalogo');
const grelha = document.querySelector('#promocoes');
const aviso = document.querySelector('#promocoes-aviso');
const resumo = document.querySelector('#catalogo-resumo');
const todasLojas = catalogo.dataset.todas.split(',');

const parametros = new URLSearchParams(window.location.search);
// na cheapshark as páginas começam no 0, no url começam no 1
const pagina = Math.max(1, parseInt(parametros.get('pagina') || '1'));

configurarCartoes(catalogo);

// escolhe as lojas: as que foram marcadas, ou então as do tipo escolhido
function lojasParaPedir() {
    const marcadas = parametros.getAll('loja[]');

    if (marcadas.length > 0) {
        return marcadas;
    }

    const tipo = parametros.get('tipo');

    if (tipo === 'oficiais') {
        return lojasOficiais;
    }

    if (tipo === 'chaves') {
        return todasLojas.filter(function (id) {
            return !lojasOficiais.includes(id);
        });
    }

    return todasLojas;
}

function montarUrl() {
    let url = CHEAPSHARK + '/deals?onSale=1&pageSize=' + POR_PAGINA + '&pageNumber=' + (pagina - 1);
    url += '&storeID=' + lojasParaPedir().join(',');
    url += '&sortBy=' + encodeURIComponent(parametros.get('ordem') || 'Deal Rating');

    if (parametros.get('q')) {
        url += '&title=' + encodeURIComponent(parametros.get('q'));
    }

    // o preço é escrito na moeda do utilizador mas a cheapshark quer dólares
    if (parametros.get('preco_min')) {
        url += '&lowerPrice=' + Math.floor(parametros.get('preco_min') / taxa);
    }

    if (parametros.get('preco_max')) {
        url += '&upperPrice=' + Math.ceil(parametros.get('preco_max') / taxa);
    }

    if (parametros.get('metacritic')) {
        url += '&metacritic=' + parseInt(parametros.get('metacritic'));
    }

    if (parametros.get('steam')) {
        url += '&steamRating=' + parseInt(parametros.get('steam'));
    }

    if (parametros.has('aaa')) {
        url += '&AAA=1';
    }

    if (parametros.has('steamworks')) {
        url += '&steamworks=1';
    }

    return url;
}

async function carregarPromocoes() {
    aviso.textContent = 'A carregar...';

    try {
        const resposta = await fetch(montarUrl());
        const promocoes = await resposta.json();
        // a cheapshark diz quantas páginas há num cabeçalho da resposta
        const totalPaginas = parseInt(resposta.headers.get('X-Total-Page-Count') || '0');

        aviso.textContent = promocoes.length === 0 ? 'Nenhuma promoção encontrada com estes filtros.' : '';

        if (totalPaginas > 1) {
            resumo.textContent = 'Página ' + pagina + ' de ' + totalPaginas;
        }

        for (const promo of promocoes) {
            grelha.appendChild(criarCartaoPromocao(promo));
        }

        mostrarPaginacao(document.querySelector('#paginacao'), parametros, pagina, totalPaginas);
    } catch (erro) {
        aviso.textContent = 'Não foi possível carregar as promoções. Tenta mais tarde.';
    }
}

async function iniciar() {
    await carregarLojas();
    carregarPromocoes();
}

iniciar();
