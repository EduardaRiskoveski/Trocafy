
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
// 3. VERIFICAR SE É ADMINISTRADOR
// ======================================================

$ehAdmin = $logado &&
    ($_SESSION['usuario_tipo'] ?? '') === 'admin';

?>

<!-- ======================================================
     4. CABEÇALHO PRINCIPAL
======================================================= -->

<header class="cabecalho">

    <div class="cabecalho-container">


        <!-- LOGO DO TROCAFY -->

        <a href="/index.php" class="logo">
            Trocafy
        </a>


        <!-- ==================================================
             5. MENU PRINCIPAL
        =================================================== -->

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


        <!-- ==================================================
             6. BARRA DE PESQUISA
        =================================================== -->

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


        <!-- ==================================================
             7. MENU DO USUÁRIO
        =================================================== -->

        <div class="menu-usuario">

            <?php if ($logado): ?>


                <!-- NOME DO USUÁRIO -->

                <span class="boas-vindas">
                    Olá, <?= e($nomeUsuario) ?>!
                </span>


                <!-- PAINEL ADMINISTRATIVO -->

                <?php if ($ehAdmin): ?>

                    <a href="/admin/index.php" class="btn-menu">
                        Painel Administrativo
                    </a>

                <?php endif; ?>


                <!-- MEUS FAVORITOS -->

                <a href="/favoritos.php" class="btn-menu">
                    ❤️ Favoritos
                </a>


                <!-- PERFIL DO USUÁRIO -->

                <a href="/perfil.php" class="btn-menu">
                    Meu perfil
                </a>


                <!-- SAIR DA CONTA -->

                <a href="/auth/sair.php" class="btn-menu sair">
                    Sair
                </a>


            <?php else: ?>


                <!-- ENTRAR NA CONTA -->

                <a href="/login.php" class="btn-menu">
                    Entrar
                </a>


                <!-- CRIAR UMA NOVA CONTA -->

                <a href="/cadastro.php" class="btn-menu cadastrar">
                    Criar conta
                </a>


            <?php endif; ?>

        </div>

    </div>

</header>
