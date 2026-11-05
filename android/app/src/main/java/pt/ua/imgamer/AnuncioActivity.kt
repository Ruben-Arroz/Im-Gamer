package pt.ua.imgamer

import android.graphics.Color
import android.os.Bundle
import android.view.LayoutInflater
import android.view.View
import android.widget.Button
import android.widget.ImageView
import android.widget.LinearLayout
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import org.json.JSONObject

// um anúncio de LFG: dados, quem está na equipa e os botões de entrar e sair
class AnuncioActivity : AppCompatActivity() {

    private var id = 0

    private lateinit var carregar: ProgressBar
    private lateinit var erro: TextView
    private lateinit var conteudo: LinearLayout
    private lateinit var entrar: Button
    private lateinit var sair: Button

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_anuncio)
        ajustarAoEcra(findViewById(R.id.main))

        id = intent.getIntExtra("id", 0)
        carregar = findViewById(R.id.carregar)
        erro = findViewById(R.id.erro)
        conteudo = findViewById(R.id.conteudo)
        entrar = findViewById(R.id.entrar)
        sair = findViewById(R.id.sair)

        entrar.setOnClickListener { pedir(mapOf("id" to id.toString(), "acao" to "entrar"), true) }
        sair.setOnClickListener {
            AlertDialog.Builder(this)
                .setMessage("Sair desta equipa?")
                .setPositiveButton("Sair") { _, _ -> pedir(mapOf("id" to id.toString(), "acao" to "sair"), true) }
                .setNegativeButton("Cancelar", null)
                .show()
        }

        pedir(mapOf("id" to id.toString()), false)
    }

    // sem post vai buscar o anúncio, com post entra ou sai. nos dois casos o site devolve o anúncio atualizado
    private fun pedir(parametros: Map<String, String>, post: Boolean) {
        carregar.visibility = View.VISIBLE
        erro.visibility = View.GONE
        entrar.isEnabled = false
        sair.isEnabled = false

        Thread {
            val resposta = Api.pedir(this, "lfg_ver.php", parametros, post)
            runOnUiThread { mostrar(resposta) }
        }.start()
    }

    private fun mostrar(resposta: JSONObject) {
        carregar.visibility = View.GONE
        entrar.isEnabled = true
        sair.isEnabled = true

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

        val jogo = findViewById<TextView>(R.id.jogo)
        jogo.text = resposta.getString("jogo")
        jogo.setOnClickListener { abrirJogo(this, resposta.getInt("jogo_id")) }

        findViewById<TextView>(R.id.plataforma).text = resposta.getString("plataforma")

        val dono = findViewById<TextView>(R.id.dono)
        dono.text = "Publicado por " + resposta.getString("dono")
        sublinhar(dono)
        dono.setOnClickListener { abrirPerfil(this, resposta.getString("dono")) }

        findViewById<TextView>(R.id.no_ar).text = if (resposta.getBoolean("saiu_do_ar")) {
            "Saiu do ar a " + resposta.getString("no_ar_ate")
        } else {
            "No ar até " + resposta.getString("no_ar_ate")
        }
        findViewById<TextView>(R.id.estado).text = resposta.getString("estado")
        findViewById<TextView>(R.id.descricao).text = resposta.getString("descricao")

        // o discord só vem para o dono e para quem está na equipa
        val discord = texto(resposta, "discord")
        mostrarOuEsconder(findViewById(R.id.discord), if (discord != null) "Discord: $discord" else null)

        mostrarParticipantes(resposta)

        entrar.visibility = if (resposta.getBoolean("pode_entrar")) View.VISIBLE else View.GONE
        sair.visibility = if (resposta.getBoolean("participo")) View.VISIBLE else View.GONE
        findViewById<TextView>(R.id.nota_dono).visibility = if (resposta.getBoolean("sou_dono")) View.VISIBLE else View.GONE
    }

    private fun mostrarParticipantes(resposta: JSONObject) {
        val lista = findViewById<LinearLayout>(R.id.participantes)
        lista.removeAllViews()

        val participantes = resposta.getJSONArray("participantes")
        for (i in 0 until participantes.length()) {
            val nome = participantes.getJSONObject(i).getString("nome")
            val linha = LayoutInflater.from(this).inflate(R.layout.item_linha, lista, false)
            linha.findViewById<TextView>(R.id.titulo).text = nome
            linha.findViewById<TextView>(R.id.detalhe).text = "Ver perfil"
            linha.setOnClickListener { abrirPerfil(this, nome) }
            lista.addView(linha)
        }

        findViewById<TextView>(R.id.sem_participantes).visibility = if (participantes.length() == 0) View.VISIBLE else View.GONE
    }
}
