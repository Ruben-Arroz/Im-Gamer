// mostra a foto e a capa escolhidas antes de carregar em guardar

const campoFoto = document.querySelector('#foto');
const campoCapa = document.querySelector('#capa');
const previsaoFoto = document.querySelector('#previsao_foto');
const previsaoCapa = document.querySelector('#previsao_capa');

campoFoto.addEventListener('change', function () {
    const ficheiro = campoFoto.files[0];

    if (ficheiro) {
        // cria um endereço temporário para a imagem que ainda só está no computador
        previsaoFoto.src = URL.createObjectURL(ficheiro);
        previsaoFoto.classList.remove('escondido');
    }
});

campoCapa.addEventListener('change', function () {
    const ficheiro = campoCapa.files[0];

    if (ficheiro) {
        previsaoCapa.style.backgroundImage = 'url(' + URL.createObjectURL(ficheiro) + ')';
        previsaoCapa.classList.remove('escondido');
    }
});
