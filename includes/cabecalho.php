
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR O LOGIN DO USUÁRIO
// ======================================================

$logado = isset($_SESSION['usuario_id']);

$nomeUsuario = $_SESSION['usuario_nome'] ?? '';


// ======================================================
// 3. CONTAR MENSAGENS NÃO LIDAS
// ======================================================

$totalMensagensNaoLidas = 0;

if ($logado && isset($pdo)) {

    $sql = "SELECT COUNT(*)
            FROM mensagens
            WHERE destinatario_id = ?
            AND lida = 0";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([(int) $_SESSION['usuario_id']]);

    $totalMensagensNaoLidas = (int) $stmt->fetchColumn();
}


// ======================================================
// 4. VERIFICAR SE É ADMINISTRADOR
// ======================================================

$ehAdmin = $logado &&
    ($_SESSION['usuario_tipo'] ?? '') === 'admin';

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="stylesheet" href="/asset/style.css">

</head>

<body>

<!-- ======================================================
     5. CABEÇALHO PRINCIPAL
======================================================= -->

<header class="cabecalho">

    <div class="cabecalho-container">

        <!-- LOGO -->

        <a href="/index.php" class="logo">
            Trocafy
        </a>


        <!-- MENU PRINCIPAL -->

        <nav class="menu">

            <a href="/index.php">
                Início
            </a>

            <a href="/index.php#produtos">
                Produtos
            </a>

            <a href="/buscar.php">
                Buscar
            </a>

        </nav>


        <!-- BARRA DE PESQUISA -->

        <form
            action="/buscar.php"
            method="GET"
            class="busca-cabecalho"
        >

            <input
                type="search"
                name="q"
                placeholder="Buscar produtos..."
                aria-label="Buscar produtos"
            >

            <button type="submit">
                🔎
            </button>

        </form>


        <!-- MENU DO USUÁRIO -->

        <div class="menu-usuario">

            <?php if ($logado): ?>

                <span class="boas-vindas">
                    Olá, <?= e($nomeUsuario) ?>!
                </span>


                <?php if ($ehAdmin): ?>

                    <a href="/admin/index.php" class="btn-menu">
                        Painel Administrativo
                    </a>

                <?php endif; ?>


                <!-- FAVORITOS -->

                <a href="/favoritos.php" class="btn-menu">
                    ❤️ Favoritos
                </a>


                <!-- CONVERSAS -->

                <a href="/minhas_conversas.php" class="btn-menu">

                    💬 Minhas conversas

                    <?php if ($totalMensagensNaoLidas > 0): ?>

                        <span class="contador-mensagens">
                            <?= $totalMensagensNaoLidas ?>
                        </span>

                    <?php endif; ?>

                </a>


                <!-- MINHAS COMPRAS -->

                <a href="/historico_compras.php" class="btn-menu">
                    🛍️ Minhas Compras
                </a>


                <!-- PERFIL -->

                <a href="/perfil.php" class="btn-menu">
                    Meu perfil
                </a>


                <!-- SAIR -->

                <a href="/auth/sair.php" class="btn-menu sair">
                    Sair
                </a>


            <?php else: ?>

                <a href="/login.php" class="btn-menu">
                    Entrar
                </a>

                <a href="/cadastro.php" class="btn-menu cadastrar">
                    Criar conta
                </a>

            <?php endif; ?>

        </div>

    </div>

</header>
</body>
</html>
