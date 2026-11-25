
<?php

// Função para proteger textos exibidos no HTML
function e(?string $valor): string {
    return htmlspecialchars($valor ?? '', ENT_QUOTES, 'UTF-8');
}

// Função para verificar se o usuário é administrador
function exigirAdmin(): void {

    // Inicia a sessão caso ainda não esteja iniciada
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }

    // Verifica se o usuário está logado e possui permissão de administrador
    if (
        !isset($_SESSION['usuario_id']) ||
        ($_SESSION['usuario_tipo'] ?? '') !== 'admin'
    ) {
        header('Location: /login.php');
        exit;
    }
}
