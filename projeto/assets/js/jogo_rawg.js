// quando o jogo ainda não está na bd: procura-o na rawg, manda os dados ao servidor para ficar guardado e abre a página dele
// é o browser que fala com a rawg porque o servidor da UA não tem acesso à internet

const caixa = document.querySelector('#procurar-jogo');
const dados = caixa.dataset;
const estado = document.querySelector('#procurar-estado');

function falhar() {
    estado.textContent = 'Não foi possível encontrar este jogo. Tente novamente mais tarde.';
}

async function procurarNaRawg() {
    try {
        let slug = dados.slug;

        // do catálogo só vem o nome, por isso primeiro pesquisa-se o jogo
        if (!slug) {
            const pesquisa = await fetch('https://api.rawg.io/api/games?page_size=1&search_precise=true&key=' + dados.chave + '&search=' + encodeURIComponent(dados.nome));
            const resultados = await pesquisa.json();

            if (!resultados.results || !resultados.results.length) {
                falhar();
                return;
            }

            slug = resultados.results[0].slug;
        }

        const resposta = await fetch('https://api.rawg.io/api/games/' + slug + '?key=' + dados.chave);
        const jogo = await resposta.json();

        if (!jogo.id) {
            falhar();
            return;
        }

        // os modos de jogo vêm nas etiquetas (tags) da rawg
        const tags = (jogo.tags || []).map(function (tag) {
            return tag.slug;
        });

        const formulario = new FormData();
        formulario.append('token', dados.token);
        formulario.append('rawg_id', jogo.id);
        formulario.append('nome', jogo.name);
        formulario.append('slug', jogo.slug);
        formulario.append('imagem', jogo.background_image || '');
        formulario.append('lancamento', jogo.released || '');
        formulario.append('descricao', jogo.description_raw || '');
        formulario.append('metacritic', jogo.metacritic || '');
        formulario.append('produtora', jogo.developers && jogo.developers.length ? jogo.developers[0].name : '');
        formulario.append('editora', jogo.publishers && jogo.publishers.length ? jogo.publishers[0].name : '');
        formulario.append('steam', dados.steam);

        if (tags.includes('singleplayer')) {
            formulario.append('um_jogador', '1');
        }
        if (tags.includes('multiplayer') || tags.includes('online-multiplayer')) {
            formulario.append('multijogador', '1');
        }
        if (tags.includes('co-op') || tags.includes('online-co-op')) {
            formulario.append('cooperativo', '1');
        }

        for (const genero of jogo.genres || []) {
            formulario.append('generos[]', genero.name);
        }
        for (const plataforma of jogo.platforms || []) {
            formulario.append('plataformas[]', plataforma.platform.name);
        }

        const guardar = await fetch(dados.base + '/projeto/api/guardar_jogo.php', { method: 'POST', body: formulario });
        const guardado = await guardar.json();

        if (!guardado.id) {
            falhar();
            return;
        }

        // replace para o botão "voltar" do browser não voltar a esta página de procura
        location.replace(dados.base + '/projeto/jogo.php?id=' + guardado.id);
    } catch (erro) {
        falhar();
    }
}

procurarNaRawg();
