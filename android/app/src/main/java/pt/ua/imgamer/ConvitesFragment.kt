package pt.ua.imgamer

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.fragment.app.Fragment
import org.json.JSONObject

// aba dos convites: recebidos (aceitar e recusar), avisos do LFG e enviados
class ConvitesFragment : Fragment(R.layout.fragment_convites) {

    private lateinit var carregar: ProgressBar
    private lateinit var erro: TextView
    private lateinit var recebidos: LinearLayout
    private lateinit var avisos: LinearLayout
    private lateinit var enviados: LinearLayout

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        carregar = view.findViewById(R.id.carregar)
        erro = view.findViewById(R.id.erro)
        recebidos = view.findViewById(R.id.recebidos)
        avisos = view.findViewById(R.id.avisos)
        enviados = view.findViewById(R.id.enviados)

        pedir(mapOf(), false)
    }

    // sem post vai buscar a lista, com post responde a um convite. nos dois casos o site devolve tudo atualizado
    private fun pedir(parametros: Map<String, String>, post: Boolean) {
        carregar.visibility = View.VISIBLE
        erro.visibility = View.GONE
        val contexto = requireContext().applicationContext

        Thread {
            val resposta = Api.pedir(contexto, "convites.php", parametros, post)
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

        mostrarRecebidos(resposta)
        mostrarAvisos(resposta)
        mostrarEnviados(resposta)
    }

    private fun mostrarRecebidos(resposta: JSONObject) {
        recebidos.removeAllViews()
        val lista = resposta.getJSONArray("recebidos")
        var pendentes = 0

        for (i in 0 until lista.length()) {
            val convite = lista.getJSONObject(i)
            val item = LayoutInflater.from(requireContext()).inflate(R.layout.item_convite, recebidos, false)

            val de = item.findViewById<TextView>(R.id.de)
            de.text = convite.getString("de")
            sublinhar(de)
            de.setOnClickListener { abrirPerfil(requireActivity(), convite.getString("de")) }

            val jogo = item.findViewById<TextView>(R.id.jogo)
            val nomeJogo = texto(convite, "jogo")
            if (nomeJogo != null) {
                jogo.text = "Para jogar $nomeJogo"
                jogo.setOnClickListener { abrirJogo(requireActivity(), convite.getInt("jogo_id")) }
            } else {
                jogo.text = "Para jogar"
            }

            mostrarOuEsconder(item.findViewById(R.id.mensagem), texto(convite, "mensagem"))
            val discord = texto(convite, "discord")
            mostrarOuEsconder(item.findViewById(R.id.discord), if (discord != null) "Discord: $discord" else null)
            item.findViewById<TextView>(R.id.estado).text = convite.getString("estado") + " · " + convite.getString("data")

            // só os que estão à espera de resposta têm os botões
            if (convite.getBoolean("pendente")) {
                pendentes++
                val id = convite.getInt("id").toString()
                item.findViewById<Button>(R.id.aceitar).setOnClickListener {
                    pedir(mapOf("acao" to "aceitar", "convite_id" to id), true)
                }
                item.findViewById<Button>(R.id.recusar).setOnClickListener {
                    pedir(mapOf("acao" to "recusar", "convite_id" to id), true)
                }
            } else {
                item.findViewById<View>(R.id.botoes).visibility = View.GONE
                item.alpha = 0.7f
            }

            recebidos.addView(item)
        }

        requireView().findViewById<TextView>(R.id.sem_recebidos).visibility = if (lista.length() == 0) View.VISIBLE else View.GONE

        // o número ao lado da aba fica logo certo depois de responder
        (activity as? MainActivity)?.mostrarContador(pendentes)
    }

    private fun mostrarAvisos(resposta: JSONObject) {
        avisos.removeAllViews()
        val lista = resposta.getJSONArray("avisos")

        for (i in 0 until lista.length()) {
            val aviso = lista.getJSONObject(i)
            val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_linha, avisos, false)
            // os avisos por ler têm "novo" à frente, como no site
            val novo = if (aviso.getBoolean("novo")) "Novo · " else ""
            linha.findViewById<TextView>(R.id.titulo).text = aviso.getString("texto")
            linha.findViewById<TextView>(R.id.detalhe).text = novo + aviso.getString("data")
            avisos.addView(linha)
        }

        requireView().findViewById<TextView>(R.id.sem_avisos).visibility = if (lista.length() == 0) View.VISIBLE else View.GONE
    }

    private fun mostrarEnviados(resposta: JSONObject) {
        enviados.removeAllViews()
        val lista = resposta.getJSONArray("enviados")

        for (i in 0 until lista.length()) {
            val convite = lista.getJSONObject(i)
            val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_linha, enviados, false)
            val nomeJogo = texto(convite, "jogo")
            linha.findViewById<TextView>(R.id.titulo).text = if (nomeJogo != null) {
                "Para " + convite.getString("para") + " · " + nomeJogo
            } else {
                "Para " + convite.getString("para")
            }
            linha.findViewById<TextView>(R.id.detalhe).text = convite.getString("estado") + " · " + convite.getString("data")
            linha.setOnClickListener { abrirPerfil(requireActivity(), convite.getString("para")) }
            enviados.addView(linha)
        }

        requireView().findViewById<TextView>(R.id.sem_enviados).visibility = if (lista.length() == 0) View.VISIBLE else View.GONE
    }
}
