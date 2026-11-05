package pt.ua.imgamer

import android.app.Notification
import android.app.NotificationChannel
import android.app.NotificationManager
import android.app.PendingIntent
import android.content.Context
import android.content.Intent

// notificações dos convites e dos avisos do LFG
// o servidor da UA não tem internet para mandar notificações push, por isso é a app que pergunta ao site
// de tempos a tempos (MainActivity) se há coisas novas
object Avisos {

    private const val CANAL = "avisos"

    // desde o android 8 todas as notificações têm de pertencer a um canal (aparece nas definições da app)
    fun criarCanal(context: Context) {
        val canal = NotificationChannel(CANAL, "Convites e avisos", NotificationManager.IMPORTANCE_DEFAULT)
        canal.description = "Convites para jogar e avisos das equipas de LFG"
        context.getSystemService(NotificationManager::class.java).createNotificationChannel(canal)
    }

    private fun preferencias(context: Context) = context.getSharedPreferences("avisos", Context.MODE_PRIVATE)

    // ao entrar na app esquece os que já tinha visto
    fun reiniciar(context: Context) {
        preferencias(context).edit().clear().apply()
    }

    // corre dentro de uma Thread. mostra uma notificação por cada convite ou aviso que ainda não tinha visto
    // devolve quantos convites estão por responder (para o número na aba), ou -1 se não conseguiu ligar
    fun verificar(context: Context): Int {
        val resposta = Api.pedir(context, "avisos.php")
        if (resposta.has("erro")) {
            return -1
        }

        val prefs = preferencias(context)
        val vistos = (prefs.getStringSet("vistos", setOf()) ?: setOf()).toMutableSet()
        // na primeira vez depois de entrar só guarda o que já existe, para não aparecerem notificações antigas
        val primeiraVez = !prefs.getBoolean("iniciado", false)

        val convites = resposta.getJSONArray("convites")
        for (i in 0 until convites.length()) {
            val convite = convites.getJSONObject(i)
            val chave = "convite" + convite.getInt("id")

            if (chave !in vistos) {
                vistos.add(chave)
                if (!primeiraVez) {
                    val jogo = texto(convite, "jogo")
                    val mensagem = if (jogo != null) "Convidou-te para jogar $jogo." else "Convidou-te para jogar."
                    mostrar(context, chave.hashCode(), convite.getString("de"), mensagem)
                }
            }
        }

        val avisos = resposta.getJSONArray("avisos")
        for (i in 0 until avisos.length()) {
            val aviso = avisos.getJSONObject(i)
            val chave = "aviso" + aviso.getInt("id")

            if (chave !in vistos) {
                vistos.add(chave)
                if (!primeiraVez) {
                    mostrar(context, chave.hashCode(), "I'm Gamer", aviso.getString("texto"))
                }
            }
        }

        prefs.edit().putStringSet("vistos", vistos).putBoolean("iniciado", true).apply()
        return convites.length()
    }

    private fun mostrar(context: Context, id: Int, titulo: String, mensagem: String) {
        // ao carregar na notificação abre a aba dos convites
        val intent = Intent(context, MainActivity::class.java)
        intent.putExtra("aba", "convites")
        intent.flags = Intent.FLAG_ACTIVITY_SINGLE_TOP or Intent.FLAG_ACTIVITY_CLEAR_TOP
        val abrir = PendingIntent.getActivity(context, id, intent, PendingIntent.FLAG_IMMUTABLE or PendingIntent.FLAG_UPDATE_CURRENT)

        val notificacao = Notification.Builder(context, CANAL)
            .setSmallIcon(R.drawable.ic_notificacao)
            .setContentTitle(titulo)
            .setContentText(mensagem)
            .setContentIntent(abrir)
            .setAutoCancel(true)
            .build()

        // se a pessoa não deu autorização para notificações o android simplesmente não as mostra
        context.getSystemService(NotificationManager::class.java).notify(id, notificacao)
    }
}
