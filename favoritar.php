
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ======================================================
// 2. CONECTAR AO BANCO DE DADOS
// ======================================================

require_once __DIR__ . '/config/conexao.php';

// ======================================================
// 3. VERIFICAR SE O USUÁRIO ESTÁ LOGADO
// ======================================================

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// ======================================================
// 4. RECEBER O ID DO PRODUTO
// ======================================================

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$usuarioId = (int) $_SESSION['usuario_id'];
$produtoId = (int) ($_POST['produto_id'] ?? 0);

if ($produtoId <= 0) {
    header('Location: index.php');
    exit;
}

try {

    // ==================================================
    // 5. VERIFICAR SE O PRODUTO EXISTE E ESTÁ DISPONÍVEL
    // ==================================================

    $sqlProduto = "SELECT id FROM produtos
                WHERE id = :id
                AND status = 'disponivel'";

    $stmtProduto = $pdo->prepare($sqlProduto);
    $stmtProduto->execute([':id' => $produtoId]);

    if (!$stmtProduto->fetch()) {
        header('Location: index.php');
        exit;
    }

    // ==================================================
    // 6. VERIFICAR SE JÁ ESTÁ NOS FAVORITOS
    // ==================================================

    $sql = "SELECT id FROM favoritos
            WHERE usuario_id = :usuario_id
            AND produto_id = :produto_id";

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':produto_id' => $produtoId
    ]);

    $favorito = $stmt->fetch();

    // ==================================================
    // 7. ADICIONAR OU REMOVER DOS FAVORITOS
    // ==================================================

    if ($favorito) {

        // Se já existe, remove
        $sql = "DELETE FROM favoritos
                WHERE usuario_id = :usuario_id
                AND produto_id = :produto_id";

    } else {

        // Se não existe, adiciona
        $sql = "INSERT INTO favoritos
                (usuario_id, produto_id)
                VALUES (:usuario_id, :produto_id)";
    }

    $stmt = $pdo->prepare($sql);

    $stmt->execute([
        ':usuario_id' => $usuarioId,
        ':produto_id' => $produtoId
    ]);

} catch (PDOException $erro) {

    die('Erro ao atualizar favorito: ' . $erro->getMessage());
}

// ======================================================
// 8. VOLTAR PARA A PÁGINA ANTERIOR
// ======================================================

$paginasPermitidas = [
    'index.php',
    'buscar.php',
    'detalhes.php',
    'favoritos.php'
];

$paginaAnterior = basename(
    parse_url($_SERVER['HTTP_REFERER'] ?? '', PHP_URL_PATH) ?? ''
);

if (in_array($paginaAnterior, $paginasPermitidas, true)) {
    header('Location: ' . $paginaAnterior);
} else {
    header('Location: index.php');
}

exit;
