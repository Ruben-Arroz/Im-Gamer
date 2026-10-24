// no telemóvel os filtros estão escondidos e o botão "Filtros" mostra-os ou esconde-os

const botaoFiltros = document.querySelector('#botao-filtros');
const filtros = document.querySelector('#filtros');

if (botaoFiltros && filtros) {
    botaoFiltros.addEventListener('click', function () {
        filtros.classList.toggle('aberto');
    });
}
