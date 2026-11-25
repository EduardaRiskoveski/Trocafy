
<?php
require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastro | Trocafy</title>
    <link rel="stylesheet" href="asset/style.css">
</head>
<body>

<?php include_once __DIR__ . '/includes/cabecalho.php'; ?>

<main class="cadastro-container">

    <div class="cadastro-card">

        <h1>Crie sua conta</h1>
        <p class="cadastro-subtitulo">Faça parte da Trocafy!</p>

        <?php if (isset($_GET['erro'])): ?>
            <p class="notice">
                <?php
                switch ($_GET['erro']) {
                    case 'campos':
                        echo 'Preencha todos os campos.';
                        break;
                    case 'senhas':
                        echo 'As senhas não são iguais.';
                        break;
                    case 'senha':
                        echo 'A senha deve ter pelo menos 8 caracteres.';
                        break;
                    case 'email':
                        echo 'Este e-mail já está cadastrado.';
                        break;
                    default:
                        echo 'Não foi possível realizar o cadastro.';
                }
                ?>
            </p>
        <?php endif; ?>

        <form action="auth/cadastrar.php" method="POST" class="cadastro-form">

            <div class="campo">
                <label for="nome">Nome completo</label>
                <input type="text" id="nome" name="nome"
                       placeholder="Digite seu nome" required>
            </div>

            <div class="campo">
                <label for="email">E-mail</label>
                <input type="email" id="email" name="email"
                       placeholder="Digite seu e-mail" required>
            </div>

            <div class="campo">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha"
                       placeholder="Crie uma senha"
                       minlength="8" required>
            </div>

            <div class="campo">
                <label for="confirmar_senha">Confirmar senha</label>
                <input type="password" id="confirmar_senha"
                       name="confirmar_senha"
                       placeholder="Digite a senha novamente" required>
            </div>

            <button type="submit" name="cadastrar">
                Criar conta
            </button>

        </form>

        <p class="cadastro-login">
            Já possui uma conta?
            <a href="login.php">Entrar</a>
        </p>

    </div>

</main>

<?php include_once __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
