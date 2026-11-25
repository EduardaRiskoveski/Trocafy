
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

// Verifica se a sessão já está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR PERMISSÃO DE ADMINISTRADOR
// ======================================================

// Carrega as funções auxiliares do projeto
require_once __DIR__ . '/../includes/funcoes.php';

// Impede que usuários comuns acessem o painel
exigirAdmin();

// ======================================================
// 3. CONECTAR AO BANCO DE DADOS
// ======================================================

// Carrega a conexão com o MySQL
require_once __DIR__ . '/../config/conexao.php';


// ======================================================
// 4. BUSCAR INFORMAÇÕES DO ADMINISTRADOR
// ======================================================

// Recupera o nome do administrador pela sessão
$nomeAdmin = $_SESSION['usuario_nome'] ?? 'Administrador';


// ======================================================
// 5. CONTAR OS USUÁRIOS CADASTRADOS
// ======================================================

// Consulta a quantidade de usuários na tabela
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM usuarios
");

// Guarda o resultado
$totalUsuarios = $stmt->fetchColumn();


// ======================================================
// 6. CONTAR OS PRODUTOS CADASTRADOS
// ======================================================

// Consulta a quantidade de produtos na tabela
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM produtos
");

// Guarda o resultado
$totalProdutos = $stmt->fetchColumn();


// ======================================================
// 7. CONTAR AS CATEGORIAS CADASTRADAS
// ======================================================

