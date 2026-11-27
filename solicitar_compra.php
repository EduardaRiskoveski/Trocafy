
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. CARREGAR A CONEXÃO E AS FUNÇÕES
// ======================================================

require_once __DIR__ . '/config/conexao.php';
require_once __DIR__ . '/includes/funcoes.php';


// ======================================================
// 3. VERIFICAR SE O USUÁRIO ESTÁ LOGADO
// ======================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

$compradorId = (int) $_SESSION['usuario_id'];


// ======================================================
// 4. VERIFICAR SE O FORMULÁRIO FOI ENVIADO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}


// ======================================================
// 5. VALIDAR O TOKEN CSRF
// ======================================================

$token = $_POST['csrf_token'] ?? '';

if (
    !isset($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {
    exit('Solicitação inválida. Atualize a página e tente novamente.');
}


// ======================================================
// 6. RECEBER OS DADOS DO FORMULÁRIO
// ======================================================

$produtoId = filter_input(INPUT_POST, 'produto_id', FILTER_VALIDATE_INT);
$mensagem = trim($_POST['mensagem'] ?? '');

if (!$produtoId || $produtoId <= 0) {
    header('Location: index.php');
    exit;
}


// ======================================================
// 7. BUSCAR O PRODUTO
// ======================================================

$sql = "
    SELECT id, usuario_id, nome, tipo_negociacao, status
    FROM produtos
    WHERE id = :id
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([':id' => $produtoId]);

$produto = $stmt->fetch(PDO::FETCH_ASSOC);


// ======================================================
// 8. VERIFICAR SE O PRODUTO PODE SER COMPRADO
// ======================================================

if (
    !$produto ||
    $produto['status'] !== 'disponivel' ||
    !in_array($produto['tipo_negociacao'], ['venda', 'ambos'], true)
) {
    exit('Este produto não está disponível para compra.');
}


// ======================================================
// 9. IMPEDIR QUE O VENDEDOR COMPRE O PRÓPRIO PRODUTO
// ======================================================

$vendedorId = (int) $produto['usuario_id'];

if ($compradorId === $vendedorId) {
    exit('Você não pode comprar seu próprio produto.');
}


// ======================================================
// 10. VERIFICAR SE JÁ EXISTE UMA SOLICITAÇÃO PENDENTE
// ======================================================

$sql = "
    SELECT id
    FROM solicitacoes_compra
    WHERE produto_id = :produto_id
      AND comprador_id = :comprador_id
      AND status = 'pendente'
    LIMIT 1
";

$stmt = $pdo->prepare($sql);
$stmt->execute([
    ':produto_id' => $produtoId,
    ':comprador_id' => $compradorId
]);

if ($stmt->fetch()) {
    exit('Você já enviou uma solicitação de compra para este produto.');
}


// ======================================================
// 11. REGISTRAR A SOLICITAÇÃO
// ======================================================

$sql = "
    INSERT INTO solicitacoes_compra
    (
        produto_id,
        comprador_id,
        vendedor_id,
        mensagem
    )
    VALUES
    (
        :produto_id,
        :comprador_id,
        :vendedor_id,
        :mensagem
    )
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':produto_id' => $produtoId,
    ':comprador_id' => $compradorId,
    ':vendedor_id' => $vendedorId,
    ':mensagem' => $mensagem
]);


// ======================================================
// 12. REDIRECIONAR APÓS O ENVIO
// ======================================================

header('Location: detalhes.php?id=' . $produtoId . '&compra=sucesso');
exit;

