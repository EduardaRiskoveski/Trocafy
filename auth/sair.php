
<?php

// Inicia a sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Limpa os dados armazenados na sessão
$_SESSION = [];

// Remove o cookie da sessão do navegador
if (ini_get('session.use_cookies')) {

    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Destrói a sessão no servidor
session_destroy();

// Impede que o navegador reutilize páginas armazenadas em cache
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// Redireciona com status HTTP 303
header('Location: /index.php', true, 303);
exit;
