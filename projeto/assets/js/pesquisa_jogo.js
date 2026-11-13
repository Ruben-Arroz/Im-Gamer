// sugestões de jogos da rawg enquanto se escreve

const campoPesquisa = document.querySelector('#jogo_pesquisa');
const listaSugestoes = document.querySelector('#jogo_sugestoes');
const chave = campoPesquisa.dataset.chave;
const base = campoPesquisa.dataset.base;

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
        // se a rawg estiver em baixo procura nos jogos que já estão guardados no site
        pesquisarGuardados(texto);
    }
}

async function pesquisarGuardados(texto) {
    listaSugestoes.innerHTML = '';

    try {
        const resposta = await fetch(base + '/projeto/api/pesquisar_jogos.php?q=' + encodeURIComponent(texto));
        const dados = await resposta.json();

        for (const jogo of dados.jogos) {
            const item = document.createElement('li');
            item.textContent = jogo.nome + (jogo.ano ? ' (' + jogo.ano + ')' : '');

            item.addEventListener('click', function () {
                escolherGuardado(jogo);
            });

            listaSugestoes.appendChild(item);
        }
    } catch (erro) {
        listaSugestoes.innerHTML = '';
    }

    if (!listaSugestoes.children.length) {
        const aviso = document.createElement('li');
        aviso.textContent = 'A RAWG não está a responder e não há jogos guardados com esse nome.';
        listaSugestoes.appendChild(aviso);
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

// jogo que já está na bd: vai o id dele e não o da rawg
function escolherGuardado(jogo) {
    campoPesquisa.value = jogo.nome;
    document.querySelector('#jogo_rawg_id').value = '';
    document.querySelector('input[name="jogo_id"]').value = jogo.id;
    listaSugestoes.innerHTML = '';
}

// fecha a lista quando se clica fora dela
document.addEventListener('click', function (evento) {
    if (evento.target !== campoPesquisa) {
        listaSugestoes.innerHTML = '';
    }
});
