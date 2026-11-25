
<?php
require_once __DIR__ . '/includes/funcoes.php';

$email = "";
$senha = "";
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login | TROCAFY</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>

<?php include_once __DIR__ . '/includes/cabecalho.php'; ?>

<main class="login">
    <section>
        <small>BEM-VINDO À TROCAFY</small>
        <h1>
            ENCONTRE O QUE
            <br>VOCÊ PROCURA <br>
            <em>NA TROCAFY</em>
        </h1>
    </section>

    <form action="auth/autenticar.php" method="POST">

        <h2>Acesse sua conta</h2>

        <?php if (isset($_GET["erro"])): ?>
            <p class="notice">E-mail ou senha inválidos</p>
        <?php endif; ?>

        <?php if (isset($_GET['cadastro']) && $_GET['cadastro'] === 'sucesso'): ?>
            <p class="notice" style="background:#e8f8ed;color:#24743b;">
                Cadastro realizado! Agora faça seu login.
            </p>
        <?php endif; ?>

        <label for="email">E-mail:</label>
        <input type="email"
            id="email"
            name="email"
            value="<?= e($email) ?>"
            maxlength="150"
            required>

        <br><br>

        <label for="senha">Senha:</label>
        <input type="password"
            id="senha"
            name="senha"
            minlength="8"
            required>

        <br><br>

        <button type="submit" class="btn">Entrar</button>

    </form>
</main>

<?php include_once __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
