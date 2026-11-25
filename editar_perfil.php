
<?php

// Conexão com o banco de dados
require_once __DIR__ . '/config/conexao.php';

// Funções auxiliares
require_once __DIR__ . '/includes/funcoes.php';

// Inicia a sessão
session_start();

// Verifica se o usuário está logado
$id = $_SESSION['usuario_id'] ?? null;

if (!$id) {
    header('Location: login.php');
    exit;
}

// Busca os dados atuais do usuário
$stmt = $pdo->prepare("
    SELECT nome, email, foto
    FROM usuarios
    WHERE id = ?
");

$stmt->execute([$id]);
$usuario = $stmt->fetch();

// Se não encontrar o usuário, volta para o login
if (!$usuario) {
    header('Location: login.php');
    exit;
}

// Variáveis para mensagens
$erro = '';
$sucesso = '';

// Verifica se o formulário foi enviado
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Recebe os dados digitados
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');

    // Mantém a foto atual caso nenhuma nova seja enviada
    $foto = $usuario['foto'];

    // Verifica se nome e e-mail foram preenchidos
    if ($nome === '' || $email === '') {

        $erro = 'Preencha todos os campos.';

    // Verifica se o e-mail é válido
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

        $erro = 'Digite um e-mail válido.';

    } else {

        // Verifica se uma nova foto foi enviada
        if (
            isset($_FILES['foto']) &&
            $_FILES['foto']['error'] !== UPLOAD_ERR_NO_FILE
        ) {

            // Verifica se houve erro no envio
            if ($_FILES['foto']['error'] !== UPLOAD_ERR_OK) {

                $erro = 'Não foi possível enviar a foto.';

            // Limita o tamanho da foto a 2 MB
            } elseif ($_FILES['foto']['size'] > 2 * 1024 * 1024) {

                $erro = 'A foto deve ter no máximo 2 MB.';

            } else {

                // Verifica o tipo verdadeiro do arquivo
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $tipo = $finfo->file($_FILES['foto']['tmp_name']);

                // Tipos de imagem permitidos
                $tiposPermitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (!isset($tiposPermitidos[$tipo])) {

                    $erro = 'Envie uma imagem JPG, PNG ou WEBP.';

                } else {

                    // Cria a pasta para guardar as fotos
                    $pasta = __DIR__ . '/uploads/perfil/';

                    if (!is_dir($pasta)) {
                        mkdir($pasta, 0755, true);
                    }

                    // Cria um nome único para a imagem
                    $nomeArquivo = bin2hex(random_bytes(16))
                        . '.' . $tiposPermitidos[$tipo];

                    // Caminho completo do arquivo
                    $destino = $pasta . $nomeArquivo;

                    // Caminho que será salvo no banco
                    $fotoNova = 'uploads/perfil/' . $nomeArquivo;

                    // Move a imagem para a pasta
                    if (move_uploaded_file(
                        $_FILES['foto']['tmp_name'],
                        $destino
                    )) {

                        $foto = $fotoNova;

                    } else {

                        $erro = 'Erro ao salvar a foto.';

                    }
                }
            }
        }

        // Salva as alterações se não houver erros
        if ($erro === '') {

            try {

                $stmt = $pdo->prepare("
                    UPDATE usuarios
                    SET nome = ?, email = ?, foto = ?
                    WHERE id = ?
                ");

                $stmt->execute([$nome, $email, $foto, $id]);

                // Volta para o perfil após salvar
                header('Location: perfil.php?atualizado=1');
                exit;

            } catch (PDOException $e) {

                // Verifica se o e-mail já está cadastrado
                if ($e->getCode() === '23000') {
                    $erro = 'Este e-mail já está sendo utilizado.';
                } else {
                    $erro = 'Não foi possível atualizar o perfil.';
                }
            }
        }
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar perfil | Trocafy</title>
    <link rel="stylesheet" href="asset/style.css">
</head>

<body>

<!-- Inclui o cabeçalho -->
<?php include __DIR__ . '/includes/cabecalho.php'; ?>

<main class="editar-container">

    <section class="editar-card">

        <span class="editar-legenda">MINHA CONTA</span>

        <h1>Editar perfil</h1>

        <p class="editar-descricao">
            Atualize suas informações pessoais.
        </p>

        <!-- Exibe mensagem de erro -->
        <?php if ($erro !== ''): ?>

            <div class="mensagem-erro">
                <?= e($erro) ?>
            </div>

        <?php endif; ?>

        <!-- Formulário de edição -->
        <form method="POST" enctype="multipart/form-data">

            <!-- Campo do nome -->
            <label for="nome">Nome completo</label>

            <input
                type="text"
                id="nome"
                name="nome"
                value="<?= e($nome ?? $usuario['nome']) ?>"
                required
            >

            <!-- Campo do e-mail -->
            <label for="email">E-mail</label>

            <input
                type="email"
                id="email"
                name="email"
                value="<?= e($email ?? $usuario['email']) ?>"
                required
            >

            <!-- Campo da foto -->
            <label for="foto">Foto de perfil</label>

            <input
                type="file"
                id="foto"
                name="foto"
                accept="image/jpeg,image/png,image/webp"
            >

            <small class="dica-foto">
                Formatos: JPG, PNG ou WEBP. Máximo: 2 MB.
            </small>

            <!-- Botões -->
            <div class="editar-botoes">

                <button type="submit">
                    Salvar alterações
                </button>

                <a href="perfil.php">
                    Cancelar
                </a>

            </div>

        </form>

    </section>

</main>

<!-- Inclui o rodapé -->
<?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
