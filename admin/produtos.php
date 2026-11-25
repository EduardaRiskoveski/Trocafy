
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR PERMISSÃO DE ADMINISTRADOR
// ======================================================

require_once __DIR__ . '/../includes/funcoes.php';

exigirAdmin();


// ======================================================
// 3. GERAR TOKEN DE SEGURANÇA
// ======================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// ======================================================
// 4. CONECTAR AO BANCO DE DADOS
// ======================================================

require_once __DIR__ . '/../config/conexao.php';


// ======================================================
// 5. BUSCAR OS PRODUTOS
// ======================================================

$sql = "
    SELECT
        p.id,
        p.nome,
        p.preco,
        p.descricao,
        p.imagem,
        p.estado,
        p.status,
        p.criado_em,
        u.nome AS vendedor,
        c.nome AS categoria

    FROM produtos p

    INNER JOIN usuarios u
        ON p.usuario_id = u.id

    LEFT JOIN categorias c
        ON p.categoria_id = c.id

    ORDER BY p.criado_em DESC
";

$stmt = $pdo->query($sql);

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Gerenciar produtos | Trocafy</title>

    <link rel="stylesheet" href="../asset/style.css">

    <style>

        /* CONTEÚDO PRINCIPAL */
        .admin-container {
            width: 90%;
            max-width: 1300px;
            margin: 40px auto;
        }

        /* TABELA */
        .tabela-container {
            overflow-x: auto;
            margin-top: 25px;
            border-radius: 10px;
        }

        .admin-tabela {
            width: 100%;
            min-width: 950px;
            border-collapse: collapse;
            background: white;
        }

        .admin-tabela th,
        .admin-tabela td {
            padding: 14px;
            border-bottom: 1px solid #eee;
            text-align: left;
        }

        .admin-tabela th {
            background: #eee8ff;
            color: #292235;
        }

        /* IMAGENS DOS PRODUTOS */
        .imagem-produto {
            width: 75px;
            height: 75px;
            object-fit: cover;
            border-radius: 8px;
            border: 1px solid #eee;
            display: block;
        }

        .sem-imagem {
            width: 75px;
            height: 75px;
            border-radius: 8px;
            background: #f0ebfa;
            color: #7041bd;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            font-size: 11px;
        }

        /* BOTÃO VOLTAR */
        .voltar-admin {
            display: inline-block;
            margin-top: 15px;
            color: #7041bd;
            text-decoration: none;
            font-weight: bold;
        }

        /* AÇÕES */
        .acoes-produto {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: 8px;
        }

        .btn-editar {
            display: inline-block;
            background: #7041bd;
            color: white;
            padding: 9px 14px;
            border-radius: 6px;
            text-decoration: none;
        }

        .btn-editar:hover {
            background: #58319b;
        }

        .btn-excluir {
            background: #dc3545;
            color: white;
            border: none;
            padding: 9px 14px;
            border-radius: 6px;
            cursor: pointer;
        }

        .btn-excluir:hover {
            background: #b02a37;
        }

        .form-excluir {
            margin: 0;
        }

        /* MENSAGENS */
        .mensagem {
            padding: 13px;
            margin-top: 20px;
            border-radius: 8px;
        }

        .mensagem-sucesso {
            background: #d1e7dd;
            color: #0f5132;
        }

        .mensagem-erro {
            background: #f8d7da;
            color: #842029;
        }

        /* LISTA VAZIA */
        .sem-registros {
            padding: 30px;
            background: white;
            border-radius: 12px;
            margin-top: 20px;
            text-align: center;
        }

        @media (max-width: 768px) {
            .admin-container {
                width: 95%;
            }
        }

    </style>

</head>

