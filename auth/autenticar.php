
<?php

// Inicia a sessão antes de qualquer saída do PHP
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Verifica se o formulário foi enviado pelo método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ../login.php');
    exit;
}

// Carrega a conexão com o banco de dados
require_once __DIR__ . '/../config/conexao.php';

// Recebe os dados enviados pelo formulário
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Verifica se os campos estão preenchidos
if ($email === '' || $senha === '') {
    header('Location: ../login.php?erro=campos');
    exit;
}

// Busca o usuário pelo e-mail informado
$sql = "
    SELECT id, nome, email, senha, tipo
    FROM usuarios
    WHERE email = ?
";

$stmt = $pdo->prepare($sql);
$stmt->execute([$email]);

// Recupera os dados do usuário
$usuario = $stmt->fetch(PDO::FETCH_ASSOC);

// Verifica se o usuário existe e se a senha está correta
if (!$usuario || !password_verify($senha, $usuario['senha'])) {
    header('Location: ../login.php?erro=login');
    exit;
}

// Gera um novo identificador de sessão
session_regenerate_id(true);

// Armazena os dados do usuário na sessão
$_SESSION['usuario_id'] = (int) $usuario['id'];
$_SESSION['usuario_nome'] = $usuario['nome'];
$_SESSION['usuario_email'] = $usuario['email'];
$_SESSION['usuario_tipo'] = $usuario['tipo'];

// Redireciona para a página inicial
header('Location: ../index.php');
exit;
