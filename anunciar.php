
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR SE O USUÁRIO ESTÁ LOGADO
// ======================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}


// ======================================================
// 3. CARREGAR OS ARQUIVOS NECESSÁRIOS
// ======================================================

require_once __DIR__ . '/includes/funcoes.php';
require_once __DIR__ . '/config/conexao.php';


// ======================================================
// 4. GERAR TOKEN DE SEGURANÇA
// ======================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// ======================================================
// 5. BUSCAR AS CATEGORIAS
// ======================================================

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 6. PROCESSAR O FORMULÁRIO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Receber os dados
    $nome = trim($_POST['nome'] ?? '');
    $preco = str_replace(',', '.', trim($_POST['preco'] ?? ''));
    $descricao = trim($_POST['descricao'] ?? '');
    $estado = $_POST['estado'] ?? '';

    // Receber a categoria
    $categoriaId = filter_input(
        INPUT_POST,
        'categoria_id',
        FILTER_VALIDATE_INT
    );

    // Receber o token
    $tokenFormulario = $_POST['csrf_token'] ?? '';


    // ==================================================
    // 7. VALIDAR O TOKEN
    // ==================================================

    if (
        !is_string($tokenFormulario) ||
        !hash_equals($csrfToken, $tokenFormulario)
    ) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página.');
    }


    // ==================================================
    // 8. VALIDAR OS CAMPOS
    // ==================================================

    if (
        $nome === '' ||
        strlen($nome) > 150 ||
        $preco === '' ||
        !is_numeric($preco) ||
        (float) $preco <= 0 ||
        (float) $preco > 99999999.99 ||
        $descricao === '' ||
        !$categoriaId ||
        $estado === ''
    ) {
        header('Location: anunciar.php?erro=campos');
        exit;
    }

    // Estados permitidos
    $estadosPermitidos = ['novo', 'usado'];

    if (!in_array($estado, $estadosPermitidos, true)) {
        header('Location: anunciar.php?erro=estado');
        exit;
    }


    // ==================================================
    // 9. VERIFICAR SE A CATEGORIA EXISTE
    // ==================================================

    $stmt = $pdo->prepare("
        SELECT id
        FROM categorias
        WHERE id = ?
    ");

    $stmt->execute([$categoriaId]);

    if (!$stmt->fetch()) {
        header('Location: anunciar.php?erro=categoria');
        exit;
    }


    // ==================================================
    // 10. RECEBER E VALIDAR A FOTO
    // ==================================================

    $imagem = null;
    $caminhoCompleto = null;

    $erroUpload = $_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($erroUpload !== UPLOAD_ERR_NO_FILE) {

        // Verificar erros no envio
        if ($erroUpload !== UPLOAD_ERR_OK) {
            header('Location: anunciar.php?erro=imagem');
            exit;
        }

        $arquivo = $_FILES['imagem'];

        // Limite de 5 MB
        if ($arquivo['size'] > 5 * 1024 * 1024) {
            header('Location: anunciar.php?erro=imagem');
            exit;
        }

        // Identificar o tipo real do arquivo
        $finfo = new finfo(FILEINFO_MIME_TYPE);
        $tipo = $finfo->file($arquivo['tmp_name']);

        // Formatos permitidos
        $tiposPermitidos = [
            'image/jpeg' => 'jpg',
            'image/png'  => 'png',
            'image/webp' => 'webp'
        ];

        if (
            !isset($tiposPermitidos[$tipo]) ||
            getimagesize($arquivo['tmp_name']) === false
        ) {
            header('Location: anunciar.php?erro=imagem');
            exit;
        }

        // Gerar nome aleatório para a imagem
        $nomeArquivo = bin2hex(random_bytes(16))
            . '.' . $tiposPermitidos[$tipo];

        // Definir pasta de destino
        $pastaDestino = __DIR__ . '/uploads/produtos/';

        // Criar a pasta caso ela não exista
        if (
            !is_dir($pastaDestino) &&
            !mkdir($pastaDestino, 0755, true)
        ) {
            header('Location: anunciar.php?erro=imagem');
            exit;
        }

        // Caminho completo do arquivo
        $caminhoCompleto = $pastaDestino . $nomeArquivo;

        // Salvar a imagem na pasta
        if (!move_uploaded_file(
            $arquivo['tmp_name'],
            $caminhoCompleto
        )) {
            header('Location: anunciar.php?erro=imagem');
            exit;
        }

        // Caminho que será salvo no banco
        $imagem = 'uploads/produtos/' . $nomeArquivo;
    }


    // ==================================================
    // 11. CADASTRAR O PRODUTO NO BANCO
    // ==================================================

    try {

        $sql = "
            INSERT INTO produtos (
                usuario_id,
                categoria_id,
                nome,
                preco,
                descricao,
                imagem,
                estado
            )
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ";

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $_SESSION['usuario_id'],
            $categoriaId,
            $nome,
            $preco,
            $descricao,
            $imagem,
            $estado
        ]);

        // Cadastro realizado
        header('Location: anunciar.php?sucesso=1');
        exit;

    } catch (PDOException $erro) {

        // Registrar erro técnico
        error_log($erro->getMessage());

        // Apagar a foto se o cadastro falhar
        if (
            $caminhoCompleto !== null &&
            file_exists($caminhoCompleto)
        ) {
            unlink($caminhoCompleto);
        }

        header('Location: anunciar.php?erro=salvar');
        exit;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Anunciar produto | Trocafy</title>

    <!-- CSS principal -->
    <link rel="stylesheet" href="asset/style.css">

    <!-- Estilos do formulário -->
    <style>

        .anunciar-container {
            max-width: 650px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 14px;
        }

        .anunciar-container h1 {
            margin-bottom: 10px;
            color: #7041bd;
        }

        .campo-formulario {
            margin-top: 20px;
        }

        .campo-formulario label {
            display: block;
            margin-bottom: 8px;
            font-weight: bold;
        }

        .campo-formulario input,
        .campo-formulario select,
        .campo-formulario textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddd;
            border-radius: 8px;
            font-size: 15px;
        }

        .campo-formulario textarea {
            min-height: 120px;
            resize: vertical;
        }

        .campo-formulario small {
            display: block;
            margin-top: 8px;
            color: #666;
        }

        .btn-publicar {
            width: 100%;
            padding: 14px;
            margin-top: 25px;
            background: #7041bd;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
        }

        .btn-publicar:hover {
            background: #58319b;
        }

        .mensagem {
            margin-top: 15px;
            padding: 12px;
            border-radius: 8px;
        }

        .sucesso {
            background: #d1e7dd;
            color: #0f5132;
        }

        .erro {
            background: #f8d7da;
            color: #842029;
        }

    </style>