<body>

    <!-- CABEÇALHO -->
    <header class="cabecalho">

        <div class="cabecalho-container">

            <a href="index.php" class="logo">
                Trocafy Admin
            </a>

            <nav class="menu">

                <a href="index.php">Painel</a>

                <a href="../index.php">Voltar ao site</a>

                <a href="../sair.php">Sair</a>

            </nav>

        </div>

    </header>


    <!-- CONTEÚDO PRINCIPAL -->
    <main class="admin-container">

        <h1>Gerenciar produtos</h1>

        <p>
            Aqui você pode visualizar, editar e excluir
            os produtos cadastrados pelos usuários.
        </p>

        <a href="index.php" class="voltar-admin">
            ← Voltar ao painel administrativo
        </a>


        <!-- MENSAGENS -->
        <?php if (($_GET['atualizado'] ?? '') === 'sucesso'): ?>

            <div class="mensagem mensagem-sucesso">
                Produto atualizado com sucesso!
            </div>

        <?php elseif (($_GET['excluido'] ?? '') === 'sucesso'): ?>

            <div class="mensagem mensagem-sucesso">
                Produto excluído com sucesso!
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'exclusao'): ?>

            <div class="mensagem mensagem-erro">
                Não foi possível excluir o produto.
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'nao_encontrado'): ?>

            <div class="mensagem mensagem-erro">
                Produto não encontrado.
            </div>

        <?php endif; ?>


        <!-- VERIFICAR SE EXISTEM PRODUTOS -->
        <?php if (count($produtos) > 0): ?>

            <div class="tabela-container">

                <table class="admin-tabela">

                    <thead>

                        <tr>
                            <th>ID</th>
                            <th>Foto</th>
                            <th>Produto</th>
                            <th>Preço</th>
                            <th>Vendedor</th>
                            <th>Categoria</th>
                            <th>Estado</th>
                            <th>Status</th>
                            <th>Ações</th>
                        </tr>

                    </thead>

                    <tbody>

                        <?php foreach ($produtos as $produto): ?>

                            <?php

                            // Define o caminho da imagem
                            $imagem = $produto['imagem'] ?? '';

                            $imagemSrc = null;

                            // Aceita imagens externas somente por HTTP ou HTTPS
                            if (
                                filter_var($imagem, FILTER_VALIDATE_URL) &&
                                in_array(
                                    strtolower(parse_url($imagem, PHP_URL_SCHEME) ?? ''),
                                    ['http', 'https'],
                                    true
                                )
                            ) {
                                $imagemSrc = $imagem;

                            // Caminho das imagens enviadas pelo formulário
                            } elseif (
                                str_starts_with($imagem, 'uploads/produtos/')
                            ) {
                                $imagemSrc = '../' . $imagem;
                            }

                            ?>

                            <tr>

                                <!-- ID -->
                                <td>
                                    <?= (int) $produto['id'] ?>
                                </td>

                                <!-- FOTO -->
                                <td>

                                    <?php if ($imagemSrc !== null): ?>

                                        <img
                                            src="<?= e($imagemSrc) ?>"
                                            alt="<?= e($produto['nome']) ?>"
                                            class="imagem-produto"
                                        >

                                    <?php else: ?>

                                        <div class="sem-imagem">
                                            Sem foto
                                        </div>

                                    <?php endif; ?>

                                </td>

                                <!-- NOME -->
                                <td>
                                    <?= e($produto['nome']) ?>
                                </td>

                                <!-- PREÇO -->
                                <td>
                                    R$
                                    <?= number_format(
                                        (float) $produto['preco'],
                                        2,
                                        ',',
                                        '.'
                                    ) ?>
                                </td>

                                <!-- VENDEDOR -->
                                <td>
                                    <?= e($produto['vendedor']) ?>
                                </td>

                                <!-- CATEGORIA -->
                                <td>
                                    <?= e($produto['categoria'] ?? 'Sem categoria') ?>
                                </td>

                                <!-- ESTADO -->
                                <td>
                                    <?= e($produto['estado'] ?? 'Não informado') ?>
                                </td>

                                <!-- STATUS -->
                                <td>
                                    <?= e($produto['status'] ?? 'Não informado') ?>
                                </td>

                                <!-- AÇÕES -->
                                <td>

                                    <div class="acoes-produto">

                                        <!-- EDITAR -->
                                        <a
                                            href="editar.php?id=<?= (int) $produto['id'] ?>"
                                            class="btn-editar"
                                        >
                                            Editar
                                        </a>

                                        <!-- EXCLUIR -->
                                        <form
                                            action="excluir.php"
                                            method="POST"
                                            class="form-excluir"
                                            onsubmit="return confirm('Tem certeza que deseja excluir este produto?')"
                                        >

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $produto['id'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($csrfToken) ?>"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-excluir"
                                            >
                                                Excluir
                                            </button>

                                        </form>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                    </tbody>

                </table>

            </div>

        <?php else: ?>

            <!-- NENHUM PRODUTO -->
            <div class="sem-registros">

                <h2>Nenhum produto cadastrado</h2>

                <p>
                    Os produtos publicados pelos usuários
                    aparecerão aqui.
                </p>

            </div>

        <?php endif; ?>

    </main>

</body>
</html>
