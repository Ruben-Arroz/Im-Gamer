// sugestões de jogos da rawg enquanto se escreve
// é o browser que faz o pedido porque o servidor da UA não tem acesso à internet

const campoPesquisa = document.querySelector('#jogo_pesquisa');
const listaSugestoes = document.querySelector('#jogo_sugestoes');
const chave = campoPesquisa.dataset.chave;

let temporizador = null;

campoPesquisa.addEventListener('input', function () {
    // se o texto mudar, o jogo que estava escolhido deixa de contar
    document.querySelector('#jogo_rawg_id').value = '';
    clearTimeout(temporizador);

    const texto = campoPesquisa.value.trim();

    if (texto.length < 2) {
        listaSugestoes.innerHTML = '';
        return;
    }

    // espera um bocadinho depois de parar de escrever, para não fazer um pedido por cada letra
    temporizador = setTimeout(function () {
        pesquisar(texto);
    }, 350);
});

async function pesquisar(texto) {
    try {
        const resposta = await fetch('https://api.rawg.io/api/games?page_size=6&key=' + chave + '&search=' + encodeURIComponent(texto));
        const dados = await resposta.json();
        mostrarSugestoes(dados.results || []);
    } catch (erro) {
        listaSugestoes.innerHTML = '';
    }
}

function mostrarSugestoes(jogos) {
    listaSugestoes.innerHTML = '';

    for (const jogo of jogos) {
        const item = document.createElement('li');
        const ano = jogo.released ? ' (' + jogo.released.slice(0, 4) + ')' : '';
        item.textContent = jogo.name + ano;

        item.addEventListener('click', function () {
            escolher(jogo);
        });

        listaSugestoes.appendChild(item);
    }
}

function escolher(jogo) {
    campoPesquisa.value = jogo.name;
    document.querySelector('#jogo_rawg_id').value = jogo.id;
    document.querySelector('#jogo_nome').value = jogo.name;
    document.querySelector('#jogo_slug').value = jogo.slug;
    document.querySelector('#jogo_imagem').value = jogo.background_image || '';
    document.querySelector('#jogo_lancamento').value = jogo.released || '';
    listaSugestoes.innerHTML = '';
}

// fecha a lista quando se clica fora dela
document.addEventListener('click', function (evento) {
    if (evento.target !== campoPesquisa) {
        listaSugestoes.innerHTML = '';
    }
});
