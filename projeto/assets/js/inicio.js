
const inicio = document.querySelector('#inicio');
const todasLojas = inicio.dataset.todas;
const POR_CARROSSEL = 15;

configurarCartoes(inicio);

// o destaque é a melhor promoção num jogo AAA que esteja na steam (para ter imagem grande)
async function carregarDestaque() {
    const destaque = document.querySelector('#destaque');

    try {
        const resposta = await fetch(CHEAPSHARK + '/deals?onSale=1&AAA=1&pageSize=10&sortBy=Deal%20Rating&storeID=' + todasLojas);
        const promocoes = await resposta.json();
        const promo = promocoes.find(function (p) {
            return p.steamAppID;
        });

        if (!promo) {
            destaque.remove();
            return;
        }

        // a imagem "hero" da steam é a mais larga, se não existir usa a normal
        const imagem = new Image();
        imagem.onload = function () {
            destaque.style.backgroundImage = 'url(' + imagem.src + ')';
        };
        imagem.onerror = function () {
            destaque.style.backgroundImage = 'url(https://cdn.akamai.steamstatic.com/steam/apps/' + promo.steamAppID + '/header.jpg)';
        };
        imagem.src = 'https://cdn.akamai.steamstatic.com/steam/apps/' + promo.steamAppID + '/library_hero.jpg';

        const caixa = document.createElement('a');
        caixa.className = 'destaque-caixa';
        caixa.href = linkJogoPromocao(promo);

        const etiqueta = criarTexto('destaque-etiqueta', 'Em destaque');
        const titulo = criarTexto('destaque-titulo', promo.title);
        const loja = criarTexto('cartao-loja', nomesLojas[promo.storeID] || '');

        const precos = document.createElement('p');
        precos.className = 'cartao-precos';

        const desconto = document.createElement('span');
        desconto.className = 'desconto';
        desconto.textContent = '-' + Math.round(promo.savings) + '%';

        const precoNormal = document.createElement('s');
        precoNormal.textContent = formatarPreco(promo.normalPrice);

        const precoPromo = document.createElement('strong');
        precoPromo.textContent = formatarPreco(promo.salePrice);

        precos.append(desconto, precoNormal, precoPromo);
        caixa.append(etiqueta, titulo, loja, precos);
        destaque.appendChild(caixa);
    } catch (erro) {
        destaque.remove();
    }
}

// faixa por cima dos lançamentos, igual à do destaque
function mostrarDestaqueLancamento(jogo) {
    const destaque = document.querySelector('#destaque-lancamento');
    destaque.style.backgroundImage = 'url(' + jogo.background_image + ')';

    // nos jogos guardados da steam a imagem é pequena, por isso tenta a imagem larga da steam como no destaque
    if (jogo.steam) {
        const imagem = new Image();
        imagem.onload = function () {
            destaque.style.backgroundImage = 'url(' + imagem.src + ')';
        };
        imagem.src = 'https://cdn.akamai.steamstatic.com/steam/apps/' + jogo.steam + '/library_hero.jpg';
    }

    const caixa = document.createElement('a');
    caixa.className = 'destaque-caixa';
    if (jogo.id_site) {
        caixa.href = base + '/projeto/jogo.php?id=' + jogo.id_site;
    } else {
        caixa.href = base + '/projeto/jogo.php?rawg=' + encodeURIComponent(jogo.slug);
    }

    const data = document.createElement('p');
    data.className = 'cartao-precos';
    const dataForte = document.createElement('strong');
    dataForte.textContent = 'Saiu a ' + formatarData(jogo.released);
    data.appendChild(dataForte);

    caixa.append(
        criarTexto('destaque-etiqueta', 'Acabado de sair'),
        criarTexto('destaque-titulo', jogo.name),
        criarTexto('cartao-loja', plataformasRawg(jogo)),
        criarTexto('cartao-loja', generosRawg(jogo)),
        data
    );
    destaque.appendChild(caixa);
}

function urlDoCarrossel(carrossel) {
    if (carrossel.dataset.fonte === 'rawg') {
        // o pedido tem os dias "de" e "até" a contar de hoje, por exemplo "-30,0"
        const dias = carrossel.dataset.pedido.split(',');
        return RAWG + '?key=' + chaveRawg + '&page_size=' + POR_CARROSSEL + '&ordering=-added&dates=' + dataDaqui(parseInt(dias[0])) + ',' + dataDaqui(parseInt(dias[1]));
    }

    // pede mais do que mostra, porque o mesmo jogo aparece uma vez por cada loja e os repetidos são tirados
    let url = CHEAPSHARK + '/deals?onSale=1&pageSize=' + (POR_CARROSSEL * 3) + '&' + carrossel.dataset.pedido;

    // se o carrossel não escolheu lojas usa só as lojas que ainda estão ativas
    if (!carrossel.dataset.pedido.includes('storeID')) {
        url += '&storeID=' + todasLojas;
    }

    return url;
}

