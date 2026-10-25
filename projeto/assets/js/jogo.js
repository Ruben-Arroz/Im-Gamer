// preços do jogo nas lojas de PC, vindos da cheapshark (as funções de preços e lojas estão no cartoes.js)

const caixaPrecos = document.querySelector('#precos');
const dadosPrecos = caixaPrecos.dataset;

configurarCartoes(caixaPrecos);

function mensagemPrecos(texto) {
    caixaPrecos.innerHTML = '';
    caixaPrecos.appendChild(criarTexto('texto-dim', texto));
}

function criarLinhaPreco(promo) {
    const linha = document.createElement('a');
    linha.className = 'preco-linha';
    linha.href = 'https://www.cheapshark.com/redirect?dealID=' + promo.dealID;
    linha.target = '_blank';
    linha.rel = 'noopener';

    const loja = document.createElement('span');
    loja.textContent = nomesLojas[promo.storeID] || 'Loja';

    if (!lojasOficiais.includes(promo.storeID)) {
        const etiqueta = document.createElement('span');
        etiqueta.className = 'etiqueta-chave';
        etiqueta.textContent = 'chave';
        loja.appendChild(etiqueta);
    }

    const precos = document.createElement('span');
    precos.className = 'cartao-precos';

    if (parseFloat(promo.savings) >= 1) {
        const desconto = document.createElement('span');
        desconto.className = 'desconto';
        desconto.textContent = '-' + Math.round(promo.savings) + '%';

        const normal = document.createElement('s');
        normal.textContent = formatarPreco(promo.retailPrice);

        precos.append(desconto, normal);
    }

    const atual = document.createElement('strong');
    atual.textContent = formatarPreco(promo.price);
    precos.appendChild(atual);

    linha.append(loja, precos);
    return linha;
}

async function carregarPrecos() {
    try {
        await carregarLojas();

        // primeiro descobre o id do jogo na cheapshark, pela steam ou pelo nome
        let url = CHEAPSHARK + '/games?';
        url += dadosPrecos.steam ? 'steamAppID=' + dadosPrecos.steam : 'exact=1&title=' + encodeURIComponent(dadosPrecos.nome);

        const lista = await (await fetch(url)).json();

        if (!lista.length) {
            mensagemPrecos('Este jogo não está à venda nas lojas de PC que acompanhamos.');
            return;
        }

        const jogo = await (await fetch(CHEAPSHARK + '/games?id=' + lista[0].gameID)).json();

        // só as lojas que o site usa, do mais barato para o mais caro
        const todas = dadosPrecos.todas.split(',');
        const promocoes = jogo.deals.filter(function (promo) {
            return todas.includes(promo.storeID);
        }).sort(function (a, b) {
            return parseFloat(a.price) - parseFloat(b.price);
        });

        if (!promocoes.length) {
            mensagemPrecos('Este jogo não está à venda nas lojas de PC que acompanhamos.');
            return;
        }

        caixaPrecos.innerHTML = '';
        for (const promo of promocoes) {
            caixaPrecos.appendChild(criarLinhaPreco(promo));
        }

        if (jogo.cheapestPriceEver) {
            caixaPrecos.appendChild(criarTexto('texto-dim preco-minimo', 'Preço mais baixo de sempre: ' + formatarPreco(jogo.cheapestPriceEver.price)));
        }
    } catch (erro) {
        mensagemPrecos('Não foi possível carregar os preços. Tente novamente mais tarde.');
    }
}

carregarPrecos();
