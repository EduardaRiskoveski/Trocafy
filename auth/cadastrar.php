
<?php

// Verifica se o formulário foi enviado pelo método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../cadastro.php');
    exit;
}

// Carrega a conexão com o banco de dados
require_once __DIR__ . '/../config/conexao.php';

// Recebe os dados enviados pelo formulário
$nome = trim($_POST['nome'] ?? '');
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
$confirmarSenha = $_POST['confirmar_senha'] ?? '';

// Verifica se todos os campos foram preenchidos
if ($nome === '' || $email === '' || $senha === '' || $confirmarSenha === '') {
    header('Location: ../cadastro.php?erro=campos');
    exit;
}

// Verifica se o e-mail possui formato válido
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: ../cadastro.php?erro=email');
    exit;
}

// Verifica se as senhas são iguais
if ($senha !== $confirmarSenha) {
    header('Location: ../cadastro.php?erro=senhas');
    exit;
}

// Verifica se a senha possui pelo menos 8 caracteres
if (strlen($senha) < 8) {
    header('Location: ../cadastro.php?erro=senha');
    exit;
}

// Verifica se o e-mail já está cadastrado
$sql = "SELECT id FROM usuarios WHERE email = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);

if ($stmt->fetch()) {
    header('Location: ../cadastro.php?erro=email');
    exit;
}

// Criptografa a senha antes de salvar no banco
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

// Insere o novo usuário no banco de dados
$sql = "
    INSERT INTO usuarios (nome, email, senha)
    VALUES (?, ?, ?)
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$nome, $email, $senhaHash]);

// Redireciona para o login após o cadastro
header('Location: ../login.php?cadastro=sucesso');
exit;
