
<?php

// ======================================================
// 1. INICIAR SESSÃO, CONEXÃO E FUNÇÕES
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

// Verifica se existe usuário logado
$usuarioLogado = isset($_SESSION['usuario_id']);

$usuarioId = $usuarioLogado
    ? (int) $_SESSION['usuario_id']
    : null;


// ======================================================
// 2. BUSCAR CATEGORIAS
// ======================================================

$categorias = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 3. BUSCAR PRODUTOS DISPONÍVEIS
// ======================================================

$produtos = $pdo->query("
    SELECT
        p.*,
        u.nome AS vendedor,
        c.nome AS categoria
    FROM produtos p
    INNER JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.status = 'disponivel'
    ORDER BY p.criado_em DESC
")->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 4. BUSCAR OS FAVORITOS DO USUÁRIO LOGADO
// ======================================================

$idsFavoritos = [];

if ($usuarioLogado) {

    $stmt = $pdo->prepare("
        SELECT produto_id
        FROM favoritos
        WHERE usuario_id = ?
    ");

    $stmt->execute([$usuarioId]);

    $idsFavoritos = array_map(
        'intval',
        $stmt->fetchAll(PDO::FETCH_COLUMN)
    );
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Trocafy 💜</title>

    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

<?php include_once __DIR__ . '/includes/cabecalho.php'; ?>

<main>

    <!-- ==================================================
        5. DESTAQUE PRINCIPAL
    =================================================== -->

    <section class="destaque">

        <div class="destaque-texto">

            <span class="etiqueta">
                UM NOVO JEITO DE CONSUMIR
            </span>

            <h1>
                O que você não usa pode ser o que alguém procura!
            </h1>

            <p>
                No Trocafy, você encontra pessoas para trocar produtos,
                renovar seus objetos e descobrir novas possibilidades.
            </p>

            <a href="index.php#produtos" class="btn-principal">
                Começar agora</a>

        </div>

        <div class="destaque-icone">
            🔄
        </div>

    </section>


    <!-- ==================================================
        6. CATEGORIAS
    =================================================== -->

    <section class="categorias" id="categorias">

        <h2>Explore as categorias</h2>

        <p>Encontre produtos de diferentes tipos.</p>
<div class="lista-categorias">

    <?php if (count($categorias) > 0): ?>

        <?php foreach ($categorias as $categoria): ?>

            <a href="buscar.php?categoria=<?= (int) $categoria['id'] ?>" class="categoria">
                <?= e($categoria['nome']) ?>
            </a>

        <?php endforeach; ?>

    <?php else: ?>

        <p>As categorias aparecerão aqui em breve.</p>

    <?php endif; ?>

</div>

    </section>


    <!-- ==================================================
        7. PRODUTOS DISPONÍVEIS
    =================================================== -->

    <section class="produtos" id="produtos">

        <h2>Produtos disponíveis</h2>

        <p>Veja o que as pessoas estão oferecendo para troca.</p>

        <div class="lista-produtos">

            <?php if (count($produtos) > 0): ?>

                <?php foreach ($produtos as $produto): ?>

                    <?php

                    // Verifica se existe uma imagem cadastrada
                    $imagem = $produto['imagem'] ?? '';

                    $imagemSrc = null;

                    // Aceita links externos seguros
                    if (
                        filter_var($imagem, FILTER_VALIDATE_URL) &&
                        in_array(
                            strtolower(parse_url($imagem, PHP_URL_SCHEME) ?? ''),
                            ['http', 'https'],
                            true
                        )
                    ) {

                        $imagemSrc = $imagem;

                    // Aceita imagens salvas na pasta de uploads
                    } elseif (
                        str_starts_with($imagem, 'uploads/produtos/')
                    ) {

                        $imagemSrc = $imagem;

                    }

                    // Verifica se o produto está nos favoritos
                    $estaFavoritado = in_array(
                        (int) $produto['id'],
                        $idsFavoritos,
                        true
                    );
                    // ======================================================
                    // LIMITAR A DESCRIÇÃO DO PRODUTO A 100 CARACTERES
                    // ======================================================

                    // Recupera a descrição completa do produto
                        $descricao = $produto['descricao'] ?? '';

                    // ======================================================
                    // LIMITAR A DESCRIÇÃO DO PRODUTO A 100 CARACTERES
                    // ======================================================

                    $descricao = $produto['descricao'] ?? '';

                    if (iconv_strlen($descricao, 'UTF-8') > 28) {

                        $descricao = iconv_substr(
                            $descricao,
                            0,
                            28,
                            'UTF-8'
                        ) . '...';
}
                    // ======================================================
                    // LIMITAR O TÍTULO DO PRODUTO A 100 CARACTERES
                    // ======================================================

                    // Recupera o título completo
                    $titulo = $produto['nome'] ?? '';

                    // Verifica se o título ultrapassa 100 caracteres
                    if (iconv_strlen($titulo, 'UTF-8') > 25) {

                        // Mantém somente os primeiros 100 caracteres
                        $titulo = iconv_substr(
                            $titulo,
                            0,
                            25,
                            'UTF-8'
                        ) . '...';

                    }
                    ?>

                    <article class="produto">

                        <!-- FOTO DO PRODUTO -->

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


                        <!-- INFORMAÇÕES DO PRODUTO -->

                        <div class="produto-info">

                            <h3>
                                <?= e($titulo) ?>
                            </h3>

                            <p>
                                <?= e($descricao) ?>
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


                            <!-- BOTÃO DE FAVORITAR -->

                            <?php if ($usuarioLogado): ?>

                                <form
                                    action="favoritar.php"
                                    method="POST"
                                    class="favorito-form"
                                >

                                    <input
                                        type="hidden"
                                        name="produto_id"
                                        value="<?= (int) $produto['id'] ?>"
                                    >

                                    <button
                                        type="submit"
                                        class="btn-favorito <?= $estaFavoritado ? 'favoritado' : '' ?>"
                                        aria-label="<?= $estaFavoritado ? 'Remover dos favoritos' : 'Adicionar aos favoritos' ?>"
                                    >

                                        <?= $estaFavoritado ? '❤️ Remover dos favoritos' : '🤍 Adicionar aos favoritos' ?>

                                    </button>

                                </form>

                            <?php else: ?>

                                <a
                                    href="login.php"
                                    class="btn-favorito"
                                >
                                    🤍 Fazer login para favoritar
                                </a>

                            <?php endif; ?>


                            <!-- LINK PARA VER O PRODUTO -->

                            <a href="detalhes.php?id=<?= (int) $produto['id'] ?>">
                                Ver produto
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <div class="sem-produtos">

                    <span>🛍️</span>

                    <h3>Nenhum produto anunciado ainda</h3>

                    <p>
                        Seja a primeira pessoa a anunciar no Trocafy!
                    </p>

                    <a href="cadastro.php">
                        Criar minha conta
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- ======================================================
    8. RODAPÉ
======================================================= -->

<?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