async function carregarCarrossel(carrossel) {
    const faixa = carrossel.querySelector('.carrossel-faixa');

    try {
        const resposta = await fetch(urlDoCarrossel(carrossel));
        const dados = await resposta.json();
        faixa.innerHTML = '';

        // a rawg devolve os jogos dentro de "results", a cheapshark devolve logo a lista
        if (carrossel.dataset.fonte === 'rawg') {
            mostrarLancamentos(carrossel, dados.results || []);
        } else {
            // cada jogo só aparece uma vez (fica a primeira loja, que é a que a ordenação pôs à frente)
            const jaMostrados = [];

            for (const promo of dados) {
                if (jaMostrados.includes(promo.gameID) || jaMostrados.length === POR_CARROSSEL) {
                    continue;
                }

                jaMostrados.push(promo.gameID);
                faixa.appendChild(criarCartaoPromocao(promo));
            }
        }

        if (!faixa.children.length) {
            faixa.appendChild(criarTexto('texto-dim', 'Nada para mostrar agora.'));
        }
    } catch (erro) {
        faixa.innerHTML = '';
        if (carrossel.dataset.fonte === 'rawg') {
            carregarGuardados(carrossel);
        } else {
            faixa.appendChild(criarTexto('texto-dim', 'Não foi possível carregar. Tenta mais tarde.'));
        }
    }
}

// vêm do mais popular para o menos, o primeiro com imagem vai para a faixa (se o carrossel a tiver)
function mostrarLancamentos(carrossel, jogos) {
    const faixa = carrossel.querySelector('.carrossel-faixa');

    if (carrossel.dataset.destaque) {
        const escolhido = jogos.find(function (jogo) {
            return jogo.background_image && jogo.released;
        });

        if (escolhido) {
            mostrarDestaqueLancamento(escolhido);
            jogos = jogos.filter(function (jogo) {
                return jogo !== escolhido;
            });
        } else {
            tirarDestaqueLancamento();
        }
    }

    for (const jogo of jogos.slice(0, POR_CARROSSEL)) {
        faixa.appendChild(criarCartaoLancamento(jogo));
    }
}

// a rawg não respondeu, usa a última lista da steam guardada no site
async function carregarGuardados(carrossel) {
    const faixa = carrossel.querySelector('.carrossel-faixa');
    // os carrosséis da rawg com dias para trás ("-30,0") são os acabados de sair
    const tipo = carrossel.dataset.pedido.startsWith('-') ? 'recentes' : 'breve';

    try {
        const dados = await lancamentosGuardados(tipo);

        if (!dados.jogos.length) {
            throw new Error('lista vazia');
        }

        carrossel.insertBefore(criarTexto('texto-dim aviso-guardados', avisoGuardados(dados.atualizado)), faixa);
        mostrarLancamentos(carrossel, dados.jogos);
    } catch (erro) {
        faixa.appendChild(criarTexto('texto-dim', 'Os lançamentos vêm da RAWG, que não está a responder. Tenta mais tarde.'));

        if (carrossel.dataset.destaque) {
            tirarDestaqueLancamento();
        }
    }
}

// se a rawg falhar a faixa sai, para não ficar uma caixa vazia
function tirarDestaqueLancamento() {
    const destaque = document.querySelector('#destaque-lancamento');
    if (destaque) {
        destaque.remove();
    }
}

// jogos da lista de desejos com o preço mais barato que houver agora (com ou sem promoção)
async function carregarDesejos(carrossel) {
    const faixa = carrossel.querySelector('.carrossel-faixa');
    const desejos = JSON.parse(carrossel.dataset.desejos).slice(0, POR_CARROSSEL);

    try {
        const precos = await melhoresPrecos(desejos, todasLojas.split(','));
        faixa.innerHTML = '';

        for (const jogo of desejos) {
            if (!precos[jogo.id]) {
                continue;
            }

            // o cartão é o mesmo das promoções, só muda o link para a página do jogo
            const cartao = criarCartaoPromocao(precos[jogo.id]);
            cartao.href = base + '/projeto/jogo.php?id=' + jogo.id;
            faixa.appendChild(cartao);
        }

        if (!faixa.children.length) {
            faixa.appendChild(criarTexto('texto-dim', 'Nenhum jogo da lista de desejos está à venda nas lojas de PC.'));
        }
    } catch (erro) {
        faixa.innerHTML = '';
        faixa.appendChild(criarTexto('texto-dim', 'Não foi possível carregar. Tenta mais tarde.'));
    }
}

// as setas andam quase uma "página" do carrossel para o lado
for (const botao of document.querySelectorAll('.carrossel-setas button')) {
    botao.addEventListener('click', function () {
        const faixa = botao.closest('.carrossel').querySelector('.carrossel-faixa');
        faixa.scrollBy({ left: parseInt(botao.dataset.direcao) * faixa.clientWidth * 0.9, behavior: 'smooth' });
    });
}

async function iniciar() {
    await carregarLojas();
    carregarDestaque();

    for (const carrossel of document.querySelectorAll('.carrossel')) {
        if (carrossel.dataset.fonte === 'desejos') {
            carregarDesejos(carrossel);
        } else {
            carregarCarrossel(carrossel);
        }
    }
}

iniciar();
