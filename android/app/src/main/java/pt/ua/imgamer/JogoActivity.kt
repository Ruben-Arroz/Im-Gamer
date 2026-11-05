package pt.ua.imgamer

import android.graphics.Color
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.RatingBar
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONObject

// um jogo: dados, a média das avaliações e o formulário para avaliar (1 a 5 estrelas e um comentário)
class JogoActivity : AppCompatActivity() {

    private var id = 0

    private lateinit var carregar: ProgressBar
    private lateinit var erro: TextView
    private lateinit var conteudo: LinearLayout
    private lateinit var estrelas: RatingBar
    private lateinit var comentario: EditText
    private lateinit var mensagem: TextView
    private lateinit var guardar: Button
    private lateinit var apagar: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_jogo)
        ajustarAoEcra(findViewById(R.id.main))

        id = intent.getIntExtra("id", 0)
        carregar = findViewById(R.id.carregar)
        erro = findViewById(R.id.erro)
        conteudo = findViewById(R.id.conteudo)
        estrelas = findViewById(R.id.estrelas)
        comentario = findViewById(R.id.comentario)
        mensagem = findViewById(R.id.mensagem)
        guardar = findViewById(R.id.guardar)
        apagar = findViewById(R.id.apagar)

        guardar.setOnClickListener { avaliar() }
        apagar.setOnClickListener {
            AlertDialog.Builder(this)
                .setMessage("Apagar a tua avaliação deste jogo?")
                .setPositiveButton("Apagar") { _, _ -> apagarAvaliacao() }
                .setNegativeButton("Cancelar", null)
                .show()
        }

        carregarJogo()
    }

    private fun carregarJogo() {
        carregar.visibility = View.VISIBLE

        Thread {
            val resposta = Api.pedir(this, "jogo.php", mapOf("id" to id.toString()))
            runOnUiThread { mostrar(resposta) }
        }.start()
    }

    private fun mostrar(resposta: JSONObject) {
        carregar.visibility = View.GONE

        if (semSessao(this, resposta)) {
            return
        }

        if (resposta.has("erro")) {
            erro.text = resposta.getString("erro")
            erro.visibility = View.VISIBLE
            return
        }

        conteudo.visibility = View.VISIBLE
        Imagens.mostrar(this, texto(resposta, "imagem"), findViewById<ImageView>(R.id.imagem))
        findViewById<TextView>(R.id.nome).text = resposta.getString("nome")

        // lançamento e produtora numa linha, só o que houver
        val detalhes = listOfNotNull(texto(resposta, "lancamento"), texto(resposta, "produtora")).joinToString(" · ")
        mostrarOuEsconder(findViewById(R.id.detalhes), detalhes)
        mostrarOuEsconder(findViewById(R.id.descricao), texto(resposta, "descricao"))

        val total = resposta.getInt("total_avaliacoes")
        findViewById<TextView>(R.id.media).text = if (total == 0) {
            "Ainda sem avaliações"
        } else {
            val avaliacoes = if (total == 1) "1 avaliação" else "$total avaliações"
            resposta.getDouble("media").toString().replace('.', ',') + " de 5 (" + avaliacoes + ")"
        }

        // se já avaliou, o formulário vem com a avaliação dele
        if (resposta.isNull("minha_pontuacao")) {
            findViewById<TextView>(R.id.titulo_avaliar).text = "Avaliar este jogo"
            apagar.visibility = View.GONE
        } else {
            findViewById<TextView>(R.id.titulo_avaliar).text = "A tua avaliação"
            estrelas.rating = resposta.getInt("minha_pontuacao").toFloat()
            comentario.setText(texto(resposta, "meu_comentario") ?: "")
            apagar.visibility = View.VISIBLE
        }

        val lista = findViewById<LinearLayout>(R.id.avaliacoes)
        lista.removeAllViews()
        val avaliacoes = resposta.getJSONArray("avaliacoes")
        for (i in 0 until avaliacoes.length()) {
            val avaliacao = avaliacoes.getJSONObject(i)
            val linha = LayoutInflater.from(this).inflate(R.layout.item_linha, lista, false)
            val pontuacao = avaliacao.getInt("pontuacao")
            // estrelas cheias e vazias, como no site
            linha.findViewById<TextView>(R.id.titulo).text = "★".repeat(pontuacao) + "☆".repeat(5 - pontuacao) + "  " + avaliacao.getString("nome")

            val textoComentario = texto(avaliacao, "comentario")
            val data = avaliacao.getString("data")
            linha.findViewById<TextView>(R.id.detalhe).text = if (textoComentario != null) "$textoComentario\n$data" else data

            val nome = avaliacao.getString("nome")
            linha.setOnClickListener { abrirPerfil(this, nome) }
            lista.addView(linha)
        }
        findViewById<TextView>(R.id.sem_avaliacoes).visibility = if (avaliacoes.length() == 0) View.VISIBLE else View.GONE
    }

    private fun avaliar() {
        val parametros = mapOf(
            "jogo_id" to id.toString(),
            "pontuacao" to estrelas.rating.toInt().toString(),
            "comentario" to comentario.text.toString().trim()
        )
        enviar(parametros, "Avaliação guardada.")
    }

    private fun apagarAvaliacao() {
        estrelas.rating = 5f
        comentario.setText("")
        enviar(mapOf("jogo_id" to id.toString(), "acao" to "apagar"), "Avaliação apagada.")
    }

    private fun enviar(parametros: Map<String, String>, textoSucesso: String) {
        guardar.isEnabled = false
        apagar.isEnabled = false
        mensagem.visibility = View.GONE

        Thread {
            val resposta = Api.pedir(this, "avaliar.php", parametros, true)
            runOnUiThread {
                guardar.isEnabled = true
                apagar.isEnabled = true

                if (!semSessao(this, resposta)) {
                    mensagem.text = if (resposta.has("erro")) resposta.getString("erro") else textoSucesso
                    mensagem.visibility = View.VISIBLE

                    // volta a carregar para a média e a lista ficarem certas
                    if (!resposta.has("erro")) {
                        carregarJogo()
                    }
                }
            }
        }.start()
    }
}
