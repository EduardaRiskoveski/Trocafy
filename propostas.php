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

// Garante token CSRF para os formulários de ação
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Busca propostas RECEBIDAS pelo usuário (pendentes ou aceitas)
$stmt = $pdo->prepare("
    SELECT 
        p.id,
        p.mensagem,
        p.status,
        p.produto_oferecido_id,
        p.produto_desejado_id,
        po.titulo AS produto_oferecido_titulo,
        po.imagem AS produto_oferecido_imagem,
        pd.titulo AS produto_desejado_titulo,
        pd.imagem AS produto_desejado_imagem,
        u.nome AS remetente_nome
    FROM propostas p
    INNER JOIN produtos po ON p.produto_oferecido_id = po.id
    INNER JOIN produtos pd ON p.produto_desejado_id = pd.id
    INNER JOIN usuarios u ON p.usuario_remetente_id = u.id
    WHERE p.usuario_destinatario_id = ?
      AND p.status IN ('pendente', 'aceita')
    ORDER BY p.id DESC
");
$stmt->execute([$usuarioId]);
$propostasRecebidas = $stmt->fetchAll(PDO::FETCH_ASSOC);

include __DIR__ . '/includes/cabecalho.php';
?>

<main class="container">
    <h1>Propostas Recebidas</h1>

    <?php if (isset($_GET['erro'])): ?>
        <p class="alerta-erro">Não foi possível concluir a negociação. Verifique se os produtos ainda estão disponíveis.</p>
    <?php endif; ?>

    <?php if (empty($propostasRecebidas)): ?>
        <p>Você não possui propostas pendentes no momento.</p>
    <?php else: ?>
        <div class="lista-propostas">
            <?php foreach ($propostasRecebidas as $prop): ?>
                <div class="card-proposta">
                    <p><strong>Interessado:</strong> <?= htmlspecialchars($prop['remetente_nome']) ?></p>
                    <p><strong>Seu item:</strong> <?= htmlspecialchars($prop['produto_desejado_titulo']) ?></p>
                    <p><strong>Item oferecido em troca:</strong> <?= htmlspecialchars($prop['produto_oferecido_titulo']) ?></p>
                    
                    <?php if (!empty($prop['mensagem'])): ?>
                        <p><strong>Mensagem:</strong> <?= nl2br(htmlspecialchars($prop['mensagem'])) ?></p>
                    <?php endif; ?>

                    <form action="concluir_venda.php" method="POST" style="margin-top: 10px;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="proposta_id" value="<?= (int) $prop['id'] ?>">
                        
                        <button type="submit" name="acao" value="concluir" onclick="return confirm('Confirmar troca/venda? Os dois produtos sairão da vitrine.')">
                            Aceitar e Concluir Negociação
                        </button>
                        
                        <button type="submit" name="acao" value="recusar" onclick="return confirm('Deseja recusar esta proposta?')">
                            Recusar
                        </button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/includes/rodape.php'; ?>