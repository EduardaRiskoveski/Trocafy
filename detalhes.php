
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. CONEXÃO E FUNÇÕES
// ======================================================

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 3. VALIDAR ID DO PRODUTO
// ======================================================

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    header('Location: index.php');
    exit;
}


// ======================================================
// 4. BUSCAR PRODUTO
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

if (!$produto) {
    header('Location: index.php');
    exit;
}


// ======================================================
// 5. VERIFICAR USUÁRIO
// ======================================================

$logado = isset($_SESSION['usuario_id']);

$usuarioId = $logado ? (int) $_SESSION['usuario_id'] : 0;

$ehDono = $logado &&
    $usuarioId === (int) $produto['usuario_id'];


// ======================================================
// 6. TIPO DE NEGOCIAÇÃO
// ======================================================

$tipoNegociacao = $produto['tipo_negociacao'] ?? 'troca';

$permiteTroca = in_array(
    $tipoNegociacao,
    ['troca', 'ambos'],
    true
);

$permiteVenda = in_array(
    $tipoNegociacao,
    ['venda', 'ambos'],
    true
);


// ======================================================
// 7. TOKEN DE SEGURANÇA
// ======================================================

if ($logado && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ======================================================
// 8. BUSCAR PRODUTOS DO USUÁRIO
// ======================================================

$meusProdutos = [];

if ($logado && !$ehDono && $permiteTroca) {

    $stmt = $pdo->prepare("
        SELECT id, nome, imagem
        FROM produtos
        WHERE usuario_id = ?
        AND status = 'disponivel'
        ORDER BY criado_em DESC
    ");

    $stmt->execute([$usuarioId]);

    $meusProdutos = $stmt->fetchAll(PDO::FETCH_ASSOC);
}


// ======================================================
// 9. VERIFICAR IMAGEM
// ======================================================

$imagem = $produto['imagem'] ?? '';

$imagemSrc = null;

if (
    filter_var($imagem, FILTER_VALIDATE_URL) &&
    in_array(
        strtolower(parse_url($imagem, PHP_URL_SCHEME) ?? ''),
        ['http', 'https'],
        true
    )
) {
    $imagemSrc = $imagem;

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

    <!-- CSS principal -->
    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

    <!-- CABEÇALHO -->
    <?php include_once __DIR__ . '/includes/cabecalho.php'; ?>


    <!-- CONTEÚDO PRINCIPAL -->
    <main class="pagina-detalhes">

        <section class="detalhes-produto">

            <!-- IMAGEM -->
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


            <!-- INFORMAÇÕES -->
            <div class="detalhes-info">

                <span class="detalhes-categoria">
                    <?= e($produto['categoria'] ?? 'Sem categoria') ?>
                </span>

                <h1>
                    <?= e($produto['nome']) ?>
                </h1>


                <!-- PREÇO -->
                <h2 class="detalhes-preco">

                    R$ <?= number_format(
                        (float) $produto['preco'],
                        2,
                        ',',
                        '.'
                    ) ?>

                </h2>


                <!-- TIPO DE NEGOCIAÇÃO -->
                <div class="detalhes-negociacao">

                    <strong>Tipo de negociação:</strong>

                    <?php if ($tipoNegociacao === 'troca'): ?>

                        <span>Somente troca</span>

                    <?php elseif ($tipoNegociacao === 'venda'): ?>

                        <span>Somente venda</span>

                    <?php else: ?>

                        <span>Troca ou venda</span>

                    <?php endif; ?>

                </div>


                <!-- DESCRIÇÃO -->
                <div class="detalhes-descricao">

                    <h3>Descrição</h3>

                    <p>
                        <?= nl2br(e($produto['descricao'] ?? 'Sem descrição.')) ?>
                    </p>

                </div>


                <!-- INFORMAÇÕES -->
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


                <!-- ÁREA DE NEGOCIAÇÃO -->
                <section class="area-negociacao">

                    <?php if (!$logado): ?>

                        <p>
                            Entre na sua conta para comprar ou fazer
                            uma proposta de troca.
                        </p>

                        <a href="login.php" class="btn-principal">
                            Entrar para negociar
                        </a>


                    <?php elseif ($ehDono): ?>

                        <p>
                            Este anúncio pertence a você.
                        </p>


                    <?php else: ?>


                        <!-- OPÇÃO DE COMPRA -->
                        <?php if ($permiteVenda): ?>

                            <div class="area-compra">

                                <h2>Quero comprar</h2>

                                <p>
                                    Você pode demonstrar interesse
                                    neste produto e combinar o pagamento
                                    diretamente com o vendedor.
                                </p>

                                <form
                                    action="solicitar_compra.php"
                                    method="POST"
                                >

                                    <input
                                        type="hidden"
                                        name="csrf_token"
                                        value="<?= e($_SESSION['csrf_token']) ?>"
                                    >

                                    <input
                                        type="hidden"
                                        name="produto_id"
                                        value="<?= (int) $produto['id'] ?>"
                                    >

                                    <label for="mensagem_compra">
                                        Mensagem para o vendedor
                                    </label>

                                    <textarea
                                        name="mensagem"
                                        id="mensagem_compra"
                                        rows="3"
                                        maxlength="1000"
                                        placeholder="Olá! Tenho interesse em comprar este produto."
                                    ></textarea>

                                    <button
                                        type="submit"
                                        class="btn-compra"
                                    >
                                        Tenho interesse em comprar
                                    </button>

                                </form>

                            </div>

                        <?php endif; ?>


                        <!-- OPÇÃO DE TROCA -->
                        <?php if ($permiteTroca): ?>

                            <div class="area-proposta">

                                <h2>Quero fazer uma troca</h2>

                                <?php if (count($meusProdutos) > 0): ?>

                                    <p>
                                        Escolha um produto seu para
                                        oferecer em troca de
                                        <?= e($produto['nome']) ?>.
                                    </p>

                                    <form
                                        action="enviar_proposta.php"
                                        method="POST"
                                    >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="produto_desejado_id"
                                            value="<?= (int) $produto['id'] ?>"
                                        >

                                        <label for="produto_oferecido_id">
                                            Seu produto
                                        </label>

                                        <select
                                            name="produto_oferecido_id"
                                            id="produto_oferecido_id"
                                            required
                                        >

                                            <option value="">
                                                Selecione um produto
                                            </option>

                                            <?php foreach ($meusProdutos as $meuProduto): ?>

                                                <option value="<?= (int) $meuProduto['id'] ?>">
                                                    <?= e($meuProduto['nome']) ?>
                                                </option>

                                            <?php endforeach; ?>

                                        </select>


                                        <label for="mensagem">
                                            Mensagem para o vendedor
                                        </label>

                                        <textarea
                                            name="mensagem"
                                            id="mensagem"
                                            rows="4"
                                            maxlength="1000"
                                            placeholder="Escreva sua proposta..."
                                        ></textarea>


                                        <button
                                            type="submit"
                                            class="btn-principal"
                                        >
                                            Enviar proposta
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <p>
                                        Você ainda não possui produtos
                                        disponíveis para oferecer em uma troca.
                                    </p>

                                    <a
                                        href="anunciar.php"
                                        class="btn-principal"
                                    >
                                        Anunciar meu produto
                                    </a>

                                <?php endif; ?>

                            </div>

                        <?php endif; ?>

                    <?php endif; ?>

                </section>


                <!-- VOLTAR -->
                <a
                    href="index.php#produtos"
                    class="btn-voltar"
                >
                    Voltar aos produtos
                </a>

            </div>

        </section>

    </main>


    <!-- RODAPÉ -->
    <?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
