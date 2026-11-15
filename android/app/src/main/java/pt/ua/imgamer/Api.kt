package pt.ua.imgamer

import android.content.Context
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

// tem de ser chamado numa Thread pq o android não deixa usar a internet na thread principal
object Api {

    // servidor da UA, só funciona com a VPN ligada
    const val ENDERECO = "https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/projeto/api/"
    // para testar com o site a correr no pc (botão Site local)
    // const val ENDERECO = "http://10.0.2.2:8124/projeto/api/"

    // se der erro devolve um json com "erro" igual ao site
    fun pedir(context: Context, ficheiro: String, parametros: Map<String, String> = mapOf(), post: Boolean = false): JSONObject {
        try {
            // fica nome=valor&nome2=valor2 como num formulário
            var dados = ""
            for (parametro in parametros) {
                if (dados != "") {
                    dados += "&"
                }
                dados += URLEncoder.encode(parametro.key, "UTF-8") + "=" + URLEncoder.encode(parametro.value, "UTF-8")
            }

            var endereco = ENDERECO + ficheiro
            if (!post && dados != "") {
                endereco += "?$dados"
            }

            val ligacao = URL(endereco).openConnection() as HttpURLConnection
            ligacao.connectTimeout = 15000
            ligacao.readTimeout = 15000
            // o site sabe quem é pelo token
            ligacao.setRequestProperty("X-Token", Sessao.token(context))

            if (post) {
                ligacao.requestMethod = "POST"
                ligacao.doOutput = true
                ligacao.setRequestProperty("Content-Type", "application/x-www-form-urlencoded")
                ligacao.outputStream.write(dados.toByteArray())
                ligacao.outputStream.close()
            }

            val texto = ligacao.inputStream.bufferedReader().readText()
            ligacao.disconnect()

            return JSONObject(texto)
        } catch (e: Exception) {
            return JSONObject().put("erro", context.getString(R.string.sem_ligacao)).put("sem_ligacao", true)
        }
    }
}
