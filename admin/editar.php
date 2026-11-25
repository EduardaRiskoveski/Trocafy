
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR PERMISSÃO
// ======================================================

require_once __DIR__ . '/../includes/funcoes.php';

exigirAdmin();


// ======================================================
// 3. CONECTAR AO BANCO
// ======================================================

require_once __DIR__ . '/../config/conexao.php';


// ======================================================
// 4. TOKEN DE SEGURANÇA
// ======================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$csrfToken = $_SESSION['csrf_token'];


// ======================================================
// 5. RECEBER E VALIDAR ID
// ======================================================

$idInformado = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['id'] ?? '')
    : ($_GET['id'] ?? '');

$id = is_scalar($idInformado)
    ? filter_var($idInformado, FILTER_VALIDATE_INT)
    : false;

if (!$id || $id <= 0) {
    header('Location: produtos.php?erro=nao_encontrado');
    exit;
}


// ======================================================
// 6. BUSCAR PRODUTO
// ======================================================

$stmt = $pdo->prepare("
    SELECT *
    FROM produtos
    WHERE id = ?
");

$stmt->execute([$id]);

$produto = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$produto) {
    header('Location: produtos.php?erro=nao_encontrado');
    exit;
}


// Guarda a imagem original para preservar o arquivo
$imagemOriginal = $produto['imagem'] ?? null;


// ======================================================
// 7. BUSCAR CATEGORIAS
// ======================================================

$stmt = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
");

$categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 8. PROCESSAR FORMULÁRIO
// ======================================================

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Verificar token
    $tokenFormulario = $_POST['csrf_token'] ?? '';

    if (
        !is_string($tokenFormulario) ||
        !hash_equals($csrfToken, $tokenFormulario)
    ) {
        http_response_code(403);
        exit('Solicitação inválida. Atualize a página.');
    }

    // Receber campos
    $nome = trim($_POST['nome'] ?? '');

    $preco = str_replace(
        ',',
        '.',
        trim($_POST['preco'] ?? '')
    );

    $descricao = trim($_POST['descricao'] ?? '');

    $estado = $_POST['estado'] ?? '';

    $status = $_POST['status'] ?? '';

    $categoriaId = filter_var(
        $_POST['categoria_id'] ?? '',
        FILTER_VALIDATE_INT
    );


    // ==================================================
    // 9. VALIDAR CAMPOS
    // ==================================================

    if (
        $nome === '' ||
        strlen($nome) > 150 ||
        $descricao === '' ||
        $preco === '' ||
        !is_numeric($preco) ||
        (float) $preco <= 0 ||
        (float) $preco > 99999999.99 ||
        !$categoriaId
    ) {
        $erro = 'Preencha todos os campos corretamente.';
    }


    // Validar estado
    if (!in_array($estado, ['novo', 'usado'], true)) {
        $erro = 'Selecione um estado válido.';
    }


    // Validar status
    $statusPermitidos = [
        'disponivel',
        'reservado',
        'trocado'
    ];

    if (!in_array($status, $statusPermitidos, true)) {
        $erro = 'Selecione um status válido.';
    }


    // ==================================================
    // 10. VALIDAR CATEGORIA
    // ==================================================

    if ($erro === '') {

        $stmt = $pdo->prepare("
            SELECT id
            FROM categorias
            WHERE id = ?
        ");

        $stmt->execute([$categoriaId]);

        if (!$stmt->fetch()) {
            $erro = 'A categoria selecionada não existe.';
        }
    }


    // ==================================================
    // 11. PROCESSAR NOVA IMAGEM
    // ==================================================

    // Mantém a imagem antiga por padrão
    $imagem = $imagemOriginal;

    $novoArquivo = null;

    $erroUpload = $_FILES['imagem']['error'] ?? UPLOAD_ERR_NO_FILE;

    if ($erro === '' && $erroUpload !== UPLOAD_ERR_NO_FILE) {

        if ($erroUpload !== UPLOAD_ERR_OK) {

            $erro = 'Não foi possível enviar a imagem.';

        } else {

            $arquivo = $_FILES['imagem'];

            // Limite de 5 MB
            if ($arquivo['size'] > 5 * 1024 * 1024) {

                $erro = 'A imagem deve ter no máximo 5 MB.';

            } else {

                // Verificar formato real do arquivo
                $finfo = new finfo(FILEINFO_MIME_TYPE);

                $tipo = $finfo->file($arquivo['tmp_name']);

                $tiposPermitidos = [
                    'image/jpeg' => 'jpg',
                    'image/png' => 'png',
                    'image/webp' => 'webp'
                ];

                if (
                    !isset($tiposPermitidos[$tipo]) ||
                    getimagesize($arquivo['tmp_name']) === false
                ) {

                    $erro = 'Envie uma imagem JPG, PNG ou WEBP.';

                } else {

                    // Gerar nome único
                    $nomeArquivo = bin2hex(random_bytes(16))
                        . '.' . $tiposPermitidos[$tipo];

                    // Pasta de destino
                    $pastaDestino = __DIR__ . '/../uploads/produtos/';

                    if (
                        !is_dir($pastaDestino) &&
                        !mkdir($pastaDestino, 0755, true)
                    ) {
                        $erro = 'Não foi possível criar a pasta de imagens.';
                    } else {

                        $caminhoCompleto = $pastaDestino . $nomeArquivo;

                        if (
                            move_uploaded_file(
                                $arquivo['tmp_name'],
                                $caminhoCompleto
                            )
                        ) {

                            $novoArquivo = $caminhoCompleto;

                            $imagem = 'uploads/produtos/' . $nomeArquivo;

                        } else {

                            $erro = 'Não foi possível salvar a nova imagem.';

                        }
                    }
                }
            }
        }
    }


    // ==================================================
    // 12. ATUALIZAR NO BANCO DE DADOS
    // ==================================================

    if ($erro === '') {

        try {

            $sql = "
                UPDATE produtos
                SET
                    nome = ?,
                    preco = ?,
                    descricao = ?,
                    imagem = ?,
                    categoria_id = ?,
                    estado = ?,
                    status = ?
                WHERE id = ?
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $nome,
                $preco,
                $descricao,
                $imagem,
                $categoriaId,
                $estado,
                $status,
                $id
            ]);


            // Excluir imagem antiga somente após salvar a nova
            if (
                $novoArquivo !== null &&
                !empty($imagemOriginal) &&
                str_starts_with(
                    $imagemOriginal,
                    'uploads/produtos/'
                )
            ) {

                $arquivoAntigo = __DIR__ . '/../' . $imagemOriginal;

                if (
                    is_file($arquivoAntigo) &&
                    realpath($arquivoAntigo) !== realpath($novoArquivo)
                ) {
                    unlink($arquivoAntigo);
                }
            }

            header('Location: produtos.php?atualizado=sucesso');
            exit;

        } catch (PDOException $erroBanco) {

            error_log($erroBanco->getMessage());

            // Se o banco falhar, remove a nova imagem enviada
            if (
                $novoArquivo !== null &&
                is_file($novoArquivo)
            ) {
                unlink($novoArquivo);
            }

            $erro = 'Não foi possível atualizar o produto.';
        }
    }


    // ==================================================
    // 13. MANTER DADOS NO FORMULÁRIO
    // ==================================================

    $produto['nome'] = $nome;
    $produto['preco'] = $preco;
    $produto['descricao'] = $descricao;
    $produto['imagem'] = $imagem;
    $produto['categoria_id'] = $categoriaId;
    $produto['estado'] = $estado;
    $produto['status'] = $status;
}


// ======================================================
// 14. DEFINIR CAMINHO DA IMAGEM ATUAL
// ======================================================

$imagemAtual = $produto['imagem'] ?? '';

$imagemSrc = null;

