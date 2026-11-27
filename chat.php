
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. CARREGAR CONEXÃO E FUNÇÕES
// ======================================================

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 3. VERIFICAR LOGIN
// ======================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];


// ======================================================
// 4. TOKEN DE SEGURANÇA
// ======================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ======================================================
// 5. IDENTIFICAR CONVERSA
// ======================================================

$conversaId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$conversaId) {
    header('Location: minhas_conversas.php');
    exit;
}


// ======================================================
// 6. BUSCAR CONVERSA
// ======================================================

$sql = "SELECT
            c.id,
            c.tipo,
            c.solicitacao_id,
            c.usuario1_id,
            c.usuario2_id,
            u.nome AS outro_usuario
        FROM conversas c
        INNER JOIN usuarios u
            ON u.id = CASE
                WHEN c.usuario1_id = :usuario1
                THEN c.usuario2_id
                ELSE c.usuario1_id
            END
        WHERE c.id = :conversa
        AND (
            c.usuario1_id = :usuario2
            OR c.usuario2_id = :usuario3
        )";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':usuario1' => $usuarioId,
    ':conversa' => $conversaId,
    ':usuario2' => $usuarioId,
    ':usuario3' => $usuarioId
]);

$conversa = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conversa) {
    header('Location: minhas_conversas.php');
    exit;
}


// ======================================================
// 7. IDENTIFICAR DESTINATÁRIO
// ======================================================

$destinatarioId = (
    (int) $conversa['usuario1_id'] === $usuarioId
)
    ? (int) $conversa['usuario2_id']
    : (int) $conversa['usuario1_id'];


// ======================================================
// 8. BUSCAR DADOS DA NEGOCIAÇÃO
// ======================================================

$compra = null;
$troca = null;


// DADOS DA COMPRA

if ($conversa['tipo'] === 'compra') {

    $sql = "SELECT
                sc.id,
                sc.comprador_id,
                sc.vendedor_id,
                sc.status,
                sc.compra_concluida,
                sc.data_conclusao,
                p.nome AS produto_nome,
                comprador.nome AS comprador_nome,
                vendedor.nome AS vendedor_nome
            FROM solicitacoes_compra sc

            INNER JOIN produtos p
                ON p.id = sc.produto_id

            INNER JOIN usuarios comprador
                ON comprador.id = sc.comprador_id

            INNER JOIN usuarios vendedor
                ON vendedor.id = sc.vendedor_id

            WHERE sc.id = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conversa['solicitacao_id']]);

    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
}


// DADOS DA TROCA

if ($conversa['tipo'] === 'troca') {

    $sql = "SELECT
                pr.id,
                pr.usuario_remetente_id,
                pr.usuario_destinatario_id,
                pr.produto_oferecido_id,
                pr.produto_desejado_id,
                pr.status,
                pr.mensagem AS mensagem_proposta,

                oferecido.nome AS produto_oferecido,
                desejado.nome AS produto_desejado,

                remetente.nome AS nome_remetente,
                destinatario.nome AS nome_destinatario

            FROM propostas pr

            INNER JOIN produtos oferecido
                ON oferecido.id = pr.produto_oferecido_id

            INNER JOIN produtos desejado
                ON desejado.id = pr.produto_desejado_id

            INNER JOIN usuarios remetente
                ON remetente.id = pr.usuario_remetente_id

            INNER JOIN usuarios destinatario
                ON destinatario.id = pr.usuario_destinatario_id

            WHERE pr.id = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conversa['solicitacao_id']]);

    $troca = $stmt->fetch(PDO::FETCH_ASSOC);
}


