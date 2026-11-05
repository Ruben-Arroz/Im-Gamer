package pt.ua.imgamer

import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.CheckBox
import android.widget.EditText
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.fragment.app.Fragment
import org.json.JSONObject

// aba do LFG: anúncios no ar ou os meus, com a pesquisa por jogo e o "só com vagas"
class LfgFragment : Fragment(R.layout.fragment_lfg) {

    private var aba = "proximos"
    private var pagina = 1
    // conta os pedidos, para ignorar a resposta de um pedido antigo se entretanto se mudou o filtro
    private var pedido = 0

    private lateinit var abaAnuncios: TextView
    private lateinit var abaMeus: TextView
    private lateinit var pesquisa: EditText
    private lateinit var soVagas: CheckBox
    private lateinit var total: TextView
    private lateinit var erro: TextView
    private lateinit var carregar: ProgressBar
    private lateinit var lista: LinearLayout
    private lateinit var mais: Button

    override fun onViewCreated(view: View, savedInstanceState: Bundle?) {
        abaAnuncios = view.findViewById(R.id.aba_anuncios)
        abaMeus = view.findViewById(R.id.aba_meus)
        pesquisa = view.findViewById(R.id.pesquisa)
        soVagas = view.findViewById(R.id.so_vagas)
        total = view.findViewById(R.id.total)
        erro = view.findViewById(R.id.erro)
        carregar = view.findViewById(R.id.carregar)
        lista = view.findViewById(R.id.lista)
        mais = view.findViewById(R.id.mais)

        abaAnuncios.setOnClickListener {
            aba = "proximos"
            carregarAnuncios(true)
        }
        abaMeus.setOnClickListener {
            aba = "meus"
            carregarAnuncios(true)
        }
        view.findViewById<Button>(R.id.procurar).setOnClickListener { carregarAnuncios(true) }
        // o botão de pesquisa do teclado também procura
        pesquisa.setOnEditorActionListener { _, _, _ ->
            carregarAnuncios(true)
            true
        }
        soVagas.setOnCheckedChangeListener { _, _ -> carregarAnuncios(true) }
        mais.setOnClickListener {
            pagina++
            carregarAnuncios(false)
        }
    }

    // carrega ao abrir e também ao voltar de um anúncio (pode-se ter entrado ou saído da equipa)
    override fun onResume() {
        super.onResume()
        carregarAnuncios(true)
    }

    // do zero (filtro novo) ou a página seguinte, que é posta no fim da lista
    private fun carregarAnuncios(doZero: Boolean) {
        if (doZero) {
            pagina = 1
            lista.removeAllViews()
        }

        marcarAba(abaAnuncios, aba == "proximos")
        marcarAba(abaMeus, aba == "meus")
        erro.visibility = View.GONE
        mais.visibility = View.GONE
        carregar.visibility = View.VISIBLE

        val parametros = mapOf(
            "aba" to aba,
            "jogo" to pesquisa.text.toString().trim(),
            "vagas" to if (soVagas.isChecked) "1" else "0",
            "pagina" to pagina.toString()
        )

        pedido++
        val estePedido = pedido
        val contexto = requireContext().applicationContext

        Thread {
            val resposta = Api.pedir(contexto, "lfg.php", parametros)
            activity?.runOnUiThread {
                if (isAdded && estePedido == pedido) {
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

        val quantos = resposta.getInt("total")
        total.text = when (quantos) {
            0 -> "Nenhum anúncio encontrado."
            1 -> "1 anúncio"
            else -> "$quantos anúncios"
        }

        val anuncios = resposta.getJSONArray("anuncios")
        for (i in 0 until anuncios.length()) {
            lista.addView(criarCartao(anuncios.getJSONObject(i)))
        }

        if (pagina < resposta.getInt("paginas")) {
            mais.visibility = View.VISIBLE
        }
    }

    private fun criarCartao(anuncio: JSONObject): View {
        val cartao = LayoutInflater.from(requireContext()).inflate(R.layout.item_anuncio, lista, false)

        Imagens.mostrar(requireActivity(), texto(anuncio, "imagem"), cartao.findViewById<ImageView>(R.id.imagem))
        cartao.findViewById<TextView>(R.id.jogo).text = anuncio.getString("jogo")
        cartao.findViewById<TextView>(R.id.detalhe).text = anuncio.getString("plataforma") + " · de " + anuncio.getString("dono")
        cartao.findViewById<TextView>(R.id.no_ar).text = "No ar até " + anuncio.getString("no_ar_ate")
        cartao.findViewById<TextView>(R.id.estado).text = anuncio.getString("estado")

        // os que já saíram do ar ficam mais apagados, como no site
        if (anuncio.getBoolean("saiu_do_ar")) {
            cartao.alpha = 0.55f
        }

        cartao.setOnClickListener { abrirAnuncio(requireActivity(), anuncio.getInt("id")) }
        return cartao
    }
}
