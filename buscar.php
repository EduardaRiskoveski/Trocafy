
<?php

// ======================================================
// 1. CONEXÃO COM O BANCO E FUNÇÕES
// ======================================================

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 2. RECEBER OS DADOS DA BUSCA
// ======================================================

// Recebe o nome digitado pelo visitante
$termo = trim($_GET['q'] ?? '');

// Recebe a categoria selecionada
$categoriaId = filter_input(
    INPUT_GET,
    'categoria',
    FILTER_VALIDATE_INT
);

// ======================================================
// 3. BUSCAR AS CATEGORIAS
// ======================================================

$categorias = $pdo->query("
    SELECT id, nome
    FROM categorias
    ORDER BY nome
")->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 4. MONTAR A CONSULTA DE PRODUTOS
// ======================================================

// Busca apenas produtos disponíveis
$sql = "
    SELECT
        p.*,
        u.nome AS vendedor,
        c.nome AS categoria
    FROM produtos p
    INNER JOIN usuarios u ON p.usuario_id = u.id
    LEFT JOIN categorias c ON p.categoria_id = c.id
    WHERE p.status = 'disponivel'
";

$parametros = [];


// ======================================================
// 5. FILTRAR PELO NOME DO PRODUTO
// ======================================================

if ($termo !== '') {

    // Procura o termo no nome ou na descrição
    $sql .= " AND (p.nome LIKE ? OR p.descricao LIKE ?)";

    $parametros[] = '%' . $termo . '%';
    $parametros[] = '%' . $termo . '%';

}


// ======================================================
// 6. FILTRAR PELA CATEGORIA
// ======================================================

if ($categoriaId && $categoriaId > 0) {

    $sql .= " AND p.categoria_id = ?";

    $parametros[] = $categoriaId;

}


// ======================================================
// 7. ORDENAR OS RESULTADOS
// ======================================================

$sql .= " ORDER BY p.criado_em DESC";


// ======================================================
// 8. EXECUTAR A CONSULTA
// ======================================================

$stmt = $pdo->prepare($sql);
$stmt->execute($parametros);

$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <!-- Configurações da página -->
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Buscar produtos - Trocafy</title>

    <!-- Arquivo de estilos -->
    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

<!-- ======================================================
    9. CABEÇALHO
======================================================= -->

<?php include_once __DIR__ . '/includes/cabecalho.php'; ?>


<main>

    <!-- ==================================================
        10. FORMULÁRIO DE BUSCA
    =================================================== -->

    <section class="produtos">

        <h1>Buscar produtos</h1>

        <p>Encontre o que você procura no Trocafy.</p>

        <form action="buscar.php" method="GET">

            <!-- Campo para pesquisar pelo nome -->
            <input
                type="search"
                name="q"
                placeholder="Digite o nome do produto..."
                value="<?= e($termo) ?>"
            >

            <!-- Seleção de categoria -->
            <select name="categoria">

                <option value="">Todas as categorias</option>

                <?php foreach ($categorias as $categoria): ?>

                    <option
                        value="<?= (int)$categoria['id'] ?>"
                        <?= $categoriaId == $categoria['id'] ? 'selected' : '' ?>
                    >
                        <?= e($categoria['nome']) ?>
                    </option>

                <?php endforeach; ?>

            </select>

            <!-- Botão para pesquisar -->
            <button type="submit">
                Buscar
            </button>

        </form>


        <!-- ==================================================
            11. RESULTADOS DA BUSCA
        =================================================== -->

        <h2>Resultados encontrados</h2>

        <div class="lista-produtos">

            <?php if (count($produtos) > 0): ?>

                <?php foreach ($produtos as $produto): ?>

                    <?php

                    // Verifica o caminho da imagem
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

                    // Aceita imagens da pasta de uploads
                    } elseif (str_starts_with($imagem, 'uploads/produtos/')) {

                        $imagemSrc = $imagem;

                    }

                    ?>

                    <article class="produto">

                        <!-- Foto do produto -->
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


                        <!-- Informações do produto -->
                        <div class="produto-info">

                            <h3>
                                <?= e($produto['nome']) ?>
                            </h3>

                            <p>
                                <?= e($produto['descricao'] ?? '') ?>
                            </p>

                            <strong>
                                R$ <?= number_format(
                                    (float)$produto['preco'],
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

                            <a href="detalhes.php?id=<?= (int)$produto['id'] ?>">
                                Ver produto
                            </a>

                        </div>

                    </article>

                <?php endforeach; ?>

            <?php else: ?>

                <!-- Mensagem quando nenhum produto é encontrado -->
                <div class="sem-produtos">

                    <span>🔎</span>

                    <h3>Nenhum produto encontrado</h3>

                    <p>
                        Tente pesquisar outro nome ou categoria.
                    </p>

                    <a href="buscar.php">
                        Limpar busca
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>


<!-- ======================================================
    12. RODAPÉ
======================================================= -->

<?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
