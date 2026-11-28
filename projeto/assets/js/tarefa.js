// de 2 em 2 minutos, quem anda pelo site pede ao servidor para atualizar uma coisa (uma conta, as taxas ou os lançamentos)
// é o que faz o site atualizar-se sozinho, já que o servidor da UA não tem tarefas agendadas

const tarefa = document.currentScript;
const DOIS_MINUTOS = 2 * 60 * 1000;

let ultimaVez = 0;
try {
    ultimaVez = Number(localStorage.getItem('ultimaTarefa')) || 0;
} catch (erro) {
    // sem localStorage (janela privada) faz na mesma
}

if (Date.now() - ultimaVez > DOIS_MINUTOS) {
    try {
        localStorage.setItem('ultimaTarefa', Date.now());
    } catch (erro) {
    }

    const dados = new FormData();
    dados.append('token', tarefa.dataset.token);
    fetch(tarefa.dataset.base + '/projeto/api/tarefa.php', { method: 'POST', body: dados }).catch(() => {});
}
