<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
require_once __DIR__ . '/config/conexao.php';

$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.usuario_remetente_id,
        p.usuario_destinatario_id,
        po.titulo AS produto_oferecido_titulo,
        po.imagem AS produto_oferecido_imagem,
        pd.titulo AS produto_desejado_titulo,
        pd.imagem AS produto_desejado_imagem,
        ur.nome AS remetente_nome,
        ud.nome AS destinatario_nome
    FROM propostas p
    INNER JOIN produtos po ON p.produto_oferecido_id = po.id
    INNER JOIN produtos pd ON p.produto_desejado_id = pd.id
    INNER JOIN usuarios ur ON p.usuario_remetente_id = ur.id
    INNER JOIN usuarios ud ON p.usuario_destinatario_id = ud.id
    WHERE p.status = 'concluida'
      AND (p.usuario_destinatario_id = ? OR p.usuario_remetente_id = ?)
    ORDER BY p.id DESC
");
$stmt->execute([$usuarioId, $usuarioId]);
$historico = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/cabecalho.php';
?>

<main class="container">
    <h1>Histórico de Vendas e Trocas</h1>

    <?php if (isset($_GET['sucesso'])): ?>
        <p class="alerta-sucesso">Negociação concluída com sucesso!</p>
    <?php endif; ?>

    <?php if (empty($historico)): ?>
        <p>Você ainda não possui vendas ou trocas concluídas.</p>
    <?php else: ?>
        <div class="lista-historico">
            <?php foreach ($historico as $item): ?>
                <?php $foiVendedor = ((int) $item['usuario_destinatario_id'] === $usuarioId); ?>
                <div class="card-historico">
                    <p><strong>Negociação #<?= (int) $item['id'] ?></strong> — <?= $foiVendedor ? 'Proposta Recebida' : 'Proposta Enviada' ?></p>
                    <p>
                        <strong>Item anunciado:</strong> <?= htmlspecialchars($item['produto_desejado_titulo']) ?> 
                        (de <?= htmlspecialchars($item['destinatario_nome']) ?>)
                    </p>
                    <p>
                        <strong>Item trocado:</strong> <?= htmlspecialchars($item['produto_oferecido_titulo']) ?> 
                        (de <?= htmlspecialchars($item['remetente_nome']) ?>)
                    </p>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/rodape.php'; ?>