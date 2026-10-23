// as promoções vêm da CheapShark e são pedidas pelo browser,
// porque o servidor da UA não deixa fazer pedidos para fora

const API = 'https://www.cheapshark.com/api/1.0';
const POR_PAGINA = 24;

const grelha = document.querySelector('#promocoes');
const aviso = document.querySelector('#promocoes-aviso');
const botoesFiltro = document.querySelectorAll('.filtros button');
const botaoAnterior = document.querySelector('#pagina-anterior');
const botaoSeguinte = document.querySelector('#pagina-seguinte');

let lojas = {};
let ordem = 'Reviews';
let pagina = 0;

async function carregarLojas() {
    const resposta = await fetch(API + '/stores');
    const dados = await resposta.json();

    for (const loja of dados) {
        lojas[loja.storeID] = loja.storeName;
    }
}

async function carregarPromocoes() {
    grelha.innerHTML = '';
    aviso.textContent = 'A carregar...';
    botaoAnterior.disabled = true;
    botaoSeguinte.disabled = true;

    try {
        const url = API + '/deals?onSale=1&pageSize=' + POR_PAGINA + '&sortBy=' + ordem + '&pageNumber=' + pagina;
        const resposta = await fetch(url);
        const promocoes = await resposta.json();

        aviso.textContent = '';

        for (const promo of promocoes) {
            grelha.appendChild(criarCartao(promo));
        }

        botaoAnterior.disabled = pagina === 0;
        botaoSeguinte.disabled = promocoes.length < POR_PAGINA;
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
    loja.textContent = lojas[promo.storeID] || '';

    const desconto = document.createElement('span');
    desconto.className = 'desconto';
    desconto.textContent = '-' + Math.round(promo.savings) + '%';

    const precoNormal = document.createElement('s');
    precoNormal.textContent = '$' + promo.normalPrice;

    const precoPromo = document.createElement('strong');
    precoPromo.textContent = promo.salePrice === '0.00' ? 'Grátis' : '$' + promo.salePrice;

    const precos = document.createElement('p');
    precos.className = 'cartao-precos';
    precos.append(desconto, precoNormal, precoPromo);

    cartao.append(imagem, titulo, loja, precos);
    return cartao;
}

for (const botao of botoesFiltro) {
    botao.addEventListener('click', function () {
        for (const outro of botoesFiltro) {
            outro.classList.remove('ativo');
        }
        botao.classList.add('ativo');

        ordem = botao.dataset.ordem;
        pagina = 0;
        carregarPromocoes();
    });
}

botaoAnterior.addEventListener('click', function () {
    pagina--;
    carregarPromocoes();
});

botaoSeguinte.addEventListener('click', function () {
    pagina++;
    carregarPromocoes();
});

async function iniciar() {
    // se as lojas falharem as promoções aparecem na mesma, só sem o nome da loja
    try {
        await carregarLojas();
    } catch (erro) {
        lojas = {};
    }

    carregarPromocoes();
}

iniciar();
