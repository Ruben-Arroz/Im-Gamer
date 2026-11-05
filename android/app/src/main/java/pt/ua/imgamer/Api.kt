package pt.ua.imgamer

import android.content.Context
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

// pedidos aos endereços da pasta projeto/api do site
// tem de ser chamado dentro de uma Thread, o android não deixa usar a internet na thread principal
object Api {

    // no emulador o 10.0.2.2 é o pc, com o site a correr no php -S (porta 8124)
    // para usar o servidor da UA (com a VPN) troca pela linha de baixo
    const val ENDERECO = "http://10.0.2.2:8124/projeto/api/"
    // const val ENDERECO = "https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/projeto/api/"

    // devolve sempre um JSONObject. se correr mal vem com o campo "erro", como as respostas do site
    fun pedir(context: Context, ficheiro: String, parametros: Map<String, String> = mapOf(), post: Boolean = false): JSONObject {
        try {
            // os parâmetros vão como num formulário: nome=valor&nome2=valor2
            val dados = parametros.map { URLEncoder.encode(it.key, "UTF-8") + "=" + URLEncoder.encode(it.value, "UTF-8") }
                .joinToString("&")

            var endereco = ENDERECO + ficheiro
            if (!post && dados != "") {
                endereco += "?$dados"
            }

            val ligacao = URL(endereco).openConnection() as HttpURLConnection
            ligacao.connectTimeout = 15000
            ligacao.readTimeout = 15000
            // é com o token que o site sabe quem está a usar a app
            ligacao.setRequestProperty("X-Token", Sessao.token(context))

            if (post) {
                ligacao.requestMethod = "POST"
                ligacao.doOutput = true
                ligacao.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
                ligacao.outputStream.use { it.write(dados.toByteArray()) }
            }

            val texto = ligacao.inputStream.bufferedReader().use { it.readText() }
            ligacao.disconnect()

            return JSONObject(texto)
        } catch (e: Exception) {
            return JSONObject().put("erro", "Não foi possível ligar ao servidor. Confirma a internet e a VPN.")
        }
    }
}
