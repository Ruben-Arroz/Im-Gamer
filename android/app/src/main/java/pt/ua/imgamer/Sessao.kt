package pt.ua.imgamer

import android.content.Context

// guarda o token e o nome de quem entrou, ficam guardados mesmo depois de fechar a app
object Sessao {

    private fun preferencias(context: Context) = context.getSharedPreferences("sessao", Context.MODE_PRIVATE)

    fun guardar(context: Context, token: String, nome: String) {
        preferencias(context).edit()
            .putString("token", token)
            .putString("nome", nome)
            .apply()
    }

    fun token(context: Context): String {
        return preferencias(context).getString("token", "") ?: ""
    }

    fun entrou(context: Context): Boolean {
        return token(context) != ""
    }

    fun sair(context: Context) {
        preferencias(context).edit().clear().apply()
    }
}
