// lançamentos de jogos da RAWG, pedidos pelo browser
// (o servidor da UA não deixa fazer pedidos para fora)
// os filtros vêm do url, que é o que o formulário envia
// os cartões e a paginação estão no cartoes.js

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
        aviso.textContent = 'Não foi possível carregar os lançamentos. Tenta mais tarde.';
    }
}

carregarLancamentos();
