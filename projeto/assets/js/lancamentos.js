// lançamentos de jogos da RAWG, pedidos pelo browser
// (o servidor da UA não deixa fazer pedidos para fora)
// os filtros vêm do url, que é o que o formulário envia

const API = 'https://api.rawg.io/api/games';
const POR_PAGINA = 40;

const secao = document.querySelector('#lancamentos');
const grelha = document.querySelector('#lancamentos-lista');
const aviso = document.querySelector('#lancamentos-aviso');
const paginacao = document.querySelector('#paginacao');
const resumo = document.querySelector('#lancamentos-resumo');

const chave = secao.dataset.chave;
const generosPt = JSON.parse(secao.dataset.generos);

const parametros = new URLSearchParams(window.location.search);
const aba = parametros.get('aba') === 'breve' ? 'breve' : 'recentes';
const periodo = parseInt(parametros.get('periodo') || '90');
const pagina = Math.max(1, parseInt(parametros.get('pagina') || '1'));

// datas no formato que a rawg quer (2026-10-24)
function dataTexto(data) {
    return data.toISOString().slice(0, 10);
}

function intervaloDatas() {
    const hoje = new Date();
    const outra = new Date();

    if (aba === 'breve') {
        // de amanhã até daqui a X dias
        hoje.setDate(hoje.getDate() + 1);
        outra.setDate(outra.getDate() + periodo);
        return dataTexto(hoje) + ',' + dataTexto(outra);
    }

    outra.setDate(outra.getDate() - periodo);
    return dataTexto(outra) + ',' + dataTexto(hoje);
}

function montarUrl() {
    let url = API + '?key=' + chave + '&page_size=' + POR_PAGINA + '&page=' + pagina;
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

function formatarData(texto) {
    const partes = texto.split('-');
    return partes[2] + '/' + partes[1] + '/' + partes[0];
}

function criarCartao(jogo) {
    const cartao = document.createElement('a');
    cartao.className = 'cartao-jogo';
    cartao.href = 'https://rawg.io/games/' + jogo.slug;
    cartao.target = '_blank';
    cartao.rel = 'noopener';

    if (jogo.background_image) {
        const imagem = document.createElement('img');
        imagem.alt = '';
        imagem.loading = 'lazy';
        // a rawg tem uma versão mais pequena da imagem, que carrega mais depressa
        imagem.src = jogo.background_image.replace('/media/games/', '/media/crop/600/400/games/');
        cartao.appendChild(imagem);
    } else {
        const vazio = document.createElement('div');
        vazio.className = 'cartao-sem-imagem';
        vazio.textContent = 'sem imagem';
        cartao.appendChild(vazio);
    }

    const titulo = document.createElement('p');
    titulo.className = 'cartao-titulo';
    titulo.textContent = jogo.name;

    // plataformas e géneros numa linha cada
    const plataformas = document.createElement('p');
    plataformas.className = 'cartao-loja';
    plataformas.textContent = (jogo.parent_platforms || []).map(function (p) {
        return p.platform.name;
    }).join(' · ');

    const generos = document.createElement('p');
    generos.className = 'cartao-loja';
    generos.textContent = (jogo.genres || []).map(function (g) {
        return generosPt[g.name] || g.name;
    }).join(', ');

    const data = document.createElement('p');
    data.className = 'cartao-precos';

    const dataForte = document.createElement('strong');
    if (jogo.released) {
        dataForte.textContent = formatarData(jogo.released);
    } else {
        dataForte.textContent = 'Sem data';
    }
    data.appendChild(dataForte);

    // quando a data ainda não é oficial a rawg marca o jogo como "tba"
    if (jogo.tba) {
        const nota = document.createElement('span');
        nota.className = 'texto-dim';
        nota.textContent = 'data por confirmar';
        data.appendChild(nota);
    }

    cartao.append(titulo, plataformas, generos, data);
    return cartao;
}

function urlPagina(numero) {
    const novos = new URLSearchParams(parametros);
    novos.set('pagina', numero);
    return '?' + novos.toString();
}

function linkPagina(texto, numero) {
    const link = document.createElement('a');
    link.textContent = texto;
    link.href = urlPagina(numero);
    paginacao.appendChild(link);
}

function mostrarPaginacao(totalPaginas) {
    if (totalPaginas <= 1) {
        return;
    }

    if (pagina > 1) {
        linkPagina('Anterior', pagina - 1);
    }

    // só mostra algumas páginas à volta da atual
    const inicio = Math.max(1, pagina - 2);
    const fim = Math.min(totalPaginas, pagina + 2);

    if (inicio > 1) {
        linkPagina('1', 1);
    }

    for (let numero = inicio; numero <= fim; numero++) {
        if (numero === pagina) {
            const span = document.createElement('span');
            span.className = 'pagina-atual';
            span.textContent = numero;
            paginacao.appendChild(span);
        } else {
            linkPagina(numero, numero);
        }
    }

    if (fim < totalPaginas) {
        linkPagina(totalPaginas, totalPaginas);
    }

    if (pagina < totalPaginas) {
        linkPagina('Seguinte', pagina + 1);
    }
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
            grelha.appendChild(criarCartao(jogo));
        }

        mostrarPaginacao(Math.ceil(dados.count / POR_PAGINA));
    } catch (erro) {
        aviso.textContent = 'Não foi possível carregar os lançamentos. Tenta mais tarde.';
    }
}

carregarLancamentos();
