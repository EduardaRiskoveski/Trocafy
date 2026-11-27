
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];

$sql = "SELECT
            sc.id,
            sc.data_conclusao,
            sc.comprador_id,
            sc.vendedor_id,
            p.nome AS produto,
            p.imagem,
            comprador.nome AS comprador,
            vendedor.nome AS vendedor
        FROM solicitacoes_compra sc
        INNER JOIN produtos p ON p.id = sc.produto_id
        INNER JOIN usuarios comprador ON comprador.id = sc.comprador_id
        INNER JOIN usuarios vendedor ON vendedor.id = sc.vendedor_id
        WHERE sc.compra_concluida = 1
        AND (sc.comprador_id = :comprador OR sc.vendedor_id = :vendedor)
        ORDER BY sc.data_conclusao DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':comprador' => $usuarioId,
    ':vendedor' => $usuarioId
]);

$compras = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once __DIR__ . '/includes/cabecalho.php';
?>

<main class="container">
    <h1>Minhas Compras</h1>

    <?php if (empty($compras)): ?>

        <p>Você ainda não possui compras concluídas.</p>

    <?php else: ?>

        <?php foreach ($compras as $compra): ?>

            <div class="card-compra">

                <?php if (!empty($compra['imagem'])): ?>
                    <img
                        src="uploads/<?php echo e($compra['imagem']); ?>"
                        alt="Produto"
                        width="120"
                    >
                <?php endif; ?>

                <h3><?php echo e($compra['produto']); ?></h3>

                <?php if ($compra['comprador_id'] == $usuarioId): ?>
                    <p>Você comprou este produto.</p>
                    <p>Vendedor: <?php echo e($compra['vendedor']); ?></p>
                <?php else: ?>
                    <p>Você vendeu este produto.</p>
                    <p>Comprador: <?php echo e($compra['comprador']); ?></p>
                <?php endif; ?>

                <p>
                    Concluída em:
                    <?php
                    echo date(
                        'd/m/Y H:i',
                        strtotime($compra['data_conclusao'])
                    );
                    ?>
                </p>

                <strong>Compra concluída</strong>

            </div>

        <?php endforeach; ?>

    <?php endif; ?>
</main>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>
