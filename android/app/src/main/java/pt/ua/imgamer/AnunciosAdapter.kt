package pt.ua.imgamer

import android.app.Activity
import android.content.Intent
import android.view.LayoutInflater
import android.view.View
import android.view.ViewGroup
import android.widget.ImageView
import android.widget.TextView
import androidx.recyclerview.widget.RecyclerView
import org.json.JSONObject

// liga a lista de anúncios que veio do site aos cartões da RecyclerView
class AnunciosAdapter(private val activity: Activity) : RecyclerView.Adapter<AnunciosAdapter.Cartao>() {

    private val anuncios = ArrayList<JSONObject>()

    // guarda as views de um cartão, para não as procurar outra vez de cada vez que o cartão é reutilizado
    class Cartao(view: View) : RecyclerView.ViewHolder(view) {
        val imagem: ImageView = view.findViewById(R.id.imagem)
        val jogo: TextView = view.findViewById(R.id.jogo)
        val detalhe: TextView = view.findViewById(R.id.detalhe)
        val noAr: TextView = view.findViewById(R.id.no_ar)
        val estado: TextView = view.findViewById(R.id.estado)
    }

    override fun onCreateViewHolder(parent: ViewGroup, viewType: Int): Cartao {
        val view = LayoutInflater.from(parent.context).inflate(R.layout.item_anuncio, parent, false)
        return Cartao(view)
    }

    override fun onBindViewHolder(cartao: Cartao, posicao: Int) {
        val anuncio = anuncios[posicao]

        Imagens.mostrar(activity, texto(anuncio, "imagem"), cartao.imagem)
        cartao.jogo.text = anuncio.getString("jogo")
        cartao.detalhe.text = activity.getString(R.string.plataforma_dono, plataformaAnuncio(activity, anuncio), anuncio.getString("dono"))
        cartao.noAr.text = activity.getString(R.string.no_ar_ate, dataAnuncio(anuncio))
        cartao.estado.text = estadoAnuncio(activity, anuncio)

        cartao.itemView.setOnClickListener {
            val intent = Intent(activity, AnuncioActivity::class.java)
            intent.putExtra("id", anuncio.getInt("id"))
            activity.startActivity(intent)
        }
    }

    override fun getItemCount(): Int {
        return anuncios.size
    }

    fun limpar() {
        val quantos = anuncios.size
        anuncios.clear()
        notifyItemRangeRemoved(0, quantos)
    }

    // a página seguinte vai para o fim da lista
    fun juntar(novos: List<JSONObject>) {
        val inicio = anuncios.size
        anuncios.addAll(novos)
        notifyItemRangeInserted(inicio, novos.size)
    }
}
