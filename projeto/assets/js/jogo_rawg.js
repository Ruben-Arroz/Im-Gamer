// quando o jogo ainda não está na bd: procura-o na rawg, manda os dados ao servidor para ficar guardado e abre a página dele
// é o browser que fala com a rawg porque o servidor da UA não tem acesso à internet

const caixa = document.querySelector('#procurar-jogo');
const dados = caixa.dataset;
const estado = document.querySelector('#procurar-estado');

function falhar() {
    estado.textContent = 'Não foi possível encontrar este jogo. Tente novamente mais tarde.';
}

// para comparar nomes sem ligar a maiúsculas, espaços e símbolos ("MAFIA HOTEL" e "Mafia Hotel" são iguais)
function simplificar(nome) {
    return nome.toLowerCase().replace(/[^a-z0-9]/g, '');
}

// manda os dados para o servidor guardar e abre a página do jogo
async function guardar(formulario) {
    formulario.append('token', dados.token);
    formulario.append('steam', dados.steam);

    const resposta = await fetch(dados.base + '/projeto/api/guardar_jogo.php', { method: 'POST', body: formulario });
    const guardado = await resposta.json();

    if (!guardado.id) {
        falhar();
        return;
    }

    // replace para o botão "voltar" do browser não voltar a esta página de procura
    location.replace(dados.base + '/projeto/jogo.php?id=' + guardado.id);
}

// a rawg não tem todos os jogos (sobretudo os mais pequenos), aí usa-se o que a cheapshark sabe do jogo
async function guardarDaCheapshark() {
    let url = 'https://www.cheapshark.com/api/1.0/games?';
    url += dados.steam ? 'steamAppID=' + dados.steam : 'exact=1&title=' + encodeURIComponent(dados.nome);

    const lista = await (await fetch(url)).json();

    const formulario = new FormData();
    formulario.append('fonte', 'cheapshark');
    formulario.append('nome', dados.nome);

    // a imagem da steam é maior, se não houver fica a miniatura da cheapshark
    if (dados.steam) {
        formulario.append('imagem', 'https://cdn.akamai.steamstatic.com/steam/apps/' + dados.steam + '/header.jpg');
    } else if (lista.length && lista[0].thumb) {
        formulario.append('imagem', lista[0].thumb);
    }

    await guardar(formulario);
}

async function procurarNaRawg() {
    try {
        let slug = dados.slug;

        // do catálogo só vem o nome, por isso primeiro pesquisa-se o jogo
        // só conta um resultado com o nome igual, senão abria outro jogo parecido
        if (!slug) {
            const pesquisa = await fetch('https://api.rawg.io/api/games?page_size=5&search_precise=true&key=' + dados.chave + '&search=' + encodeURIComponent(dados.nome));
            const resultados = await pesquisa.json();

            const igual = (resultados.results || []).find(function (jogo) {
                return simplificar(jogo.name) === simplificar(dados.nome);
            });

            if (!igual) {
                await guardarDaCheapshark();
                return;
            }

            slug = igual.slug;
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
        formulario.append('rawg_id', jogo.id);
        formulario.append('nome', jogo.name);
        formulario.append('slug', jogo.slug);
        formulario.append('imagem', jogo.background_image || '');
        formulario.append('lancamento', jogo.released || '');
        formulario.append('descricao', jogo.description_raw || '');
        formulario.append('metacritic', jogo.metacritic || '');
        formulario.append('produtora', jogo.developers && jogo.developers.length ? jogo.developers[0].name : '');
        formulario.append('editora', jogo.publishers && jogo.publishers.length ? jogo.publishers[0].name : '');

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

        await guardar(formulario);
    } catch (erro) {
        falhar();
    }
}

procurarNaRawg();
