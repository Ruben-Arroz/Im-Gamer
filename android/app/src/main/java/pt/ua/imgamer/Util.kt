package pt.ua.imgamer

import android.app.Activity
import android.content.Context
import android.content.Intent
import android.view.View
import androidx.core.view.ViewCompat
import androidx.core.view.WindowInsetsCompat
import org.json.JSONObject
import java.time.LocalDate
import java.time.format.DateTimeFormatter
import java.util.Locale

// funções usadas em vários ecrãs

// a app ocupa o ecrã todo ent isto dá margem para não ficar por baixo das barras e do teclado
fun ajustarAoEcra(view: View) {
    ViewCompat.setOnApplyWindowInsetsListener(view) { v, insets ->
        val barras = insets.getInsets(WindowInsetsCompat.Type.systemBars() or WindowInsetsCompat.Type.ime())
        v.setPadding(barras.left, barras.top, barras.right, barras.bottom)
        insets
    }
}

// se o token já não vale volta ao login (e devolve true)
fun semSessao(activity: Activity, resposta: JSONObject): Boolean {
    if (!resposta.optBoolean("sem_sessao")) {
        return false
    }

    voltarAoLogin(activity)
    return true
}

fun voltarAoLogin(activity: Activity) {
    Sessao.sair(activity)
    val intent = Intent(activity, LoginActivity::class.java)
    intent.flags = Intent.FLAG_ACTIVITY_NEW_TASK or Intent.FLAG_ACTIVITY_CLEAR_TASK
    activity.startActivity(intent)
}

// o optString devolve o texto "null" em vez de null ent usa-se esta
fun texto(json: JSONObject, nome: String): String? {
    if (!json.has(nome) || json.isNull(nome)) {
        return null
    }
    return json.getString(nome)
}

// o estado é feito aqui e não no site pq tem de ficar na língua do telemóvel
fun estadoAnuncio(context: Context, anuncio: JSONObject): String {
    if (anuncio.getBoolean("saiu_do_ar")) {
        return context.getString(R.string.ja_saiu_do_ar)
    }
    if (anuncio.getBoolean("fechado")) {
        return context.getString(R.string.fechado)
    }

    val livres = anuncio.getInt("vagas_livres")
    if (livres == 0) {
        return context.getString(R.string.equipa_completa)
    }
    return context.resources.getQuantityString(R.plurals.faltam_jogadores, livres, livres)
}

// null é qualquer plataforma
fun plataformaAnuncio(context: Context, anuncio: JSONObject): String {
    return texto(anuncio, "plataforma") ?: context.getString(R.string.qualquer_plataforma)
}

// fica "sex, 20/11" ou "Fri, 20/11" conforme a língua
fun dataAnuncio(anuncio: JSONObject): String {
    val data = LocalDate.parse(anuncio.getString("data_fim"))
    return data.format(DateTimeFormatter.ofPattern("EEE, dd/MM", Locale.getDefault()))
}
