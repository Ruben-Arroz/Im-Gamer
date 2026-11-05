package pt.ua.imgamer

import android.app.Activity
import android.content.Intent
import android.graphics.Paint
import android.view.View
import android.widget.TextView
import androidx.core.content.ContextCompat
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import org.json.JSONObject

// funções usadas em vários ecrãs

// a app ocupa o ecrã todo, por isso o conteúdo leva margens para não ficar por baixo das barras do telemóvel e do teclado
fun ajustarAoEcra(view: View) {
    ViewCompat.setOnApplyWindowInsetsListener(view) { v, insets ->
        val barras = insets.getInsets(WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.ime())
        v.setPadding(barras.left, barras.top, barras.right, barras.bottom)
        insets
    }
}

// se o site disser que o token já não vale, apaga a sessão e volta ao login. devolve true nesse caso
fun semSessao(activity: Activity, resposta: JSONObject): Boolean {
    if (!resposta.optBoolean("sem_sessao")) {
        return false
    }

    Sessao.sair(activity)
    val intent = Intent(activity, LoginActivity::class.java)
    intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
    activity.startActivity(intent)
    return true
}

// o optString do json devolve o texto "null" quando o valor é null, isto devolve mesmo null
fun texto(json: JSONObject, nome: String): String? {
    if (!json.has(nome) || json.isNull(nome)) {
        return null
    }
    return json.getString(nome)
}

// mostra o texto, ou esconde a caixa se não houver texto
fun mostrarOuEsconder(campo: TextView, valor: String?) {
    if (valor == null || valor == "") {
        campo.visibility = View.GONE
    } else {
        campo.text = valor
        campo.visibility = View.VISIBLE
    }
}

// para os textos que se podem carregar (nomes de jogadores e de jogos)
fun sublinhar(campo: TextView) {
    campo.paintFlags = campo.paintFlags or Paint.UNDERLINE_TEXT_FLAG
}

// aba escolhida a branco, as outras só com o contorno
fun marcarAba(aba: TextView, ativa: Boolean) {
    if (ativa) {
        aba.setBackgroundResource(R.drawable.aba_ativa)
        aba.setTextColor(ContextCompat.getColor(aba.context, R.color.fundo))
    } else {
        aba.setBackgroundResource(R.drawable.caixa)
        aba.setTextColor(ContextCompat.getColor(aba.context, R.color.texto))
    }
}

fun abrirPerfil(activity: Activity, nome: String) {
    val intent = Intent(activity, PerfilActivity::class.java)
    intent.putExtra("nome", nome)
    activity.startActivity(intent)
}

fun abrirJogo(activity: Activity, id: Int) {
    val intent = Intent(activity, JogoActivity::class.java)
    intent.putExtra("id", id)
    activity.startActivity(intent)
}

fun abrirAnuncio(activity: Activity, id: Int) {
    val intent = Intent(activity, AnuncioActivity::class.java)
    intent.putExtra("id", id)
    activity.startActivity(intent)
}
