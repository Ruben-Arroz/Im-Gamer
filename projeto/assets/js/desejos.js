// preços dos jogos da lista de desejos (a procura dos preços está no cartoes.js)

const grelha = document.querySelector('#desejos');
const cartoes = Array.from(grelha.querySelectorAll('.cartao-jogo'));

configurarCartoes(grelha);

function mostrarPreco(cartao, promo) {
    const preco = cartao.querySelector('.preco-jogo');
    preco.className = 'preco-jogo';
    preco.textContent = '';

    if (!promo) {
        preco.className = 'texto-dim preco-jogo';
        preco.textContent = 'Sem preço nas lojas de PC';
        return;
    }

    if (parseFloat(promo.savings) >= 1) {
        const desconto = document.createElement('span');
        desconto.className = 'desconto';
        desconto.textContent = '-' + Math.round(promo.savings) + '%';

        const normal = document.createElement('s');
        normal.textContent = formatarPreco(promo.normalPrice);

        preco.append(desconto, ' ', normal, ' ');
    }

    const atual = document.createElement('strong');
    atual.textContent = formatarPreco(promo.salePrice);
    preco.appendChild(atual);

    cartao.querySelector('.cartao-loja-preco').textContent = nomesLojas[promo.storeID] || '';
}

async function carregarPrecos() {
    const jogos = cartoes.map(function (cartao) {
        return { id: cartao.dataset.id, nome: cartao.dataset.nome, steam: cartao.dataset.steam };
    });

    try {
        await carregarLojas();
        const precos = await melhoresPrecos(jogos, grelha.dataset.todas.split(','));

        for (const cartao of cartoes) {
            mostrarPreco(cartao, precos[cartao.dataset.id]);
        }

        // a ordem por preço só se pode fazer aqui, os sem preço ficam no fim
        if (grelha.dataset.ordem === 'preco') {
            cartoes.sort(function (a, b) {
                const precoA = precos[a.dataset.id] ? parseFloat(precos[a.dataset.id].salePrice) : Infinity;
                const precoB = precos[b.dataset.id] ? parseFloat(precos[b.dataset.id].salePrice) : Infinity;
                return precoA - precoB;
            });

            for (const cartao of cartoes) {
                grelha.appendChild(cartao);
            }
        }
    } catch (erro) {
        for (const cartao of cartoes) {
            const preco = cartao.querySelector('.preco-jogo');
            preco.textContent = 'Não foi possível carregar o preço.';
        }
    }
}

carregarPrecos();
