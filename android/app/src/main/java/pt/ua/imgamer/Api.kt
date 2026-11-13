package pt.ua.imgamer

import android.content.Context
import org.json.JSONObject
import java.net.HttpURLConnection
import java.net.URL
import java.net.URLEncoder

// tem de ser chamado numa Thread, o android não deixa usar a internet na thread principal
object Api {

    // no emulador o 10.0.2.2 é o pc, com o site a correr no php -S (porta 8124)
    // para usar o servidor da UA (com a VPN) troca pela linha de baixo
    const val ENDERECO = "http://10.0.2.2:8124/projeto/api/"
    // const val ENDERECO = "https://esan-tesp-ds-paw.web.ua.pt/tesp-ds-g17/projeto/api/"

    // se correr mal devolve um json com o campo "erro", como o site
    fun pedir(context: Context, ficheiro: String, parametros: Map<String, String> = mapOf(), post: Boolean = false): JSONObject {
        try {
            // os parâmetros vão como num formulário: nome=valor&nome2=valor2
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
            // é com o token que o site sabe quem está a usar a app
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
