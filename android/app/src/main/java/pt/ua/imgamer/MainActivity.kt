package pt.ua.imgamer

import android.graphics.Color
import android.os.Bundle
import android.view.Menu
import android.view.MenuItem
import android.view.View
import android.widget.Button
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AlertDialog
import androidx.appcompat.app.AppCompatActivity
import androidx.appcompat.widget.Toolbar
import androidx.recyclerview.widget.LinearLayoutManager
import androidx.recyclerview.widget.RecyclerView
import org.json.JSONObject

// ecrã principal: os anúncios de LFG que estão no ar e ainda têm vagas
class MainActivity : AppCompatActivity() {

    private var pagina = 1

    private lateinit var total: TextView
    private lateinit var erro: TextView
    private lateinit var carregar: ProgressBar
    private lateinit var mais: Button
    private lateinit var adapter: AnunciosAdapter

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_main)
        ajustarAoEcra(findViewById(R.id.main))

        // a barra de cima faz de action bar, para ter o menu
        setSupportActionBar(findViewById<Toolbar>(R.id.barra))
        supportActionBar?.setDisplayShowTitleEnabled(false)

        total = findViewById(R.id.total)
        erro = findViewById(R.id.erro)
        carregar = findViewById(R.id.carregar)
        mais = findViewById(R.id.mais)

        adapter = AnunciosAdapter(this)
        val lista = findViewById<RecyclerView>(R.id.lista)
        lista.layoutManager = LinearLayoutManager(this)
        lista.adapter = adapter

        mais.setOnClickListener {
            pagina++
            carregarAnuncios()
        }

        carregarAnuncios()
    }

    override fun onCreateOptionsMenu(menu: Menu): Boolean {
        menuInflater.inflate(R.menu.menu_principal, menu)
        return true
    }

    override fun onOptionsItemSelected(item: MenuItem): Boolean {
        when (item.itemId) {
            R.id.menu_atualizar -> {
                pagina = 1
                adapter.limpar()
                carregarAnuncios()
                return true
            }
            R.id.menu_sair -> {
                perguntarSair()
                return true
            }
        }
        return super.onOptionsItemSelected(item)
    }

    private fun carregarAnuncios() {
        erro.visibility = View.GONE
        mais.visibility = View.GONE
        carregar.visibility = View.VISIBLE

        val parametros = mapOf("pagina" to pagina.toString())

        Thread {
            val resposta = Api.pedir(this, "lfg.php", parametros)
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

        val quantos = resposta.getInt("total")
        total.text = if (quantos == 0) {
            getString(R.string.sem_anuncios)
        } else {
            resources.getQuantityString(R.plurals.total_anuncios, quantos, quantos)
        }

        val json = resposta.getJSONArray("anuncios")
        val anuncios = ArrayList<JSONObject>()
        for (i in 0 until json.length()) {
            anuncios.add(json.getJSONObject(i))
        }
        adapter.juntar(anuncios)

        if (pagina < resposta.getInt("paginas")) {
            mais.visibility = View.VISIBLE
        }
    }

    private fun perguntarSair() {
        AlertDialog.Builder(this)
            .setMessage(R.string.sair_pergunta)
            .setPositiveButton(R.string.sair) { _, _ -> sair() }
            .setNegativeButton(R.string.cancelar, null)
            .show()
    }

    // avisa o site para apagar o token e depois apaga a sessão guardada no telemóvel
    private fun sair() {
        Thread {
            Api.pedir(this, "logout.php", mapOf(), true)
            runOnUiThread { voltarAoLogin(this) }
        }.start()
    }
}
