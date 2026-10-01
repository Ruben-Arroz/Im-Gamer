<?php

require_once __DIR__ . '/credentials.php';

class Database
{
    private static ?PDO $instancia = null;

    public static function ligar(): PDO
    {
        if (self::$instancia === null) {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';

            try {
                self::$instancia = new PDO($dsn, DB_USER, DB_PASS, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                ]);
            } catch (PDOException $erro) {
                error_log($erro->getMessage());
                die('Erro de ligação à base de dados.');
            }
        }

        return self::$instancia;
    }
}