<?php

require_once __DIR__ . '/../config/credentials.php';

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

    // fecha a ligação e abre uma nova, para quando a ligação pode ter caído
    // (por exemplo na sincronização, que fica muito tempo à espera da internet)
    public static function religar(): PDO
    {
        self::$instancia = null;
        return self::ligar();
    }
}