// Consulta a quantidade de categorias na tabela
$stmt = $pdo->query("
    SELECT COUNT(*)
    FROM categorias
");

// Guarda o resultado
$totalCategorias = $stmt->fetchColumn();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <!-- Define a codificação dos caracteres -->
    <meta charset="UTF-8">

    <!-- Adapta a página para celulares e computadores -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Título exibido na aba do navegador -->
    <title>Painel Administrativo | Trocafy</title>

    <!-- Importa o CSS principal do projeto -->
    <link rel="stylesheet" href="../asset/style.css">


    <!-- ==================================================
        8. ESTILOS ESPECÍFICOS DO PAINEL
    =================================================== -->

    <style>

        /* Configurações gerais da página */
        .admin-pagina {
            background: #faf8ff;
            min-height: 100vh;
            padding-bottom: 50px;
        }

        /* Cabeçalho administrativo */
        .admin-topo {
            background: #ffffff;
            padding: 22px 8%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        /* Logo do painel */
        .admin-logo {
            color: #7041bd;
            font-size: 25px;
            font-weight: bold;
            text-decoration: none;
        }

        /* Menu de navegação */
        .admin-menu {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        /* Links do menu */
        .admin-menu a {
            text-decoration: none;
            color: #292235;
            font-weight: bold;
        }

        /* Efeito ao passar o mouse */
        .admin-menu a:hover {
            color: #7041bd;
        }

        /* Conteúdo principal */
        .admin-container {
            width: 85%;
            max-width: 1200px;
            margin: 45px auto;
        }

        /* Área de boas-vindas */
        .admin-boasvindas {
            margin-bottom: 35px;
        }

        /* Identificação da área administrativa */
        .admin-boasvindas span {
            color: #7041bd;
            font-size: 13px;
            font-weight: bold;
        }

        /* Título de boas-vindas */
        .admin-boasvindas h1 {
            color: #292235;
            margin: 10px 0;
            font-size: 30px;
        }

        /* Texto de apresentação */
        .admin-boasvindas p {
            color: #777;
        }

        /* Cartões com os indicadores */
        .admin-estatisticas {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 22px;
            margin-bottom: 40px;
        }

        /* Cada cartão de indicador */
        .admin-card {
            background: white;
            padding: 25px;
            border-radius: 14px;
            box-shadow: 0 3px 12px rgba(0,0,0,0.04);
            border: 1px solid #eee9f6;
        }

        /* Descrição do indicador */
        .admin-card span {
            color: #777;
            font-size: 14px;
        }

        /* Número do indicador */
        .admin-card h2 {
            font-size: 34px;
            color: #7041bd;
            margin-top: 12px;
        }

        /* Texto complementar do cartão */
        .admin-card p {
            font-size: 13px;
            color: #888;
            margin-top: 5px;
        }

        /* Área de gerenciamento */
        .admin-gerenciamento {
            background: white;
            padding: 30px;
            border-radius: 14px;
            border: 1px solid #eee9f6;
        }

        /* Título do gerenciamento */
        .admin-gerenciamento h2 {
            color: #292235;
            margin-bottom: 10px;
        }

        /* Texto do gerenciamento */
        .admin-gerenciamento > p {
            color: #777;
            margin-bottom: 25px;
        }

        /* Organização dos atalhos */
        .admin-atalhos {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
        }

        /* Cartões de atalhos */
        .admin-atalho {
            background: #faf8ff;
            padding: 22px;
            border-radius: 12px;
            border: 1px solid #eee9f6;
            text-decoration: none;
            transition: 0.2s;
        }

        /* Efeito dos atalhos */
        .admin-atalho:hover {
            border-color: #7041bd;
            transform: translateY(-3px);
        }

        /* Título dos atalhos */
        .admin-atalho h3 {
            color: #7041bd;
            margin-bottom: 8px;
        }

        /* Descrição dos atalhos */
        .admin-atalho p {
            color: #777;
            font-size: 14px;
        }

        /* Rodapé administrativo */
        .admin-rodape {
            text-align: center;
            margin-top: 40px;
            color: #888;
            font-size: 13px;
        }


        /* ==================================================
        9. RESPONSIVIDADE
        =================================================== */

        /* Ajustes para tablets */
        @media (max-width: 900px) {

            .admin-atalhos {
                grid-template-columns: repeat(2, 1fr);
            }

        }

        /* Ajustes para celulares */
        @media (max-width: 768px) {

            .admin-topo {
                flex-direction: column;
                gap: 18px;
            }

            .admin-menu {
                flex-wrap: wrap;
                justify-content: center;
            }

            .admin-container {
                width: 90%;
            }

            .admin-estatisticas {
                grid-template-columns: 1fr;
            }

            .admin-atalhos {
                grid-template-columns: 1fr;
            }

            .admin-boasvindas h1 {
                font-size: 25px;
            }

        }

    </style>

</head>

<body class="admin-pagina">


    <!-- ==================================================
        10. CABEÇALHO DO PAINEL
    =================================================== -->

    <header class="admin-topo">

        <!-- Identidade do painel -->
        <a href="index.php" class="admin-logo">
            Trocafy Admin
        </a>

        <!-- Menu de navegação -->
        <nav class="admin-menu">

            <!-- Retorna ao painel -->
            <a href="index.php">
                Painel
            </a>

            <!-- Volta para a página inicial -->
            <a href="../index.php">
                Voltar ao site
            </a>

            <!-- Encerra a sessão -->
            <a href="../sair.php">
                Sair
            </a>

        </nav>

    </header>


    <!-- ==================================================
        11. CONTEÚDO PRINCIPAL
    =================================================== -->

    <main class="admin-container">


        <!-- Apresentação do administrador -->
        <section class="admin-boasvindas">

            <!-- Identificação da área -->
            <span>ÁREA ADMINISTRATIVA</span>

            <!-- Nome do administrador -->
            <h1>
                Olá, <?= e($nomeAdmin) ?>!
            </h1>

            <!-- Texto de apresentação -->
            <p>
                Bem-vindo ao painel de controle do Trocafy.
                Aqui você pode acompanhar e gerenciar a plataforma.
            </p>

        </section>


        <!-- ==================================================
            12. INDICADORES DO SISTEMA
        =================================================== -->

        <section class="admin-estatisticas">


            <!-- Cartão de usuários -->
            <article class="admin-card">

                <span>Usuários cadastrados</span>

                <h2>
                    <?= (int) $totalUsuarios ?>
                </h2>

                <p>
                    Contas registradas no Trocafy
                </p>

            </article>


            <!-- Cartão de produtos -->
            <article class="admin-card">

                <span>Produtos cadastrados</span>

                <h2>
                    <?= (int) $totalProdutos ?>
                </h2>

                <p>
                    Anúncios registrados na plataforma
                </p>

            </article>


            <!-- Cartão de categorias -->
            <article class="admin-card">

                <span>Categorias cadastradas</span>

                <h2>
                    <?= (int) $totalCategorias ?>
                </h2>

                <p>
                    Categorias disponíveis no sistema
                </p>

            </article>


        </section>


        <!-- ==================================================
            13. ÁREA DE GERENCIAMENTO
        =================================================== -->

        <section class="admin-gerenciamento">

            <!-- Título da área -->
            <h2>Gerenciamento do Trocafy</h2>

            <p>
                Escolha uma área para administrar.
            </p>


            <!-- Cartões de acesso às funcionalidades -->
            <div class="admin-atalhos">


                <!-- GERENCIAR PRODUTOS -->
                <a href="produtos.php" class="admin-atalho">

                    <h3>
                        Gerenciar produtos
                    </h3>

                    <p>
                        Visualize os anúncios cadastrados
                        e exclua produtos quando necessário.
                    </p>

                </a>


                <!-- GERENCIAR USUÁRIOS -->
                <a href="usuarios.php" class="admin-atalho">

                    <h3>
                        Gerenciar usuários
                    </h3>

                    <p>
                        Visualize as contas cadastradas
                        e confira os tipos de acesso dos usuários.
                    </p>

                </a>


                <!-- VISUALIZAR SITE -->
                <a href="../index.php" class="admin-atalho">

                    <h3>
                        Visualizar site
                    </h3>

                    <p>
                        Acesse a página inicial do Trocafy
                        e confira como a plataforma aparece para os usuários.
                    </p>

                </a>


            </div>

        </section>


        <!-- ==================================================
            14. RODAPÉ DO PAINEL
        =================================================== -->

        <footer class="admin-rodape">

            <p>
                Trocafy © <?= date('Y') ?> -
                Painel Administrativo
            </p>

        </footer>


    </main>

</body>
</html>
