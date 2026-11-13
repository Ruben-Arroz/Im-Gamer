package pt.ua.imgamer

import android.content.Intent
import android.graphics.Color
import android.os.Bundle
import android.view.View
import android.widget.Button
import android.widget.EditText
import android.widget.ProgressBar
import android.widget.TextView
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity

class LoginActivity : AppCompatActivity() {

    private lateinit var email: EditText
    private lateinit var password: EditText
    private lateinit var erro: TextView
    private lateinit var entrar: Button
    private lateinit var carregar: ProgressBar

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)

        // se já entrou antes, vai direto para a app
        if (Sessao.entrou(this)) {
            abrirApp()
            return
        }

        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_login)
        ajustarAoEcra(findViewById(R.id.main))

        email = findViewById(R.id.email)
        password = findViewById(R.id.password)
        erro = findViewById(R.id.erro)
        entrar = findViewById(R.id.entrar)
        carregar = findViewById(R.id.carregar)

        entrar.setOnClickListener { fazerLogin() }
    }

    private fun fazerLogin() {
        val parametros = mapOf("email" to email.text.toString().trim(), "password" to password.text.toString())

        if (parametros["email"] == "" || parametros["password"] == "") {
            mostrarErro(getString(R.string.campos_vazios))
            return
        }

        erro.visibility = View.GONE
        entrar.isEnabled = false
        carregar.visibility = View.VISIBLE

        Thread {
            val resposta = Api.pedir(this, "login.php", parametros, true)

            runOnUiThread {
                entrar.isEnabled = true
                carregar.visibility = View.GONE

                if (resposta.optBoolean("sem_ligacao")) {
                    mostrarErro(resposta.getString("erro"))
                } else if (resposta.has("erro")) {
                    // o site só dá erro aqui quando o email ou a password estão mal
                    mostrarErro(getString(R.string.login_errado))
                } else {
                    Sessao.guardar(this, resposta.getString("token"), resposta.getString("nome"))
                    abrirApp()
                }
            }
        }.start()
    }

    private fun mostrarErro(mensagem: String) {
        erro.text = mensagem
        erro.visibility = View.VISIBLE
    }

    private fun abrirApp() {
        startActivity(Intent(this, MainActivity::class.java))
        finish()
    }
}
