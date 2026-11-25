
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

// Carrega as funções auxiliares
require_once __DIR__ . '/../includes/funcoes.php';

// Impede que usuários comuns acessem esta página
exigirAdmin();


// ======================================================
// 3. CONECTAR AO BANCO DE DADOS
// ======================================================

// Carrega a conexão com o MySQL
require_once __DIR__ . '/../config/conexao.php';


// ======================================================
// 4. BUSCAR OS USUÁRIOS CADASTRADOS
// ======================================================

// Busca os dados dos usuários
// A senha não é selecionada, pois não precisamos exibi-la.
$sql = "
    SELECT
        id,
        nome,
        email,
        tipo,
        criado_em
    FROM usuarios
    ORDER BY id DESC
";

// Executa a consulta
$stmt = $pdo->query($sql);

// Armazena todos os usuários encontrados
$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <!-- Define a codificação dos caracteres -->
    <meta charset="UTF-8">

    <!-- Adapta a página para celulares e computadores -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Título da página -->
    <title>Gerenciar usuários | Trocafy</title>

    <!-- Importa o CSS principal -->
    <link rel="stylesheet" href="../asset/style.css">

    <!-- Estilos específicos da página administrativa -->
    <style>

        /* Área principal */
        .admin-container {
            width: 90%;
            max-width: 1200px;
            margin: 40px auto;
        }

        /* Tabela de usuários */
        .admin-tabela {
            width: 100%;
            border-collapse: collapse;
            background: white;
            margin-top: 25px;
        }

        /* Células da tabela */
        .admin-tabela th,
        .admin-tabela td {
            padding: 15px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        /* Cabeçalho da tabela */
        .admin-tabela th {
            background: #eee8ff;
            color: #292235;
        }

        /* Permite rolagem em telas pequenas */
        .tabela-container {
            overflow-x: auto;
        }

        /* Link para voltar ao painel */
        .voltar-admin {
            display: inline-block;
            margin-top: 15px;
            color: #7041bd;
            text-decoration: none;
            font-weight: bold;
        }

        /* Identificação do administrador */
        .tipo-admin {
            background: #eee5ff;
            color: #7041bd;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        /* Identificação de usuário comum */
        .tipo-usuario {
            background: #eeeeee;
            color: #555;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
        }

        /* Mensagem quando não existem usuários */
        .sem-registros {
            padding: 30px;
            background: white;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
        }

    </style>

</head>

<body>

    <!-- ==================================================
         5. CABEÇALHO ADMINISTRATIVO
    =================================================== -->

    <header class="cabecalho">

        <div class="cabecalho-container">

            <!-- Logo do painel -->
            <a href="index.php" class="logo">
                Trocafy Admin
            </a>

            <!-- Links de navegação -->
            <nav class="menu">

                <a href="index.php">Painel</a>

                <a href="../index.php">Voltar ao site</a>

                <a href="../sair.php">Sair</a>

            </nav>

        </div>

    </header>


    <!-- ==================================================
         6. CONTEÚDO PRINCIPAL
    =================================================== -->

    <main class="admin-container">

        <!-- Título da página -->
        <h1>Gerenciar usuários</h1>

        <p>
            Aqui você pode visualizar as contas cadastradas
            no Trocafy.
        </p>

        <!-- Link para retornar ao painel -->
        <a href="index.php" class="voltar-admin">
            ← Voltar ao painel administrativo
        </a>


        <!-- ==================================================
             7. VERIFICAR SE EXISTEM USUÁRIOS
        =================================================== -->

        <?php if (count($usuarios) > 0): ?>

            <!-- Área que permite rolagem da tabela -->
            <div class="tabela-container">

                <!-- Tabela de usuários -->
                <table class="admin-tabela">

                    <!-- Cabeçalho da tabela -->
                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Nome</th>
                            <th>E-mail</th>
                            <th>Tipo de conta</th>
                            <th>Data de cadastro</th>
                        </tr>

                    </thead>


                    <!-- Dados dos usuários -->
                    <tbody>

                        <!-- Percorre todos os usuários encontrados -->
                        <?php foreach ($usuarios as $usuario): ?>

                            <tr>

                                <!-- Identificador do usuário -->
                                <td>
                                    <?= (int) $usuario['id'] ?>
                                </td>

                                <!-- Nome do usuário -->
                                <td>
                                    <?= e($usuario['nome']) ?>
                                </td>

                                <!-- E-mail do usuário -->
                                <td>
                                    <?= e($usuario['email']) ?>
                                </td>

                                <!-- Tipo de conta -->
                                <td>

                                    <?php if ($usuario['tipo'] === 'admin'): ?>

                                        <span class="tipo-admin">
                                            Administrador
                                        </span>

                                    <?php else: ?>

                                        <span class="tipo-usuario">
                                            Usuário
                                        </span>

                                    <?php endif; ?>

                                </td>

                                <!-- Data em que a conta foi criada -->
                                <td>

                                    <?php if (!empty($usuario['criado_em'])): ?>

                                        <?= date(
                                            'd/m/Y',
                                            strtotime($usuario['criado_em'])
                                        ) ?>

                                    <?php else: ?>

                                        Não informada

                                    <?php endif; ?>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <!-- Mensagem quando não existem usuários -->
            <div class="sem-registros">

                <h2>Nenhum usuário cadastrado</h2>

                <p>
                    As contas criadas no Trocafy aparecerão aqui.
                </p>

            </div>

        <?php endif; ?>

    </main>

</body>
</html>
