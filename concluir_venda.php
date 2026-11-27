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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: propostas.php');
    exit;
}

$propostaId = filter_input(INPUT_POST, 'proposta_id', FILTER_VALIDATE_INT);
$acao       = $_POST['acao'] ?? 'concluir';
$token      = $_POST['csrf_token'] ?? '';

if (
    !is_string($token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token) ||
    !$propostaId ||
    !in_array($acao, ['concluir', 'recusar'], true)
) {
    http_response_code(403);
    exit('Solicitação inválida.');
}

try {
    $pdo->beginTransaction();

    // 1. Verifica se a proposta pertence ao usuário que recebeu a oferta
    $stmt = $pdo->prepare("
        SELECT id, produto_oferecido_id, produto_desejado_id
        FROM propostas
        WHERE id = ?
          AND usuario_destinatario_id = ?
          AND status IN ('pendente', 'aceita')
        FOR UPDATE
    ");
    $stmt->execute([$propostaId, $usuarioId]);
    $proposta = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$proposta) {
        throw new Exception('Proposta não encontrada ou já finalizada.');
    }

    if ($acao === 'recusar') {
        $stmt = $pdo->prepare("UPDATE propostas SET status = 'recusada' WHERE id = ?");
        $stmt->execute([$propostaId]);
        $pdo->commit();

        header('Location: propostas.php?status=recusada');
        exit;
    }

    // 2. Garante que os dois produtos envolvidos ainda estão disponíveis
    $stmtProd = $pdo->prepare("
        SELECT COUNT(*) 
        FROM produtos 
        WHERE id IN (?, ?) AND status = 'disponivel'
        FOR UPDATE
    ");
    $stmtProd->execute([
        $proposta['produto_oferecido_id'],
        $proposta['produto_desejado_id']
    ]);

    if ((int) $stmtProd->fetchColumn() !== 2) {
        throw new Exception('Um dos produtos não está mais disponível.');
    }

    // 3. Conclui a proposta e registra a data
    $stmt = $pdo->prepare("
        UPDATE propostas 
        SET status = 'concluida', data_conclusao = NOW() 
        WHERE id = ?
    ");
    $stmt->execute([$propostaId]);

    // 4. Marca os dois produtos como trocados/vendidos para saírem da vitrine
    $stmt = $pdo->prepare("
        UPDATE produtos 
        SET status = 'trocado' 
        WHERE id IN (?, ?)
    ");
    $stmt->execute([
        $proposta['produto_oferecido_id'],
        $proposta['produto_desejado_id']
    ]);

    // 5. Recusa outras propostas pendentes que envolvam esses mesmos itens
    $stmt = $pdo->prepare("
        UPDATE propostas 
        SET status = 'recusada' 
        WHERE id != ? 
          AND status = 'pendente'
          AND (
              produto_oferecido_id IN (?, ?) OR 
              produto_desejado_id IN (?, ?)
          )
    ");
    $stmt->execute([
        $propostaId,
        $proposta['produto_oferecido_id'],
        $proposta['produto_desejado_id'],
        $proposta['produto_oferecido_id'],
        $proposta['produto_desejado_id']
    ]);

    $pdo->commit();

    header('Location: historico_vendas.php?sucesso=1');
    exit;

} catch (Throwable $erro) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log($erro->getMessage());
    header('Location: propostas.php?erro=1');
    exit;
}