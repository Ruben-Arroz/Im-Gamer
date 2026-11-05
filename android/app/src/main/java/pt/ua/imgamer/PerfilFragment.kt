package pt.ua.imgamer

import android.content.Intent
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.appcompat.app.AlertDialog
import androidx.fragment.app.Fragment
import org.json.JSONArray
import org.json.JSONObject

// perfil de um jogador. sem nome é o de quem está a usar a app (aba Perfil),
// com nome é o de outro jogador (aberto pela PerfilActivity)
class PerfilFragment : Fragment(R.layout.fragment_perfil) {

    companion object {
        fun novo(nome: String?): PerfilFragment {
            val fragmento = PerfilFragment()
            val argumentos = Bundle()
            argumentos.putString("nome", nome)
            fragmento.arguments = argumentos
            return fragmento
        }
    }

    private lateinit var carregar: ProgressBar
    private lateinit var erro: TextView
    private lateinit var conteudo: LinearLayout

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        carregar = view.findViewById(R.id.carregar)
        erro = view.findViewById(R.id.erro)
        conteudo = view.findViewById(R.id.conteudo)

        view.findViewById<Button>(R.id.sair).setOnClickListener {
            AlertDialog.Builder(requireContext())
                .setMessage("Sair da conta nesta app?")
                .setPositiveButton("Sair") { _, _ -> sairDaConta() }
                .setNegativeButton("Cancelar", null)
                .show()
        }
    }

    // carrega ao abrir e ao voltar de outro ecrã
    override fun onResume() {
        super.onResume()
        carregarPerfil()
    }

    private fun carregarPerfil() {
        val nome = arguments?.getString("nome")
        val parametros = if (nome != null) mapOf("u" to nome) else mapOf()
        val contexto = requireContext().applicationContext

        Thread {
            val resposta = Api.pedir(contexto, "perfil.php", parametros)
            activity?.runOnUiThread {
                if (isAdded) {
                    mostrar(resposta)
                }
            }
        }.start()
    }

    private fun mostrar(resposta: JSONObject) {
        carregar.visibility = View.GONE

        if (semSessao(requireActivity(), resposta)) {
            return
        }

        if (resposta.has("erro")) {
            erro.text = resposta.getString("erro")
            erro.visibility = View.VISIBLE
            return
        }

        val view = requireView()
        conteudo.visibility = View.VISIBLE

        Imagens.mostrar(requireActivity(), texto(resposta, "capa"), view.findViewById<ImageView>(R.id.capa))
        Imagens.mostrar(requireActivity(), texto(resposta, "foto"), view.findViewById<ImageView>(R.id.foto))
        view.findViewById<TextView>(R.id.nome).text = resposta.getString("nome")
        view.findViewById<TextView>(R.id.membro).text = "Membro desde " + resposta.getString("membro_desde")

        // plataforma, jogo favorito e discord, cada um numa linha e só os que houver
        val plataforma = texto(resposta, "plataforma_preferida")
        val favorito = texto(resposta, "jogo_favorito")
        val discord = texto(resposta, "discord")
        val extra = ArrayList<String>()
        if (plataforma != null) {
            extra.add("Joga mais em $plataforma")
        }
        if (favorito != null) {
            extra.add("Jogo favorito: $favorito")
        }
        if (discord != null) {
            extra.add("Discord: $discord")
        }
        mostrarOuEsconder(view.findViewById(R.id.extra), extra.joinToString("\n"))
        mostrarOuEsconder(view.findViewById(R.id.bio), texto(resposta, "bio"))

        view.findViewById<TextView>(R.id.num_jogos).text = resposta.getInt("jogos").toString() + "\njogos"
        view.findViewById<TextView>(R.id.num_horas).text = resposta.getString("horas") + "\njogadas"
        view.findViewById<TextView>(R.id.num_conquistas).text = resposta.getInt("conquistas").toString() + "\nconquistas"

        // o valor fica escondido se o dono o escondeu (aí mostra o número de contas)
        val valor = texto(resposta, "valor")
        val numContas = resposta.getJSONArray("contas").length()
        view.findViewById<TextView>(R.id.num_valor).text = if (valor != null) {
            "$valor\nvalor estimado"
        } else {
            numContas.toString() + if (numContas == 1) "\nconta ligada" else "\ncontas ligadas"
        }

        mostrarContas(resposta.getJSONArray("contas"))
        mostrarMaisJogados(resposta.getJSONArray("mais_jogados"))
        mostrarEquipas(resposta.getJSONArray("equipas"))

        // só no próprio perfil aparece o botão de sair
        view.findViewById<Button>(R.id.sair).visibility = if (resposta.getBoolean("proprio") && arguments?.getString("nome") == null) View.VISIBLE else View.GONE
    }

    private fun mostrarContas(contas: JSONArray) {
        val lista = requireView().findViewById<LinearLayout>(R.id.contas)
        lista.removeAllViews()

        for (i in 0 until contas.length()) {
            val conta = contas.getJSONObject(i)
            val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_linha, lista, false)
            linha.findViewById<TextView>(R.id.titulo).text = conta.getString("plataforma") + " · " + conta.getString("nome")

            val jogos = conta.getInt("jogos")
            val horas = texto(conta, "horas")
            var detalhe = if (jogos == 1) "1 jogo" else "$jogos jogos"
            if (horas != null) {
                detalhe += " · $horas"
            }
            linha.findViewById<TextView>(R.id.detalhe).text = detalhe
            lista.addView(linha)
        }

        esconderSeVazio(R.id.titulo_contas, lista, contas.length())
    }

    private fun mostrarMaisJogados(jogos: JSONArray) {
        val lista = requireView().findViewById<LinearLayout>(R.id.mais_jogados)
        lista.removeAllViews()

        for (i in 0 until jogos.length()) {
            val jogo = jogos.getJSONObject(i)
            val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_resultado, lista, false)
            linha.findViewById<TextView>(R.id.nome).text = jogo.getString("nome") + "\n" + jogo.getString("horas")
            Imagens.mostrar(requireActivity(), texto(jogo, "imagem"), linha.findViewById<ImageView>(R.id.imagem))

            // alguns jogos da biblioteca não estão ligados a um jogo do site
            if (!jogo.isNull("jogo_id")) {
                val id = jogo.getInt("jogo_id")
                linha.setOnClickListener { abrirJogo(requireActivity(), id) }
            }
            lista.addView(linha)
        }

        esconderSeVazio(R.id.titulo_mais_jogados, lista, jogos.length())
    }

    private fun mostrarEquipas(equipas: JSONArray) {
        val lista = requireView().findViewById<LinearLayout>(R.id.equipas)
        lista.removeAllViews()

        for (i in 0 until equipas.length()) {
            val anuncio = equipas.getJSONObject(i)
            val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_linha, lista, false)
            linha.findViewById<TextView>(R.id.titulo).text = anuncio.getString("jogo")
            linha.findViewById<TextView>(R.id.detalhe).text = "de " + anuncio.getString("dono") + " · " + anuncio.getString("estado")
            val id = anuncio.getInt("id")
            linha.setOnClickListener { abrirAnuncio(requireActivity(), id) }
            lista.addView(linha)
        }

        esconderSeVazio(R.id.titulo_equipas, lista, equipas.length())
    }

    // as secções sem nada ficam escondidas, com o título
    private fun esconderSeVazio(titulo: Int, lista: LinearLayout, quantos: Int) {
        val visivel = if (quantos > 0) View.VISIBLE else View.GONE
        requireView().findViewById<TextView>(titulo).visibility = visivel
        lista.visibility = visivel
    }

    private fun sairDaConta() {
        val contexto = requireContext().applicationContext

        Thread {
            // o token deixa de valer no site
            Api.pedir(contexto, "logout.php", mapOf(), true)
            activity?.runOnUiThread {
                Sessao.sair(contexto)
                Avisos.reiniciar(contexto)
                val intent = Intent(contexto, LoginActivity::class.java)
                intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
                startActivity(intent)
            }
        }.start()
    }
}
