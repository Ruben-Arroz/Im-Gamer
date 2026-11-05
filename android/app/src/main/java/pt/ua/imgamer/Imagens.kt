package pt.ua.imgamer

import android.app.Activity
import android.graphics.Bitmap
import android.graphics.BitmapFactory
import android.view.View
import android.widget.ImageView
import java.net.URL

// carrega as imagens dos jogos e das fotos de perfil a partir do endereço
// as que já vieram ficam guardadas, para não as pedir outra vez ao voltar a um ecrã
object Imagens {

    private val guardadas = HashMap<String, Bitmap>()

    fun mostrar(activity: Activity, endereco: String?, imagem: ImageView) {
        if (endereco == null) {
            imagem.visibility = View.GONE
            return
        }

        imagem.visibility = View.VISIBLE
        val guardada = guardadas[endereco]
        if (guardada != null) {
            imagem.setImageBitmap(guardada)
            return
        }

        Thread {
            try {
                val bitmap = BitmapFactory.decodeStream(URL(endereco).openStream())
                if (bitmap != null) {
                    activity.runOnUiThread {
                        guardadas[endereco] = bitmap
                        imagem.setImageBitmap(bitmap)
                    }
                }
            } catch (e: Exception) {
                // se a imagem não vier fica o espaço vazio
            }
        }.start()
    }
}
