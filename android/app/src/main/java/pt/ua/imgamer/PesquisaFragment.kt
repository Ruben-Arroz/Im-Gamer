package pt.ua.imgamer

import android.content.Context
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.view.inputmethod.InputMethodManager
import android.widget.Button
import android.widget.EditText
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.fragment.app.Fragment
import org.json.JSONObject

// aba da pesquisa: jogadores (para ver o perfil) e jogos guardados no site (para avaliar)
class PesquisaFragment : Fragment(R.layout.fragment_pesquisa) {

    private lateinit var texto: EditText
    private lateinit var aviso: TextView
    private lateinit var carregar: ProgressBar
    private lateinit var resultados: LinearLayout
    private lateinit var jogadores: LinearLayout
    private lateinit var jogos: LinearLayout

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        texto = view.findViewById(R.id.texto)
        aviso = view.findViewById(R.id.aviso)
        carregar = view.findViewById(R.id.carregar)
        resultados = view.findViewById(R.id.resultados)
        jogadores = view.findViewById(R.id.jogadores)
        jogos = view.findViewById(R.id.jogos)

        view.findViewById<Button>(R.id.procurar).setOnClickListener { procurar() }
        texto.setOnEditorActionListener { _, _, _ ->
            procurar()
            true
        }
    }

    private fun procurar() {
        val pesquisa = texto.text.toString().trim()
        if (pesquisa.length < 2) {
            aviso.text = "Escreve pelo menos 2 letras."
            aviso.visibility = View.VISIBLE
            return
        }

        // fecha o teclado para se verem os resultados
        val teclado = requireContext().getSystemService(Context.INPUT_METHOD_SERVICE) as InputMethodManager
        teclado.hideSoftInputFromWindow(texto.windowToken, 0)

        aviso.visibility = View.GONE
        carregar.visibility = View.VISIBLE
        val contexto = requireContext().applicationContext

        Thread {
            val resposta = Api.pedir(contexto, "pesquisa.php", mapOf("q" to pesquisa))
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
            aviso.text = resposta.getString("erro")
            aviso.visibility = View.VISIBLE
            return
        }

        jogadores.removeAllViews()
        jogos.removeAllViews()

        val listaJogadores = resposta.getJSONArray("jogadores")
        for (i in 0 until listaJogadores.length()) {
            val jogador = listaJogadores.getJSONObject(i)
            val nome = jogador.getString("nome")
            val linha = criarLinha(jogadores, nome, texto(jogador, "foto"))
            linha.setOnClickListener { abrirPerfil(requireActivity(), nome) }
            jogadores.addView(linha)
        }

        val listaJogos = resposta.getJSONArray("jogos")
        for (i in 0 until listaJogos.length()) {
            val jogo = listaJogos.getJSONObject(i)
            val id = jogo.getInt("id")
            val linha = criarLinha(jogos, jogo.getString("nome"), texto(jogo, "imagem"))
            linha.setOnClickListener { abrirJogo(requireActivity(), id) }
            jogos.addView(linha)
        }

        // a parte que não tem resultados fica escondida, com o título
        val view = requireView()
        view.findViewById<TextView>(R.id.titulo_jogadores).visibility = if (listaJogadores.length() > 0) View.VISIBLE else View.GONE
        view.findViewById<TextView>(R.id.titulo_jogos).visibility = if (listaJogos.length() > 0) View.VISIBLE else View.GONE

        if (listaJogadores.length() == 0 && listaJogos.length() == 0) {
            aviso.text = "Nada encontrado."
            aviso.visibility = View.VISIBLE
            resultados.visibility = View.GONE
        } else {
            resultados.visibility = View.VISIBLE
        }
    }

    private fun criarLinha(lista: LinearLayout, nome: String, imagem: String?): View {
        val linha = LayoutInflater.from(requireContext()).inflate(R.layout.item_resultado, lista, false)
        linha.findViewById<TextView>(R.id.nome).text = nome
        Imagens.mostrar(requireActivity(), imagem, linha.findViewById<ImageView>(R.id.imagem))
        return linha
    }
}
