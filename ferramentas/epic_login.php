<?php
// faz o login na conta Epic uma vez e guarda o token em ferramentas/epic_token.json
// corre-se na linha de comandos: php ferramentas/epic_login.php
// o ficheiro do token não vai para o git nem para o servidor

if (php_sapi_name() !== 'cli') {
    exit('Este script só corre na linha de comandos.');
}

// dados públicos do launcher da Epic (são os mesmos que as ferramentas não oficiais usam)
$clienteId = '34a02cf8f4414e29b15921876da36f9a';
$clienteSegredo = 'daafbccc737745039dffe53d94fc76cf';

$linkLogin = 'https://www.epicgames.com/id/login?redirectUrl='
    . urlencode('https://www.epicgames.com/id/api/redirect?clientId=' . $clienteId . '&responseType=code');

echo "1. Abre este link no browser, onde já tens a conta Epic com sessão iniciada:\n\n";
echo $linkLogin . "\n\n";
echo "2. Vai aparecer uma página com um texto parecido com {\"authorizationCode\":\"abc123...\"}.\n";
echo "3. Copia só o código (sem aspas) e cola aqui.\n\n";
echo 'Código: ';

$codigo = trim(fgets(STDIN));

$curl = curl_init('https://account-public-service-prod03.ol.epicgames.com/account/api/oauth/token');
curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
curl_setopt($curl, CURLOPT_TIMEOUT, 30);
curl_setopt($curl, CURLOPT_USERPWD, $clienteId . ':' . $clienteSegredo);
curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query([
    'grant_type' => 'authorization_code',
    'code' => $codigo,
    'token_type' => 'eg1',
]));

$resposta = json_decode(curl_exec($curl), true);

if (!isset($resposta['refresh_token'])) {
    exit("\nNão deu. A Epic respondeu: " . ($resposta['errorMessage'] ?? 'sem resposta') . "\nO código só vale uns minutos e só uma vez, tenta outra vez.\n");
}

file_put_contents(__DIR__ . '/epic_token.json', json_encode([
    'conta_id' => $resposta['account_id'],
    'nome' => $resposta['displayName'] ?? null,
    'refresh_token' => $resposta['refresh_token'],
    'expira' => $resposta['refresh_expires_at'] ?? null,
]));

echo "\nFeito. Conta Epic: " . ($resposta['displayName'] ?? $resposta['account_id']) . "\n";
