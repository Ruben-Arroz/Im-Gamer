// lançamentos da rawg, pedidos pelo browser porque o servidor da UA não tem internet

const POR_PAGINA = 40;

const secao = document.querySelector('#lancamentos');
const grelha = document.querySelector('#lancamentos-lista');
const aviso = document.querySelector('#lancamentos-aviso');
const resumo = document.querySelector('#lancamentos-resumo');

const parametros = new URLSearchParams(window.location.search);
const aba = parametros.get('aba') === 'breve' ? 'breve' : 'recentes';
const periodo = parseInt(parametros.get('periodo') || '90');
const pagina = Math.max(1, parseInt(parametros.get('pagina') || '1'));

configurarCartoes(secao);

function intervaloDatas() {
    // em breve: de amanhã até daqui a X dias, recentes: de há X dias até hoje
    if (aba === 'breve') {
        return dataDaqui(1) + ',' + dataDaqui(periodo);
    }

    return dataDaqui(-periodo) + ',' + dataDaqui(0);
}

function montarUrl() {
    let url = RAWG + '?key=' + chaveRawg + '&page_size=' + POR_PAGINA + '&page=' + pagina;
    url += '&dates=' + intervaloDatas();

    // popularidade é quantas pessoas adicionaram o jogo na rawg
    if (parametros.get('ordem') === 'data') {
        url += aba === 'breve' ? '&ordering=released' : '&ordering=-released';
    } else {
        url += '&ordering=-added';
    }

    const plataformas = parametros.getAll('plataforma[]');
    if (plataformas.length > 0) {
        url += '&parent_platforms=' + plataformas.join(',');
    }

    const generos = parametros.getAll('genero[]');
    if (generos.length > 0) {
        url += '&genres=' + generos.join(',');
    }

    if (parametros.get('q')) {
        url += '&search=' + encodeURIComponent(parametros.get('q'));
    }

    return url;
}

async function carregarLancamentos() {
    aviso.textContent = 'A carregar...';

    try {
        const resposta = await fetch(montarUrl());
        const dados = await resposta.json();
        const jogos = dados.results || [];

        aviso.textContent = jogos.length === 0 ? 'Nenhum jogo encontrado com estes filtros.' : '';
        resumo.textContent = dados.count + (dados.count === 1 ? ' jogo' : ' jogos') + (aba === 'breve' ? ' a sair em breve' : ' lançados recentemente');

        for (const jogo of jogos) {
            grelha.appendChild(criarCartaoLancamento(jogo));
        }

        mostrarPaginacao(document.querySelector('#paginacao'), parametros, pagina, Math.ceil(dados.count / POR_PAGINA));
    } catch (erro) {
        mostrarGuardados();
    }
}

// a rawg não respondeu, mostra a última lista da steam que ficou guardada (sem filtros)
async function mostrarGuardados() {
    try {
        const dados = await lancamentosGuardados(aba);

        if (!dados.jogos.length) {
            aviso.textContent = 'Os lançamentos vêm da RAWG, que não está a responder, e ainda não há nenhuma lista guardada. Tenta mais tarde.';
            return;
        }

        aviso.textContent = avisoGuardados(dados.atualizado) + ' Os filtros não se aplicam a esta lista.';
        resumo.textContent = dados.jogos.length + ' jogos de PC' + (aba === 'breve' ? ' a sair em breve' : ' lançados recentemente');

        for (const jogo of dados.jogos) {
            grelha.appendChild(criarCartaoLancamento(jogo));
        }
    } catch (erro) {
        aviso.textContent = 'Os lançamentos vêm da RAWG, que não está a responder. Tenta mais tarde.';
    }
}

carregarLancamentos();
