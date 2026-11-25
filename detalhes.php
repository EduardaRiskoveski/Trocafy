
<?php

// ======================================================
// 1. CONEXÃO COM O BANCO E FUNÇÕES
// ======================================================

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 2. RECEBER E VALIDAR O ID
// ======================================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}


// ======================================================
// 3. BUSCAR PRODUTO NO BANCO
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        p.*,
        u.nome AS vendedor,
        c.nome AS categoria
    FROM produtos p
    INNER JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.id = ?
    AND p.status = 'disponivel'
    LIMIT 1
");

$stmt->execute([$id]);

$produto = $stmt->fetch(PDO::FETCH_ASSOC);


// ======================================================
// 4. VERIFICAR SE O PRODUTO EXISTE
// ======================================================

if (!$produto) {
    header('Location: index.php');
    exit;
}


// ======================================================
// 5. VERIFICAR A IMAGEM
// ======================================================

$imagem = $produto['imagem'] ?? '';
$imagemSrc = null;

// Imagem externa
if (
    filter_var($imagem, FILTER_VALIDATE_URL) &&
    in_array(
        strtolower(parse_url($imagem, PHP_URL_SCHEME) ?? ''),
        ['http', 'https'],
        true
    )
) {
    $imagemSrc = $imagem;

// Imagem salva no servidor
} elseif (
    str_starts_with($imagem, 'uploads/produtos/')
) {
    $imagemSrc = $imagem;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= e($produto['nome']) ?> - Trocafy</title>

    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

    <!-- CABEÇALHO -->
    <?php include_once __DIR__ . '/includes/cabecalho.php'; ?>


    <!-- CONTEÚDO PRINCIPAL -->
    <main class="pagina-detalhes">

        <section class="detalhes-produto">

            <!-- IMAGEM DO PRODUTO -->
            <div class="detalhes-imagem">

                <?php if ($imagemSrc): ?>

                    <img
                        src="<?= e($imagemSrc) ?>"
                        alt="<?= e($produto['nome']) ?>"
                    >

                <?php else: ?>

                    <span>📦</span>

                <?php endif; ?>

            </div>


            <!-- INFORMAÇÕES DO PRODUTO -->
            <div class="detalhes-info">

                <span class="detalhes-categoria">
                    <?= e($produto['categoria'] ?? 'Sem categoria') ?>
                </span>

                <h1>
                    <?= e($produto['nome']) ?>
                </h1>

                <h2 class="detalhes-preco">
                    R$
                    <?= number_format(
                        (float) $produto['preco'],
                        2,
                        ',',
                        '.'
                    ) ?>
                </h2>

                <div class="detalhes-descricao">

                    <h3>Descrição</h3>

                    <p>
                        <?= nl2br(e($produto['descricao'] ?? 'Sem descrição.')) ?>
                    </p>

                </div>

                <div class="detalhes-informacoes">

                    <p>
                        <strong>Estado:</strong>
                        <?= e(ucfirst($produto['estado'] ?? 'Não informado')) ?>
                    </p>

                    <p>
                        <strong>Vendedor:</strong>
                        <?= e($produto['vendedor']) ?>
                    </p>

                </div>

                <a href="index.php#produtos" class="btn-principal">
                    Voltar aos produtos
                </a>

            </div>

        </section>

    </main>


    <!-- RODAPÉ -->
    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
