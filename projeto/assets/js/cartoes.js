// funções partilhadas para criar os cartões de jogos (catálogo, lançamentos e página inicial)
// cada página chama configurarCartoes() com o elemento que tem os dados que o php lá pôs

const CHEAPSHARK = 'https://www.cheapshark.com/api/1.0';
const RAWG = 'https://api.rawg.io/api/games';

let simbolo = '$';
let taxa = 1;
let lojasOficiais = [];
let nomesLojas = {};
let generosPt = {};
let chaveRawg = '';
let base = '';

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

function configurarCartoes(elemento) {
    const dados = elemento.dataset;

    if (dados.simbolo) {
        simbolo = dados.simbolo;
        taxa = parseFloat(dados.taxa);
    }
    if (dados.oficiais) {
        lojasOficiais = dados.oficiais.split(',');
    }
    if (dados.generos) {
        generosPt = JSON.parse(dados.generos);
    }
    if (dados.chave) {
        chaveRawg = dados.chave;
    }
    // o endereço base do site (vazio em localhost, /tesp-ds-g17 no servidor)
    if (dados.base !== undefined) {
        base = dados.base;
    }
}

// os cartões abrem a página do jogo, que vai buscar o resto dos dados
function linkJogoPromocao(promo) {
    return base + '/projeto/jogo.php?nome=' + encodeURIComponent(promo.title) + (promo.steamAppID ? '&steam=' + promo.steamAppID : '');
}

// nomes das lojas da cheapshark, se falhar os cartões aparecem na mesma sem o nome
async function carregarLojas() {
    try {
        const resposta = await fetch(CHEAPSHARK + '/stores');
        const lojas = await resposta.json();

        for (const loja of lojas) {
            nomesLojas[loja.storeID] = loja.storeName;
        }
    } catch (erro) {
        nomesLojas = {};
    }
}

// os preços vêm em dólares e a taxa passa-os para a moeda do utilizador
function formatarPreco(dolares) {
    const valor = parseFloat(dolares) * taxa;

    if (valor === 0) {
        return 'Grátis';
    }

    return valor.toFixed(2).replace('.', ',') + ' ' + simbolo;
}

// datas da rawg vêm como 2026-10-24
function formatarData(texto) {
    const partes = texto.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function criarTexto(classe, texto) {
    const paragrafo = document.createElement('p');
    paragrafo.className = classe;
    paragrafo.textContent = texto;
    return paragrafo;
}

function criarCartaoPromocao(promo) {
    const cartao = document.createElement('a');
    cartao.className = 'cartao-jogo';
    cartao.href = linkJogoPromocao(promo);

    // a imagem da steam é maior que a miniatura da cheapshark, se falhar usa a miniatura
    const imagem = document.createElement('img');
    imagem.alt = '';
    imagem.loading = 'lazy';
    imagem.src = promo.steamAppID ? 'https://cdn.akamai.steamstatic.com/steam/apps/' + promo.steamAppID + '/header.jpg' : promo.thumb;
    imagem.onerror = function () {
        imagem.onerror = null;
        imagem.src = promo.thumb;
    };

    const loja = criarTexto('cartao-loja', nomesLojas[promo.storeID] || 'Loja');

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

    const precos = document.createElement('p');
    precos.className = 'cartao-precos';

    // na lista de desejos há jogos sem promoção, aí só aparece o preço
    if (parseFloat(promo.savings) >= 1) {
        const desconto = document.createElement('span');
        desconto.className = 'desconto';
        desconto.textContent = '-' + Math.round(promo.savings) + '%';

        const precoNormal = document.createElement('s');
        precoNormal.textContent = formatarPreco(promo.normalPrice);

        precos.append(desconto, precoNormal);
    }

    const precoPromo = document.createElement('strong');
    precoPromo.textContent = formatarPreco(promo.salePrice);
    precos.appendChild(precoPromo);

    cartao.append(imagem, criarTexto('cartao-titulo', promo.title), loja, criarTexto('cartao-loja', extra.join(' · ')), precos);
    return cartao;
}

function criarCartaoLancamento(jogo) {
    const cartao = document.createElement('a');
    cartao.className = 'cartao-jogo';
    cartao.href = base + '/projeto/jogo.php?rawg=' + encodeURIComponent(jogo.slug);

    if (jogo.background_image) {
        const imagem = document.createElement('img');
        imagem.alt = '';
        imagem.loading = 'lazy';
        // a rawg tem uma versão mais pequena da imagem, que carrega mais depressa
        imagem.src = jogo.background_image.replace('/media/games/', '/media/crop/600/400/games/');
        cartao.appendChild(imagem);
    } else {
        cartao.appendChild(criarTexto('cartao-sem-imagem', 'sem imagem'));
    }

    const plataformas = (jogo.parent_platforms || []).map(function (p) {
        return p.platform.name;
    });

    const generos = (jogo.genres || []).map(function (g) {
        return generosPt[g.name] || g.name;
    });

    const data = document.createElement('p');
    data.className = 'cartao-precos';

    const dataForte = document.createElement('strong');
    dataForte.textContent = jogo.released ? formatarData(jogo.released) : 'Sem data';
    data.appendChild(dataForte);

    // quando a data ainda não é oficial a rawg marca o jogo como "tba"
    if (jogo.tba) {
        const nota = document.createElement('span');
        nota.className = 'texto-dim';
        nota.textContent = 'data por confirmar';
        data.appendChild(nota);
    }

    cartao.append(criarTexto('cartao-titulo', jogo.name), criarTexto('cartao-loja', plataformas.join(' · ')), criarTexto('cartao-loja', generos.join(', ')), data);
    return cartao;
}

// datas no formato que a rawg quer (2026-10-24), a contar a partir de hoje
function dataDaqui(dias) {
    const data = new Date();
    data.setDate(data.getDate() + dias);
    return data.toISOString().slice(0, 10);
}

// paginação com os números à volta da página atual (usada no catálogo e nos lançamentos)
function mostrarPaginacao(elemento, parametros, atual, totalPaginas) {
    if (totalPaginas <= 1) {
        return;
    }

    function link(texto, numero) {
        const novos = new URLSearchParams(parametros);
        novos.set('pagina', numero);

        const a = document.createElement('a');
        a.textContent = texto;
        a.href = '?' + novos.toString();
        elemento.appendChild(a);
    }

    if (atual > 1) {
        link('Anterior', atual - 1);
    }

    const inicio = Math.max(1, atual - 2);
    const fim = Math.min(totalPaginas, atual + 2);

    if (inicio > 1) {
        link('1', 1);
    }

    for (let numero = inicio; numero <= fim; numero++) {
        if (numero === atual) {
            const span = document.createElement('span');
            span.className = 'pagina-atual';
            span.textContent = numero;
            elemento.appendChild(span);
        } else {
            link(numero, numero);
        }
    }

    if (fim < totalPaginas) {
        link(totalPaginas, totalPaginas);
    }

    if (atual < totalPaginas) {
        link('Seguinte', atual + 1);
    }
}
