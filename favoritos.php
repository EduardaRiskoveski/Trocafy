
<?php

// ======================================================
// 1. INICIAR SESSÃO E CARREGAR ARQUIVOS
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 2. VERIFICAR SE O USUÁRIO ESTÁ LOGADO
// ======================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];


// ======================================================
// 3. BUSCAR OS PRODUTOS FAVORITADOS
// ======================================================

$stmt = $pdo->prepare("
    SELECT
        p.*,
        u.nome AS vendedor,
        c.nome AS categoria
    FROM favoritos f
    INNER JOIN produtos p ON f.produto_id = p.id
    INNER JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE f.usuario_id = ?
    ORDER BY f.id DESC
");

$stmt->execute([$usuarioId]);

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus favoritos | Trocafy</title>

    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

<?php include_once __DIR__ . '/includes/cabecalho.php'; ?>


<main>

    <!-- ==================================================
         4. CABEÇALHO DA PÁGINA
    =================================================== -->

    <section class="produtos">

        <h2>❤️ Meus favoritos</h2>

        <p>
            Aqui estão os produtos que você salvou.
        </p>


        <!-- ==================================================
             5. LISTA DE FAVORITOS
        =================================================== -->

        <div class="lista-produtos">

            <?php if (count($produtos) > 0): ?>

                <?php foreach ($produtos as $produto): ?>

                    <?php

                    // Verifica a imagem do produto
                    $imagem = $produto['imagem'] ?? '';

                    $imagemSrc = null;

                    // Aceita links externos HTTP ou HTTPS
                    if (
                        filter_var($imagem, FILTER_VALIDATE_URL) &&
                        in_array(
                            strtolower(parse_url($imagem, PHP_URL_SCHEME) ?? ''),
                            ['http', 'https'],
                            true
                        )
                    ) {

                        $imagemSrc = $imagem;

                    // Aceita imagens da pasta de uploads
                    } elseif (
                        str_starts_with($imagem, 'uploads/produtos/')
                    ) {

                        $imagemSrc = $imagem;

                    }

                    ?>

                    <article class="produto">

                        <!-- IMAGEM -->

                        <div class="produto-imagem">

                            <?php if ($imagemSrc): ?>

                                <img
                                    src="<?= e($imagemSrc) ?>"
                                    alt="<?= e($produto['nome']) ?>"
                                    loading="lazy"
                                >

                            <?php else: ?>

                                <span>📦</span>

                            <?php endif; ?>

                        </div>


                        <!-- INFORMAÇÕES -->

                        <div class="produto-info">

                            <h3>
                                <?= e($produto['nome']) ?>
                            </h3>

                            <p>
                                <?= e($produto['descricao'] ?? '') ?>
                            </p>

                            <strong>
                                R$ <?= number_format(
                                    (float) $produto['preco'],
                                    2,
                                    ',',
                                    '.'
                                ) ?>
                            </strong>

                            <span>
                                <?= e($produto['categoria'] ?? 'Sem categoria') ?>
                            </span>

                            <small>
                                Anunciado por
                                <?= e($produto['vendedor']) ?>
                            </small>


                            <!-- REMOVER DOS FAVORITOS -->

                            <form
                                action="favoritar.php"
                                method="POST"
                            >

                                <input
                                    type="hidden"
                                    name="produto_id"
                                    value="<?= (int) $produto['id'] ?>"
                                >

                                <button
                                    type="submit"
                                    class="btn-favorito favoritado"
                                >
                                    ❤️ Remover dos favoritos
                                </button>

                            </form>


                            <!-- VER PRODUTO -->

                            <?php if ($produto['status'] === 'disponivel'): ?>

                                <a href="detalhes.php?id=<?= (int) $produto['id'] ?>">
                                    Ver produto
                                </a>

                            <?php else: ?>

                                <span class="produto-estado">
                                    Produto não disponível
                                </span>

                            <?php endif; ?>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <!-- MENSAGEM QUANDO NÃO HÁ FAVORITOS -->

                <div class="sem-produtos">

                    <span>💜</span>

                    <h3>Você ainda não tem favoritos</h3>

                    <p>
                        Explore os produtos e salve aqueles que mais gostar!
                    </p>

                    <a href="index.php#produtos">
                        Explorar produtos
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- RODAPÉ -->

<?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
