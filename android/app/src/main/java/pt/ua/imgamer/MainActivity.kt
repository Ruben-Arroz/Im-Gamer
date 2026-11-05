package pt.ua.imgamer

import android.Manifest
import android.content.Intent
import android.content.pm.PackageManager
import android.graphics.Color
import android.os.Build
import android.os.Bundle
import android.os.Handler
import android.os.Looper
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity
import androidx.fragment.app.Fragment

// ecrã principal: as quatro abas do fundo e a verificação dos convites e avisos
class MainActivity : AppCompatActivity() {

    // de quanto em quanto tempo pergunta ao site se há novidades (30 segundos)
    private val intervalo = 30000L

    private lateinit var abaLfg: TextView
    private lateinit var abaConvites: TextView
    private lateinit var abaPesquisa: TextView
    private lateinit var abaPerfil: TextView

    private val relogio = Handler(Looper.getMainLooper())
    private val verificarAvisos = object : Runnable {
        override fun run() {
            Thread {
                val pendentes = Avisos.verificar(applicationContext)
                runOnUiThread { mostrarContador(pendentes) }
            }.start()
            relogio.postDelayed(this, intervalo)
        }
    }

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_main)
        ajustarAoEcra(findViewById(R.id.main))

        abaLfg = findViewById(R.id.aba_lfg)
        abaConvites = findViewById(R.id.aba_convites)
        abaPesquisa = findViewById(R.id.aba_pesquisa)
        abaPerfil = findViewById(R.id.aba_perfil)

        abaLfg.setOnClickListener { abrirAba("lfg") }
        abaConvites.setOnClickListener { abrirAba("convites") }
        abaPesquisa.setOnClickListener { abrirAba("pesquisa") }
        abaPerfil.setOnClickListener { abrirAba("perfil") }

        Avisos.criarCanal(this)
        pedirAutorizacaoNotificacoes()

        // quando a app abre pela notificação vai para os convites, senão para o LFG
        if (savedInstanceState == null) {
            abrirAba(intent.getStringExtra("aba") ?: "lfg")
        }

        // enquanto a app estiver aberta (mesmo em segundo plano) vai perguntando ao site
        relogio.post(verificarAvisos)
    }

    // a app já estava aberta e carregou-se numa notificação
    override fun onNewIntent(intent: Intent) {
        super.onNewIntent(intent)
        abrirAba(intent.getStringExtra("aba") ?: "lfg")
    }

    override fun onDestroy() {
        super.onDestroy()
        relogio.removeCallbacks(verificarAvisos)
    }

    // desde o android 13 é preciso pedir autorização para mostrar notificações
    private fun pedirAutorizacaoNotificacoes() {
        if (Build.VERSION.SDK_INT >= 33 &&
            checkSelfPermission(Manifest.permission.POST_NOTIFICATIONS) != PackageManager.PERMISSION_GRANTED
        ) {
            requestPermissions(arrayOf(Manifest.permission.POST_NOTIFICATIONS), 1)
        }
    }

    fun abrirAba(aba: String) {
        val fragmento: Fragment = when (aba) {
            "convites" -> ConvitesFragment()
            "pesquisa" -> PesquisaFragment()
            "perfil" -> PerfilFragment.novo(null)
            else -> LfgFragment()
        }

        supportFragmentManager.beginTransaction()
            .replace(R.id.conteudo, fragmento)
            .commit()

        marcarAba(abaLfg, aba == "lfg")
        marcarAba(abaConvites, aba == "convites")
        marcarAba(abaPesquisa, aba == "pesquisa")
        marcarAba(abaPerfil, aba == "perfil")
    }

    // número de convites por responder ao lado do nome da aba, como no site
    fun mostrarContador(pendentes: Int) {
        if (pendentes > 0) {
            abaConvites.text = "Convites ($pendentes)"
        } else if (pendentes == 0) {
            abaConvites.text = "Convites"
        }
    }
}
