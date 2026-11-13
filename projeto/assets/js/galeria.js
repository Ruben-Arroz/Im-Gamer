// galeria da página do jogo, com as capturas de ecrã da rawg (ou as da steam guardadas, se a rawg falhar)

const galeria = document.querySelector('#galeria');
const imagemGrande = document.querySelector('#galeria-imagem');
const setas = galeria.querySelectorAll('.galeria-seta');

let imagens = [];
let atual = 0;

// para comparar nomes sem ligar a maiúsculas, espaços e símbolos (como no jogo_rawg.js)
function simplificar(nome) {
    return nome.toLowerCase().replace(/[^a-z0-9]/g, '');
}

function mostrarImagem(numero) {
    // depois da última volta à primeira, e ao contrário
    atual = (numero + imagens.length) % imagens.length;
    imagemGrande.src = imagens[atual];
    imagemGrande.classList.remove('escondido');
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
    let capturas = [];

    try {
        const slug = await descobrirSlug();

        if (slug) {
            const resposta = await fetch('https://api.rawg.io/api/games/' + slug + '/screenshots?key=' + galeria.dataset.chave);
            const dados = await resposta.json();

            for (const captura of dados.results || []) {
                capturas.push(captura.image);
            }
        }
    } catch (erro) {
        // a rawg falhou, ficam as capturas guardadas da steam (em baixo)
    }

    if (!capturas.length) {
        capturas = JSON.parse(galeria.dataset.capturas || '[]');
    }

    // a imagem que já estava fica em primeiro, sem repetir
    if (imagemGrande.getAttribute('src')) {
        imagens.push(imagemGrande.getAttribute('src'));
    }
    for (const captura of capturas) {
        if (!imagens.includes(captura)) {
            imagens.push(captura);
        }
    }

    // com uma imagem só não vale a pena mostrar as setas
    if (imagens.length < 2) {
        return;
    }

    for (const seta of setas) {
        seta.classList.remove('escondido');
        seta.addEventListener('click', function () {
            mostrarImagem(atual + parseInt(seta.dataset.direcao));
        });
    }

    mostrarImagem(0);
}

carregarGaleria();
