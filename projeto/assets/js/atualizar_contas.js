// atualiza as contas steam, xbox e gog que estão desatualizadas e os preços do inventário, um passo de cada vez, enquanto se vê o perfil

const aviso = document.querySelector('#atualizar-contas');
const tentadas = [];
const falhadas = [];
let passos = 0;

function atualizarConta() {
    const dados = new FormData();
    dados.append('token', aviso.dataset.token);
    dados.append('utilizador', aviso.dataset.utilizador);
    // as que já se tentaram ficam de fora, para uma conta que falha não travar as outras
    tentadas.forEach(servico => dados.append('saltar[]', servico));

    fetch(aviso.dataset.base + '/projeto/api/atualizar_contas.php', { method: 'POST', body: dados })
        .then(resposta => resposta.json())
        .then(resposta => {
            passos++;
            if (resposta.erro) {
                aviso.textContent = 'Não foi possível atualizar as contas agora, ficam os dados da última vez.';
                return;
            }

            // o inventário pode levar vários passos, por isso não entra nas tentadas
            if (resposta.chave && resposta.chave !== 'inventario') {
                tentadas.push(resposta.chave);
                if (!resposta.sucesso) {
                    falhadas.push(resposta.servico);
                }
            }

            // o limite de passos é só para nunca ficar preso num ciclo
            if (resposta.faltam > 0 && passos < 12) {
                if (resposta.chave === 'inventario') {
                    aviso.textContent = 'A atualizar os preços do inventário...';
                } else {
                    aviso.textContent = 'A atualizar as contas... (' + resposta.servico + (resposta.sucesso ? ' feita)' : ' não respondeu)');
                }
                atualizarConta();
                return;
            }

            terminar();
        })
        .catch(() => {
            aviso.textContent = 'Não foi possível atualizar as contas agora, ficam os dados da última vez.';
        });
}

function terminar() {
    if (tentadas.length > 0 && falhadas.length === tentadas.length) {
        aviso.textContent = 'Não foi possível atualizar as contas agora, ficam os dados da última vez.';
        return;
    }

    aviso.textContent = 'Contas atualizadas. ';
    if (falhadas.length > 0) {
        aviso.textContent = 'Contas atualizadas, menos ' + falhadas.join(' e ') + (falhadas.length > 1 ? ' que não responderam. ' : ' que não respondeu. ');
    }

    const recarregar = document.createElement('a');
    recarregar.href = location.href;
    recarregar.textContent = 'Recarregar a página';
    aviso.append(recarregar);
}

atualizarConta();