if (
    filter_var($imagemAtual, FILTER_VALIDATE_URL) &&
    in_array(
        strtolower(parse_url($imagemAtual, PHP_URL_SCHEME) ?? ''),
        ['http', 'https'],
        true
    )
) {

    $imagemSrc = $imagemAtual;

} elseif (
    str_starts_with($imagemAtual, 'uploads/produtos/')
) {

    $imagemSrc = '../' . $imagemAtual;

}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar produto | Trocafy</title>

    <link rel="stylesheet" href="../asset/style.css">

    <style>

        .editar-container {
            max-width: 650px;
            margin: 40px auto;
            padding: 30px;
            background: white;
            border-radius: 14px;
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

        .imagem-atual {
            width: 150px;
            height: 150px;
            object-fit: cover;
            border-radius: 10px;
            margin-bottom: 12px;
            display: block;
        }

        .campo-formulario small {
            display: block;
            margin-top: 8px;
            color: #777;
        }

        .btn-atualizar {
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

        .btn-atualizar:hover {
            background: #58319b;
        }

        .mensagem-erro {
            background: #f8d7da;
            color: #842029;
            padding: 12px;
            border-radius: 8px;
            margin-top: 15px;
        }

        .voltar-admin {
            display: inline-block;
            margin-top: 20px;
            color: #7041bd;
            text-decoration: none;
            font-weight: bold;
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

                <a href="produtos.php">Produtos</a>

                <a href="../sair.php">Sair</a>

            </nav>

        </div>

    </header>


    <!-- FORMULÁRIO -->
    <main class="editar-container">

        <h1>Editar produto</h1>

        <p>Altere as informações do anúncio selecionado.</p>


        <?php if ($erro !== ''): ?>

            <div class="mensagem-erro">
                <?= e($erro) ?>
            </div>

        <?php endif; ?>


        <!-- enctype permite enviar arquivos -->
        <form action="editar.php" method="POST" enctype="multipart/form-data">

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


            <!-- NOME -->
            <div class="campo-formulario">

                <label for="nome">Nome do produto</label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="150"
                    value="<?= e((string) $produto['nome']) ?>"
                    required
                >

            </div>


            <!-- PREÇO -->
            <div class="campo-formulario">

                <label for="preco">Preço (R$)</label>

                <input
                    type="number"
                    id="preco"
                    name="preco"
                    min="0.01"
                    step="0.01"
                    value="<?= e((string) $produto['preco']) ?>"
                    required
                >

            </div>


            <!-- DESCRIÇÃO -->
            <div class="campo-formulario">

                <label for="descricao">Descrição</label>

                <textarea
                    id="descricao"
                    name="descricao"
                    required
                ><?= e((string) $produto['descricao']) ?></textarea>

            </div>


            <!-- IMAGEM ATUAL -->
            <div class="campo-formulario">

                <label>Imagem atual</label>

                <?php if ($imagemSrc !== null): ?>

                    <img
                        src="<?= e($imagemSrc) ?>"
                        alt="Imagem atual do produto"
                        class="imagem-atual"
                    >

                <?php else: ?>

                    <p>Este produto ainda não possui imagem.</p>

                <?php endif; ?>

            </div>


            <!-- NOVA IMAGEM -->
            <div class="campo-formulario">

                <label for="imagem">Escolher outra imagem (opcional)</label>

                <input
                    type="file"
                    id="imagem"
                    name="imagem"
                    accept="image/jpeg,image/png,image/webp"
                >

                <small>
                    JPG, PNG ou WEBP. Máximo de 5 MB.
                    Deixe vazio para manter a imagem atual.
                </small>

            </div>


            <!-- CATEGORIA -->
            <div class="campo-formulario">

                <label for="categoria_id">Categoria</label>

                <select
                    id="categoria_id"
                    name="categoria_id"
                    required
                >

                    <?php foreach ($categorias as $categoria): ?>

                        <option
                            value="<?= (int) $categoria['id'] ?>"
                            <?= (int) $produto['categoria_id'] === (int) $categoria['id'] ? 'selected' : '' ?>
                        >
                            <?= e($categoria['nome']) ?>
                        </option>

                    <?php endforeach; ?>

                </select>

            </div>


            <!-- ESTADO -->
            <div class="campo-formulario">

                <label for="estado">Estado de conservação</label>

                <select id="estado" name="estado" required>

                    <option value="novo"
                        <?= $produto['estado'] === 'novo' ? 'selected' : '' ?>>
                        Novo
                    </option>

                    <option value="usado"
                        <?= $produto['estado'] === 'usado' ? 'selected' : '' ?>>
                        Usado
                    </option>

                </select>

            </div>


            <!-- STATUS -->
            <div class="campo-formulario">

                <label for="status">Status do anúncio</label>

                <select id="status" name="status" required>

                    <option value="disponivel"
                        <?= $produto['status'] === 'disponivel' ? 'selected' : '' ?>>
                        Disponível
                    </option>

                    <option value="reservado"
                        <?= $produto['status'] === 'reservado' ? 'selected' : '' ?>>
                        Reservado
                    </option>

                    <option value="trocado"
                        <?= $produto['status'] === 'trocado' ? 'selected' : '' ?>>
                        Trocado
                    </option>

                </select>

            </div>


            <button type="submit" class="btn-atualizar">
                Salvar alterações
            </button>

        </form>


        <a href="produtos.php" class="voltar-admin">
            ← Voltar aos produtos
        </a>

    </main>

</body>
</html>
