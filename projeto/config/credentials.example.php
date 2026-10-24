<?php
define('DB_HOST', 'localhost');
define('DB_NAME', 'nome_da_bd');
define('DB_USER', 'utilizador');
define('DB_PASS', 'password');

// chaves das apis (rawg.io/apidocs, steamcommunity.com/dev/apikey, xbl.io)
define('RAWG_API_KEY', 'a_tua_chave');
define('STEAM_API_KEY', 'a_tua_chave');
define('OPENXBL_API_KEY', 'a_tua_chave');

// chave para cifrar os tokens das contas epic (64 letras e números ao calhas)
// gera-se com: php -r "echo bin2hex(random_bytes(32));"
define('CHAVE_TOKENS', 'uma_chave_grande');
