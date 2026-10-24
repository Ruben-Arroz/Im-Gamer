// catálogo de promoções da CheapShark, pedido pelo browser
// (o servidor da UA não deixa fazer pedidos para fora)
// os filtros vêm do url (?q=...&ordem=...), que é o que o formulário envia

const API = 'https://www.cheapshark.com/api/1.0';
const POR_PAGINA = 60;

const catalogo = document.querySelector('#catalogo');
const grelha = document.querySelector('#promocoes');
const aviso = document.querySelector('#promocoes-aviso');
const paginacao = document.querySelector('#paginacao');
const resumo = document.querySelector('#catalogo-resumo');

// dados que o php pôs na página
const simbolo = catalogo.dataset.simbolo;
const taxa = parseFloat(catalogo.dataset.taxa);
const lojasOficiais = catalogo.dataset.oficiais.split(',');
const todasLojas = catalogo.dataset.todas.split(',');

const parametros = new URLSearchParams(window.location.search);
const pagina = Math.max(0, parseInt(parametros.get('pagina') || '1') - 1);

let nomesLojas = {};

// as avaliações da steam vêm em inglês, ficam com os nomes que a steam usa em português
const AVALIACOES = {
    'Overwhelmingly Positive': 'Extremamente positivas',
    'Very Positive': 'Muito positivas',
    'Positive': 'Positivas',
    'Mostly Positive': 'Maioritariamente positivas',
    'Mixed': 'Variadas',
    'Mostly Negative': 'Maioritariamente negativas',
    'Negative': 'Negativas',
    'Very Negative': 'Muito negativas',
    'Overwhelmingly Negative': 'Extremamente negativas',
};

function formatarPreco(dolares) {
    const valor = parseFloat(dolares) * taxa;

    if (valor === 0) {
        return 'Grátis';
    }

    return valor.toFixed(2).replace('.', ',') + ' ' + simbolo;
}

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
    let url = API + '/deals?onSale=1&pageSize=' + POR_PAGINA + '&pageNumber=' + pagina;
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

async function carregarLojas() {
    const resposta = await fetch(API + '/stores');
    const dados = await resposta.json();

    for (const loja of dados) {
        nomesLojas[loja.storeID] = loja.storeName;
    }
}

async function carregarPromocoes() {
    aviso.textContent = 'A carregar...';

    try {
        const resposta = await fetch(montarUrl());
        const promocoes = await resposta.json();
        // a cheapshark diz quantas páginas há num cabeçalho da resposta
        const totalPaginas = parseInt(resposta.headers.get('X-Total-Page-Count') || '0');

        aviso.textContent = promocoes.length === 0 ? 'Nenhuma promoção encontrada com estes filtros.' : '';

        for (const promo of promocoes) {
            grelha.appendChild(criarCartao(promo));
        }

        mostrarPaginacao(totalPaginas);
    } catch (erro) {
        aviso.textContent = 'Não foi possível carregar as promoções. Tenta mais tarde.';
    }
}

function criarCartao(promo) {
    const cartao = document.createElement('a');
    cartao.className = 'cartao-jogo';
    cartao.href = 'https://www.cheapshark.com/redirect?dealID=' + promo.dealID;
    cartao.target = '_blank';
    cartao.rel = 'noopener';

    // a imagem da steam é maior que a miniatura da cheapshark, se falhar usa a miniatura
    const imagem = document.createElement('img');
    imagem.alt = '';
    imagem.loading = 'lazy';
    imagem.src = promo.steamAppID ? 'https://cdn.akamai.steamstatic.com/steam/apps/' + promo.steamAppID + '/header.jpg' : promo.thumb;
    imagem.onerror = function () {
        imagem.onerror = null;
        imagem.src = promo.thumb;
    };

    const titulo = document.createElement('p');
    titulo.className = 'cartao-titulo';
    titulo.textContent = promo.title;

    const loja = document.createElement('p');
    loja.className = 'cartao-loja';
    loja.textContent = nomesLojas[promo.storeID] || 'Loja';

    if (!lojasOficiais.includes(promo.storeID)) {
        const etiqueta = document.createElement('span');
        etiqueta.className = 'etiqueta-chave';
        etiqueta.textContent = 'chave';
        loja.appendChild(etiqueta);
    }

    // nota e avaliações, só se existirem
    const extra = [];
    if (promo.metacriticScore !== '0') {
        extra.push('Metacritic ' + promo.metacriticScore);
    }
    if (promo.steamRatingText) {
        extra.push(AVALIACOES[promo.steamRatingText] || promo.steamRatingText);
    }

    const notas = document.createElement('p');
    notas.className = 'cartao-loja';
    notas.textContent = extra.join(' · ');

    const desconto = document.createElement('span');
    desconto.className = 'desconto';
    desconto.textContent = '-' + Math.round(promo.savings) + '%';

    const precoNormal = document.createElement('s');
    precoNormal.textContent = formatarPreco(promo.normalPrice);

    const precoPromo = document.createElement('strong');
    precoPromo.textContent = formatarPreco(promo.salePrice);

    const precos = document.createElement('p');
    precos.className = 'cartao-precos';
    precos.append(desconto, precoNormal, precoPromo);

    cartao.append(imagem, titulo, loja, notas, precos);
    return cartao;
}

// link para outra página mantendo os filtros
function urlPagina(numero) {
    const novos = new URLSearchParams(parametros);
    novos.set('pagina', numero);
    return '?' + novos.toString();
}

function linkPagina(texto, numero) {
    const link = document.createElement('a');
    link.textContent = texto;
    link.href = urlPagina(numero);
    paginacao.appendChild(link);
}

function mostrarPaginacao(totalPaginas) {
    const atual = pagina + 1;

    if (totalPaginas <= 1) {
        return;
    }

    resumo.textContent = 'Página ' + atual + ' de ' + totalPaginas;

    if (atual > 1) {
        linkPagina('Anterior', atual - 1);
    }

    // só mostra algumas páginas à volta da atual, senão podiam ser centenas de números
    const inicio = Math.max(1, atual - 2);
    const fim = Math.min(totalPaginas, atual + 2);

    if (inicio > 1) {
        linkPagina('1', 1);
    }

    for (let numero = inicio; numero <= fim; numero++) {
        if (numero === atual) {
            const span = document.createElement('span');
            span.className = 'pagina-atual';
            span.textContent = numero;
            paginacao.appendChild(span);
        } else {
            linkPagina(numero, numero);
        }
    }

    if (fim < totalPaginas) {
        linkPagina(totalPaginas, totalPaginas);
    }

    if (atual < totalPaginas) {
        linkPagina('Seguinte', atual + 1);
    }
}

async function iniciar() {
    // se as lojas falharem as promoções aparecem na mesma, só sem o nome da loja
    try {
        await carregarLojas();
    } catch (erro) {
        nomesLojas = {};
    }

    carregarPromocoes();
}

iniciar();