</head>

<body>

    <!-- Cabeçalho do site -->
    <?php include __DIR__ . '/includes/cabecalho.php'; ?>


    <!-- Formulário de anúncio -->
    <main class="anunciar-container">

        <h1>Anunciar produto</h1>

        <p>
            Preencha os dados para publicar seu anúncio no Trocafy.
        </p>


        <!-- Mensagem de sucesso -->
        <?php if (isset($_GET['sucesso'])): ?>

            <div class="mensagem sucesso">
                Produto cadastrado com sucesso!
            </div>

        <?php endif; ?>


        <!-- Mensagens de erro -->
        <?php if (($_GET['erro'] ?? '') === 'campos'): ?>

            <div class="mensagem erro">
                Preencha todos os campos obrigatórios corretamente.
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'categoria'): ?>

            <div class="mensagem erro">
                Selecione uma categoria válida.
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'estado'): ?>

            <div class="mensagem erro">
                Selecione um estado válido.
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'imagem'): ?>

            <div class="mensagem erro">
                Envie uma imagem JPG, PNG ou WEBP com até 5 MB.
            </div>

        <?php elseif (($_GET['erro'] ?? '') === 'salvar'): ?>

            <div class="mensagem erro">
                Não foi possível cadastrar o produto.
            </div>

        <?php endif; ?>


        <!-- Formulário -->
        <form
            action="anunciar.php"
            method="POST"
            enctype="multipart/form-data"
        >

            <!-- Token de segurança -->
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($csrfToken) ?>"
            >


            <!-- Nome -->
            <div class="campo-formulario">

                <label for="nome">Nome do produto</label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="150"
                    required
                >

            </div>


            <!-- Preço -->
            <div class="campo-formulario">

                <label for="preco">Preço (R$)</label>

                <input
                    type="number"
                    id="preco"
                    name="preco"
                    min="0.01"
                    max="99999999.99"
                    step="0.01"
                    placeholder="Ex.: 50.00"
                    required
                >

            </div>


            <!-- Descrição -->
            <div class="campo-formulario">

                <label for="descricao">Descrição</label>

                <textarea
                    id="descricao"
                    name="descricao"
                    required
                ></textarea>

            </div>


            <!-- Foto do produto -->
            <div class="campo-formulario">

                <label for="imagem">
                    Foto do produto (opcional)
                </label>

                <input
                    type="file"
                    id="imagem"
                    name="imagem"
                    accept="image/jpeg,image/png,image/webp"
                >

                <small>
                    JPG, PNG ou WEBP. Tamanho máximo de 5 MB.
                </small>

            </div>


            <!-- Categoria -->
            <div class="campo-formulario">

                <label for="categoria_id">Categoria</label>

                <select
                    id="categoria_id"
                    name="categoria_id"
                    required
                >

                    <option value="">
                        Selecione uma categoria
                    </option>

                    <?php foreach ($categorias as $categoria): ?>

                        <option value="<?= (int) $categoria['id'] ?>">
                            <?= e($categoria['nome']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- Estado -->
            <div class="campo-formulario">

                <label for="estado">
                    Estado de conservação
                </label>

                <select
                    id="estado"
                    name="estado"
                    required
                >

                    <option value="">
                        Selecione o estado
                    </option>

                    <option value="novo">Novo</option>

                    <option value="usado">Usado</option>

                </select>

            </div>


            <!-- Botão -->
            <button
                type="submit"
                class="btn-publicar"
            >
                Publicar anúncio
            </button>

        </form>

    </main>


    <!-- Rodapé -->
    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
