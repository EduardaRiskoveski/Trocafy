
<?php

// 1. INICIAR A SESSÃO
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 2. VERIFICAR ADMINISTRADOR
require_once __DIR__ . '/../includes/funcoes.php';
exigirAdmin();

// 3. CONECTAR AO BANCO
require_once __DIR__ . '/../config/conexao.php';

// ID do administrador conectado
$adminAtual = (int) $_SESSION['usuario_id'];

// 4. GERAR TOKEN DE SEGURANÇA
if (empty($_SESSION['csrf_usuarios'])) {
    $_SESSION['csrf_usuarios'] = bin2hex(random_bytes(32));
}

// 5. PROCESSAR PROMOÇÃO
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';
    $usuarioId = filter_input(
        INPUT_POST,
        'usuario_id',
        FILTER_VALIDATE_INT
    );

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_usuarios'], $token)
    ) {
        header('Location: usuarios.php?erro=token');
        exit;
    }

    // Impede alterar a própria conta
    if (!$usuarioId || $usuarioId === $adminAtual) {
        header('Location: usuarios.php?erro=usuario');
        exit;
    }

    // Verifica se o usuário existe
    $stmt = $pdo->prepare("
        SELECT id, tipo
        FROM usuarios
        WHERE id = ?
    ");

    $stmt->execute([$usuarioId]);
    $usuario = $stmt->fetch();

    if (!$usuario) {
        header('Location: usuarios.php?erro=nao_encontrado');
        exit;
    }

    // Promove o usuário a administrador
    if ($usuario['tipo'] !== 'admin') {

        $stmt = $pdo->prepare("
            UPDATE usuarios
            SET tipo = 'admin'
            WHERE id = ?
        ");

        $stmt->execute([$usuarioId]);
    }

    header('Location: usuarios.php?sucesso=1');
    exit;
}

// 6. BUSCAR USUÁRIOS
$stmt = $pdo->query("
    SELECT id, nome, email, tipo, criado_em
    FROM usuarios
    ORDER BY id DESC
");

$usuarios = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Gerenciar usuários | Trocafy</title>
<link rel="stylesheet" href="../asset/style.css">



</head>

<body>
<header class="cabecalho">

    <div class="cabecalho-container">

        <a href="index.php" class="logo">
            Trocafy Admin
        </a>

        <nav class="menu">
            <a href="index.php">Painel</a>
            <a href="../index.php">Voltar ao site</a>
            <a href="../auth/sair.php">Sair</a>
        </nav>

    </div>

</header>

<main class="admin-container">

    <h1>Gerenciar usuários</h1>

    <p>
        Visualize os usuários cadastrados
        e gerencie suas permissões.
    </p>

    <?php if (isset($_GET['sucesso'])): ?>

        <div class="mensagem sucesso">
            Usuário promovido a administrador com sucesso!
        </div>

    <?php endif; ?>

    <?php if (isset($_GET['erro'])): ?>

        <div class="mensagem erro">
            Não foi possível realizar a operação.
            Verifique os dados e tente novamente.
        </div>

    <?php endif; ?>

    <div class="tabela-container">

        <table class="admin-tabela">

            <thead>

                <tr>
                    <th>ID</th>
                    <th>Nome</th>
                    <th>E-mail</th>
                    <th>Tipo</th>
                    <th>Cadastro</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

                <?php foreach ($usuarios as $usuario): ?>

                    <tr>

                        <td>
                            <?= (int) $usuario['id'] ?>
                        </td>

                        <td>
                            <?= e($usuario['nome']) ?>
                        </td>

                        <td>
                            <?= e($usuario['email']) ?>
                        </td>

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

                        <td>
                            <?= !empty($usuario['criado_em'])
                                ? date('d/m/Y', strtotime($usuario['criado_em']))
                                : 'Não informada' ?>
                        </td>

                        <td>

                            <?php if ($usuario['tipo'] !== 'admin'): ?>

                                <form
                                    method="POST"
                                    action="usuarios.php"
                                    onsubmit="return confirm('Deseja realmente tornar este usuário administrador?');"
                                >

                                    <input
                                        type="hidden"
                                        name="usuario_id"
                                        value="<?= (int) $usuario['id'] ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_usuarios']) ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-promover"
                                    >
                                        Tornar administrador
                                    </button>

                                </form>

                            <?php else: ?>

                                <span>Já é administrador</span>

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endforeach; ?>

            </tbody>

        </table>

    </div>

</main>

</body>
</html>