// ======================================================
// 9. PROCESSAR AÇÕES
// ======================================================

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        exit('Solicitação inválida. Atualize a página.');
    }

    $acao = $_POST['acao'] ?? 'mensagem';


    // FINALIZAR COMPRA

    if ($acao === 'finalizar_compra') {

        if (
            !$compra ||
            (int) $compra['comprador_id'] !== $usuarioId ||
            $compra['status'] !== 'aceita' ||
            (int) $compra['compra_concluida'] === 1
        ) {
            exit('Não foi possível finalizar esta compra.');
        }

        try {

            $sql = "UPDATE solicitacoes_compra
                    SET compra_concluida = 1,
                        data_conclusao = NOW()
                    WHERE id = ?
                    AND comprador_id = ?
                    AND status = 'aceita'
                    AND compra_concluida = 0";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $compra['id'],
                $usuarioId
            ]);

            if ($stmt->rowCount() !== 1) {
                throw new Exception('Compra não encontrada ou já concluída.');
            }

            header(
                'Location: chat.php?id=' . $conversaId . '&compra=concluida'
            );
            exit;

        } catch (Throwable $ex) {

            error_log($ex->getMessage());

            $erro = 'Não foi possível finalizar a compra.';
        }

    } else {

        // ENVIAR MENSAGEM

        $mensagem = trim($_POST['mensagem'] ?? '');

        if ($mensagem === '') {

            $erro = 'Digite uma mensagem antes de enviar.';

        } elseif (strlen($mensagem) > 5000) {

            $erro = 'A mensagem deve ter no máximo 5000 bytes.';

        } else {

            try {

                $sql = "INSERT INTO mensagens (
                            conversa_id,
                            remetente_id,
                            destinatario_id,
                            mensagem,
                            lida
                        ) VALUES (?, ?, ?, ?, 0)";

                $stmt = $pdo->prepare($sql);

                $stmt->execute([
                    $conversaId,
                    $usuarioId,
                    $destinatarioId,
                    $mensagem
                ]);

                header('Location: chat.php?id=' . $conversaId);
                exit;

            } catch (Throwable $ex) {

                error_log($ex->getMessage());

                $erro = 'Não foi possível enviar a mensagem. Tente novamente.';
            }
        }
    }
}


// ======================================================
// 10. ATUALIZAR DADOS DA COMPRA
// ======================================================

if ($conversa['tipo'] === 'compra') {

    $sql = "SELECT
                sc.id,
                sc.comprador_id,
                sc.vendedor_id,
                sc.status,
                sc.compra_concluida,
                sc.data_conclusao,
                p.nome AS produto_nome,
                comprador.nome AS comprador_nome,
                vendedor.nome AS vendedor_nome
            FROM solicitacoes_compra sc

            INNER JOIN produtos p
                ON p.id = sc.produto_id

            INNER JOIN usuarios comprador
                ON comprador.id = sc.comprador_id

            INNER JOIN usuarios vendedor
                ON vendedor.id = sc.vendedor_id

            WHERE sc.id = ?
            LIMIT 1";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$conversa['solicitacao_id']]);

    $compra = $stmt->fetch(PDO::FETCH_ASSOC);
}


// ======================================================
// 11. MARCAR MENSAGENS COMO LIDAS
// ======================================================

$sql = "UPDATE mensagens
        SET lida = 1
        WHERE conversa_id = ?
        AND destinatario_id = ?";

$stmt = $pdo->prepare($sql);
$stmt->execute([$conversaId, $usuarioId]);


// ======================================================
// 12. BUSCAR MENSAGENS
// ======================================================

$sql = "SELECT
            m.id,
            m.remetente_id,
            m.mensagem,
            m.enviado_em,
            u.nome AS nome_remetente
        FROM mensagens m
        INNER JOIN usuarios u
            ON u.id = m.remetente_id
        WHERE m.conversa_id = ?
        ORDER BY m.enviado_em ASC, m.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute([$conversaId]);

$mensagens = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 13. VERIFICAR PERMISSÃO PARA FINALIZAR
// ======================================================

$podeFinalizarCompra =
    $compra &&
    (int) $compra['comprador_id'] === $usuarioId &&
    $compra['status'] === 'aceita' &&
    (int) $compra['compra_concluida'] === 0;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Conversa - Trocafy</title>

    <link rel="stylesheet" href="/asset/style.css">

</head>

<body>

<?php require_once __DIR__ . '/includes/cabecalho.php'; ?>

