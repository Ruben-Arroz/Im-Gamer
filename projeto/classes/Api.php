<?php

class Api
{
    // faz um pedido GET e devolve o json já convertido, ou null se correr mal
    public static function pedir(string $url): ?array
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 20);
        // algumas apis recusam pedidos sem user agent
        curl_setopt($curl, CURLOPT_USERAGENT, 'ImGamer/1.0 (projeto escolar ESAN UA)');

        $resposta = curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($resposta === false || $codigo !== 200) {
            return null;
        }

        return json_decode($resposta, true);
    }

    // vê se um endereço existe sem descarregar o ficheiro todo
    public static function existe(string $url): bool
    {
        $curl = curl_init($url);
        curl_setopt($curl, CURLOPT_NOBODY, true);
        curl_setopt($curl, CURLOPT_TIMEOUT, 10);
        curl_exec($curl);
        $codigo = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        return $codigo === 200;
    }
}
