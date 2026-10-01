<?php

class Database
{
    private static ?PDO $instancia = null;

    public static function ligar(): PDO
    {
        if (self::$instancia === null) {
            $host = 'mysql-hosting.ua.pt';
            $nomeBD = 'esan-dsg17';
            $utilizador = 'esan-dsg17-web';
            $password = ''; // preencher com a password da BD, não enviar este ficheiro para o repositório com a password preenchida

            $dsn = "mysql:host={$host};dbname={$nomeBD};charset=utf8mb4";

            try {
                self::$instancia = new PDO($dsn, $utilizador, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $erro) {
                die('Erro de ligação à base de dados: ' . $erro->getMessage());
            }
        }

        return self::$instancia;
    }
}
