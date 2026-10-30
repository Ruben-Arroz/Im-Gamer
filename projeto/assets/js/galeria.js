// galeria da página do jogo: a imagem do jogo e as capturas de ecrã da rawg
// as capturas não ficam guardadas na bd, o browser pede-as cada vez que a página abre

const galeria = document.querySelector('#galeria');
const imagemGrande = document.querySelector('#galeria-imagem');
const faixaMiniaturas = document.querySelector('#galeria-miniaturas');
const setas = galeria.querySelectorAll('.galeria-seta');

let imagens = [];
let atual = 0;

// para comparar nomes sem ligar a maiúsculas, espaços e símbolos (como no jogo_rawg.js)
function simplificar(nome) {
    return nome.toLowerCase().replace(/[^a-z0-9]/g, '');
}

// a rawg tem uma versão mais pequena de cada imagem, que carrega mais depressa nas miniaturas
function versaoPequena(url) {
    return url.replace('/media/screenshots/', '/media/crop/600/400/screenshots/').replace('/media/games/', '/media/crop/600/400/games/');
}

function mostrarImagem(numero) {
    // depois da última volta à primeira, e ao contrário
    atual = (numero + imagens.length) % imagens.length;
    imagemGrande.src = imagens[atual];
    imagemGrande.classList.remove('escondido');

    faixaMiniaturas.querySelectorAll('img').forEach(function (miniatura, posicao) {
        miniatura.classList.toggle('ativa', posicao === atual);
    });
}

// os jogos que vieram da steam, xbox, gog ou epic não têm o slug da rawg, por isso procura-se pelo nome
async function descobrirSlug() {
    if (galeria.dataset.slug) {
        return galeria.dataset.slug;
    }

    const resposta = await fetch('https://api.rawg.io/api/games?page_size=5&search_precise=true&key=' + galeria.dataset.chave + '&search=' + encodeURIComponent(galeria.dataset.nome));
    const dados = await resposta.json();

    // só conta um jogo com o nome igual, senão apareciam imagens de outro jogo
    const igual = (dados.results || []).find(function (jogo) {
        return simplificar(jogo.name) === simplificar(galeria.dataset.nome);
    });

    return igual ? igual.slug : null;
}

async function carregarGaleria() {
    try {
        const slug = await descobrirSlug();
        if (!slug) {
            return;
        }

        const resposta = await fetch('https://api.rawg.io/api/games/' + slug + '/screenshots?key=' + galeria.dataset.chave);
        const dados = await resposta.json();

        // a imagem que já estava fica em primeiro, sem repetir se a rawg tiver a mesma
        if (imagemGrande.getAttribute('src')) {
            imagens.push(imagemGrande.getAttribute('src'));
        }
        for (const captura of dados.results || []) {
            if (!imagens.includes(captura.image)) {
                imagens.push(captura.image);
            }
        }

        // com uma imagem só não vale a pena mostrar setas nem miniaturas
        if (imagens.length < 2) {
            return;
        }

        imagens.forEach(function (url, posicao) {
            const miniatura = document.createElement('img');
            miniatura.src = versaoPequena(url);
            miniatura.alt = 'Imagem ' + (posicao + 1);
            miniatura.loading = 'lazy';
            miniatura.addEventListener('click', function () {
                mostrarImagem(posicao);
            });
            faixaMiniaturas.appendChild(miniatura);
        });

        for (const seta of setas) {
            seta.classList.remove('escondido');
            seta.addEventListener('click', function () {
                mostrarImagem(atual + parseInt(seta.dataset.direcao));
            });
        }

        mostrarImagem(0);
    } catch (erro) {
        // se a rawg falhar fica só a imagem que já lá estava
    }
}

carregarGaleria();
