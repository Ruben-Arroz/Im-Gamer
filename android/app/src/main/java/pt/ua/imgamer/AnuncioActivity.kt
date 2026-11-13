package pt.ua.imgamer

import android.graphics.Color
import android.os.Bundle
import android.view.View
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.Toolbar
import org.json.JSONObject

// detalhe de um anúncio de LFG, aberto ao carregar num cartão da lista
class AnuncioActivity : AppCompatActivity() {

    private lateinit var carregar: ProgressBar
    private lateinit var erro: TextView
    private lateinit var conteudo: LinearLayout

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_anuncio)
        ajustarAoEcra(findViewById(R.id.main))

        // seta na barra de cima para voltar à lista
        setSupportActionBar(findViewById<Toolbar>(R.id.barra))
        supportActionBar?.title = getString(R.string.anuncio)
        supportActionBar?.setDisplayHomeAsUpEnabled(true)

        carregar = findViewById(R.id.carregar)
        erro = findViewById(R.id.erro)
        conteudo = findViewById(R.id.conteudo)

        val id = intent.getIntExtra("id", 0)

        Thread {
            val resposta = Api.pedir(this, "lfg_ver.php", mapOf("id" to id.toString()))
            runOnUiThread { mostrar(resposta) }
        }.start()
    }

    override fun onSupportNavigateUp(): Boolean {
        finish()
        return true
    }

    private fun mostrar(resposta: JSONObject) {
        carregar.visibility = View.GONE

        if (semSessao(this, resposta)) {
            return
        }

        if (resposta.has("erro")) {
            // o único erro do site aqui é o anúncio já não existir
            erro.text = if (resposta.optBoolean("sem_ligacao")) resposta.getString("erro") else getString(R.string.anuncio_nao_existe)
            erro.visibility = View.VISIBLE
            return
        }

        conteudo.visibility = View.VISIBLE
        Imagens.mostrar(this, texto(resposta, "imagem"), findViewById<ImageView>(R.id.imagem))

        findViewById<TextView>(R.id.jogo).text = resposta.getString("jogo")
        findViewById<TextView>(R.id.plataforma).text = plataformaAnuncio(this, resposta)
        findViewById<TextView>(R.id.dono).text = getString(R.string.publicado_por, resposta.getString("dono"))

        val data = dataAnuncio(resposta)
        findViewById<TextView>(R.id.no_ar).text = if (resposta.getBoolean("saiu_do_ar")) {
            getString(R.string.saiu_do_ar_a, data)
        } else {
            getString(R.string.no_ar_ate, data)
        }

        findViewById<TextView>(R.id.estado).text = estadoAnuncio(this, resposta)
        findViewById<TextView>(R.id.descricao).text = resposta.getString("descricao")

        // os nomes de quem já entrou, um por linha
        val participantes = resposta.getJSONArray("participantes")
        var nomes = ""
        for (i in 0 until participantes.length()) {
            if (nomes != "") {
                nomes += "\n"
            }
            nomes += participantes.getJSONObject(i).getString("nome")
        }
        findViewById<TextView>(R.id.participantes).text = if (nomes == "") getString(R.string.ninguem_na_equipa) else nomes
    }
}
