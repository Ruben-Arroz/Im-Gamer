<?php
// login da app: recebe o email e a password e devolve um token
require_once __DIR__ . '/../includes/api.php';

soPost();

$email = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
    erro('Preenche o email e a password.');
}

$utilizador = Auth::credenciais($email, $password);
if (!$utilizador) {
    erro('Email ou password incorretos.');
}

responder([
    'token' => TokensApi::criar($utilizador['id']),
    'id' => (int) $utilizador['id'],
    'nome' => $utilizador['nome_utilizador'],
]);
