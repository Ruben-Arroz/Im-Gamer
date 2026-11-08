<?php
require_once __DIR__ . '/../includes/api.php';

soPost();

TokensApi::apagar($_SERVER['HTTP_X_TOKEN'] ?? '');

responder(['ok' => true]);
