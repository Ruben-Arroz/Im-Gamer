package pt.ua.imgamer

import android.graphics.Color
import android.os.Bundle
import androidx.activity.SystemBarStyle
import androidx.activity.enableEdgeToEdge
import androidx.appcompat.app.AppCompatActivity

// perfil de outro jogador, usa o mesmo ecrã da aba Perfil (PerfilFragment)
class PerfilActivity : AppCompatActivity() {

    override fun onCreate(savedInstanceState: Bundle?) {
        super.onCreate(savedInstanceState)
        enableEdgeToEdge(SystemBarStyle.dark(Color.TRANSPARENT), SystemBarStyle.dark(Color.TRANSPARENT))
        setContentView(R.layout.activity_perfil)
        ajustarAoEcra(findViewById(R.id.main))

        if (savedInstanceState == null) {
            supportFragmentManager.beginTransaction()
                .replace(R.id.conteudo, PerfilFragment.novo(intent.getStringExtra("nome")))
                .commit()
        }
    }
}
