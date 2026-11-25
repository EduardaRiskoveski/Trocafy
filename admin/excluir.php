
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR A PERMISSÃO DO ADMINISTRADOR
// ======================================================

require_once __DIR__ . '/../includes/funcoes.php';

// Impede que usuários comuns excluam produtos
exigirAdmin();


// ======================================================
// 3. VERIFICAR O MÉTODO DO FORMULÁRIO
// ======================================================

// A exclusão só pode acontecer através de POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: produtos.php');
    exit;
}


// ======================================================
// 4. VALIDAR O TOKEN DE SEGURANÇA
// ======================================================

$tokenSessao = $_SESSION['csrf_token'] ?? '';
$tokenFormulario = $_POST['csrf_token'] ?? '';

// Compara os tokens para validar a solicitação
if (
    !is_string($tokenSessao) ||
    !is_string($tokenFormulario) ||
    $tokenSessao === '' ||
    !hash_equals($tokenSessao, $tokenFormulario)
) {

    // Bloqueia a solicitação inválida
    http_response_code(403);
    exit('Solicitação inválida. Atualize a página e tente novamente.');
}


// ======================================================
// 5. RECEBER O ID DO PRODUTO
// ======================================================

// Recebe o identificador enviado pelo formulário
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

// Verifica se o ID é válido
if (!$id || $id <= 0) {
    header('Location: produtos.php?erro=nao_encontrado');
    exit;
}


// ======================================================
// 6. CONECTAR AO BANCO DE DADOS
// ======================================================

require_once __DIR__ . '/../config/conexao.php';


// ======================================================
// 7. EXCLUIR O PRODUTO
// ======================================================

try {

    // Prepara a consulta para excluir somente o produto informado
    $sql = "DELETE FROM produtos WHERE id = ?";

    $stmt = $pdo->prepare($sql);

    // Executa a exclusão com o ID recebido
    $stmt->execute([$id]);


    // Verifica se algum produto foi excluído
    if ($stmt->rowCount() > 0) {

        // Redireciona com mensagem de sucesso
        header('Location: produtos.php?excluido=sucesso');
        exit;

    } else {

        // Informa que o produto não foi encontrado
        header('Location: produtos.php?erro=nao_encontrado');
        exit;

    }

} catch (PDOException $erro) {

    // Registra os detalhes técnicos no log do PHP
    error_log($erro->getMessage());

    // Informa que não foi possível excluir
    header('Location: produtos.php?erro=exclusao');
    exit;

}