<main class="pagina-chat">

    <div class="chat-cabecalho">

        <a href="minhas_conversas.php" class="chat-voltar">
            ← Voltar
        </a>

        <h1><?= e($conversa['outro_usuario']) ?></h1>

        <span>
            <?= $conversa['tipo'] === 'troca'
                ? '🔄 CONVERSA SOBRE TROCA'
                : '🛍️ CONVERSA SOBRE COMPRA' ?>
        </span>

    </div>


    <!-- DETALHES DA COMPRA -->

    <?php if ($compra): ?>

        <div class="detalhes-negociacao detalhes-compra">

            <h2>🛍️ DETALHES DA COMPRA</h2>

            <p>
                <strong>Produto desejado:</strong>
                <?= e($compra['produto_nome']) ?>
            </p>

            <p>
                <strong>Comprador:</strong>
                <?= e($compra['comprador_nome']) ?>
            </p>

            <p>
                <strong>Vendedor:</strong>
                <?= e($compra['vendedor_nome']) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= e(ucfirst($compra['status'])) ?>
            </p>

        </div>

    <?php endif; ?>


    <!-- DETALHES DA TROCA -->

    <?php if ($troca): ?>

        <div class="detalhes-negociacao detalhes-troca">

            <h2>🔄 DETALHES DA TROCA</h2>

            <p>
                <strong>Produto oferecido:</strong>
                <?= e($troca['produto_oferecido']) ?>
            </p>

            <p>
                <strong>Produto desejado:</strong>
                <?= e($troca['produto_desejado']) ?>
            </p>

            <p>
                <strong>Quem propôs a troca:</strong>
                <?= e($troca['nome_remetente']) ?>
            </p>

            <p>
                <strong>Quem recebeu a proposta:</strong>
                <?= e($troca['nome_destinatario']) ?>
            </p>

            <p>
                <strong>Mensagem da proposta:</strong><br>
                <?= nl2br(e($troca['mensagem_proposta'] ?? 'Nenhuma mensagem enviada.')) ?>
            </p>

            <p>
                <strong>Status:</strong>
                <?= e(ucfirst($troca['status'])) ?>
            </p>

        </div>

    <?php endif; ?>


    <!-- AVISO DE CONCLUSÃO -->

    <?php if (isset($_GET['compra']) && $_GET['compra'] === 'concluida'): ?>

        <div class="alerta-sucesso">
            Compra finalizada com sucesso!
        </div>

    <?php endif; ?>


    <!-- FINALIZAÇÃO DA COMPRA -->

    <?php if ($compra && (int) $compra['compra_concluida'] === 1): ?>

        <div class="alerta-sucesso">

            <strong>Compra concluída!</strong>

            <p>
                Esta compra foi finalizada com sucesso.

                <?php if (!empty($compra['data_conclusao'])): ?>

                    Data:
                    <?= date(
                        'd/m/Y H:i',
                        strtotime($compra['data_conclusao'])
                    ) ?>

                <?php endif; ?>

            </p>

        </div>

    <?php elseif ($podeFinalizarCompra): ?>

        <div class="finalizar-compra-area">

            <h3>Finalizar compra</h3>

            <p>
                Já recebeu o produto? Confirme a conclusão da compra.
            </p>

            <form method="POST">

                <input type="hidden" name="acao" value="finalizar_compra">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <button type="submit" class="btn-aceitar">
                    Finalizar compra
                </button>

            </form>

        </div>

    <?php elseif ($compra && $compra['status'] === 'aceita'): ?>

        <div class="finalizar-compra-area">
            Aguardando o comprador confirmar a conclusão da compra.
        </div>

    <?php endif; ?>


    <!-- MENSAGENS DO CHAT -->

    <div class="chat-mensagens">

        <?php if (empty($mensagens)): ?>

            <p class="chat-sem-mensagens">
                Ainda não existem mensagens. Envie a primeira!
            </p>

        <?php else: ?>

            <?php foreach ($mensagens as $msg): ?>

                <?php
                $minhaMensagem =
                    (int) $msg['remetente_id'] === $usuarioId;
                ?>

                <div class="mensagem <?= $minhaMensagem
                    ? 'mensagem-minha'
                    : 'mensagem-outro' ?>">

                    <strong>
                        <?= $minhaMensagem
                            ? 'Você'
                            : e($msg['nome_remetente']) ?>
                    </strong>

                    <p><?= nl2br(e($msg['mensagem'])) ?></p>

                    <small>
                        <?= date(
                            'd/m/Y H:i',
                            strtotime($msg['enviado_em'])
                        ) ?>
                    </small>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>


    <!-- FORMULÁRIO DE MENSAGEM -->

    <form method="POST" class="chat-formulario">

        <input type="hidden" name="acao" value="mensagem">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= e($_SESSION['csrf_token']) ?>"
        >

        <?php if ($erro !== ''): ?>

            <p class="chat-erro">
                <?= e($erro) ?>
            </p>

        <?php endif; ?>

        <textarea
            name="mensagem"
            placeholder="Digite sua mensagem..."
            maxlength="5000"
            required
        ></textarea>

        <button type="submit">
            Enviar mensagem
        </button>

    </form>

</main>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
