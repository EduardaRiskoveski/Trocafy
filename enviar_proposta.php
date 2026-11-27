
<?php

// ======================================================
// 1. INICIAR A SESSÃO
// ======================================================

// Inicia a sessão somente se ainda não estiver ativa
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}


// ======================================================
// 2. VERIFICAR SE O USUÁRIO ESTÁ LOGADO
// ======================================================

// Somente usuários conectados podem enviar propostas
if (!isset($_SESSION['usuario_id'])) {

    header('Location: login.php');
    exit;

}

// Recupera o ID do usuário conectado
$usuarioId = (int) $_SESSION['usuario_id'];


// ======================================================
// 3. CONEXÃO COM O BANCO DE DADOS
// ======================================================

// Importa a conexão PDO
require_once __DIR__ . '/config/conexao.php';


// ======================================================
// 4. VERIFICAR O MÉTODO DA REQUISIÇÃO
// ======================================================

// Permite somente o envio pelo método POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    header('Location: index.php');
    exit;

}


// ======================================================
// 5. RECEBER OS DADOS DO FORMULÁRIO
// ======================================================

// Recebe o ID do produto que o usuário está oferecendo
$produtoOferecidoId = filter_input(
    INPUT_POST,
    'produto_oferecido_id',
    FILTER_VALIDATE_INT
);


// Recebe o ID do produto que o usuário deseja receber
$produtoDesejadoId = filter_input(
    INPUT_POST,
    'produto_desejado_id',
    FILTER_VALIDATE_INT
);


// Recebe a mensagem escrita pelo usuário
$mensagem = trim($_POST['mensagem'] ?? '');


// Recebe o token CSRF
$token = $_POST['csrf_token'] ?? '';


// ======================================================
// 6. VALIDAR O TOKEN CSRF
// ======================================================

// Verifica se o token recebido corresponde ao da sessão
if (
    !is_string($token) ||
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $token)
) {

    http_response_code(403);

    exit('Solicitação inválida. Atualize a página.');

}


// ======================================================
// 7. VALIDAR OS DADOS RECEBIDOS
// ======================================================

// Verifica se os IDs são válidos e diferentes
if (
    !$produtoOferecidoId ||
    !$produtoDesejadoId ||
    $produtoOferecidoId === $produtoDesejadoId ||
    strlen($mensagem) > 1000
) {

    exit('Dados da proposta inválidos.');

}


// ======================================================
// 8. INICIAR A TRANSAÇÃO
// ======================================================

// A transação garante que a operação seja concluída
// corretamente ou desfeita em caso de erro

try {

    $pdo->beginTransaction();


    // ==================================================
    // 9. VERIFICAR O PRODUTO OFERECIDO
    // ==================================================

    // Busca o produto que pertence ao usuário conectado
    // e verifica se ainda está disponível

    $stmt = $pdo->prepare("
        SELECT id, usuario_id
        FROM produtos
        WHERE id = ?
        AND usuario_id = ?
        AND status = 'disponivel'
        FOR UPDATE
    ");

    $stmt->execute([
        $produtoOferecidoId,
        $usuarioId
    ]);

    $produtoOferecido = $stmt->fetch();


    // Se o produto não for válido, interrompe a operação
    if (!$produtoOferecido) {

        throw new Exception('Produto oferecido inválido.');

    }


    // ==================================================
    // 10. VERIFICAR O PRODUTO DESEJADO
    // ==================================================

    // Busca o produto que o usuário deseja receber
    // e verifica se continua disponível

    $stmt = $pdo->prepare("
        SELECT id, usuario_id
        FROM produtos
        WHERE id = ?
        AND status = 'disponivel'
        FOR UPDATE
    ");

    $stmt->execute([$produtoDesejadoId]);

    $produtoDesejado = $stmt->fetch();


    // Se o produto não estiver disponível, interrompe
    if (!$produtoDesejado) {

        throw new Exception('Produto desejado indisponível.');

    }


    // ==================================================
    // 11. IDENTIFICAR O DESTINATÁRIO
    // ==================================================

    // Recupera automaticamente o proprietário do anúncio
    $destinatarioId = (int) $produtoDesejado['usuario_id'];


    // Impede que o usuário envie proposta para si mesmo
    if ($destinatarioId === $usuarioId) {

        throw new Exception(
            'Você não pode propor troca para si mesmo.'
        );

    }


    // ==================================================
    // 12. CADASTRAR A PROPOSTA NO BANCO
    // ==================================================

    // Registra os produtos, os usuários, a mensagem
    // e o status inicial da proposta

    $stmt = $pdo->prepare("
        INSERT INTO propostas (
            produto_oferecido_id,
            produto_desejado_id,
            usuario_remetente_id,
            usuario_destinatario_id,
            mensagem,
            status
        ) VALUES (?, ?, ?, ?, ?, 'pendente')
    ");

    $stmt->execute([

        $produtoOferecidoId,
        $produtoDesejadoId,
        $usuarioId,
        $destinatarioId,
        $mensagem !== '' ? $mensagem : null

    ]);


    // ==================================================
    // 13. CONFIRMAR A TRANSAÇÃO
    // ==================================================

    // Salva definitivamente a proposta no banco
    $pdo->commit();


    // Redireciona para os detalhes do produto
    // informando que a proposta foi enviada

    header(
        'Location: detalhes.php?id=' .
        $produtoDesejadoId .
        '&proposta=sucesso'
    );

    exit;


} catch (Throwable $erro) {


    // ==================================================
    // 14. TRATAR ERROS
    // ==================================================

    // Desfaz a operação se alguma etapa falhar
    if ($pdo->inTransaction()) {

        $pdo->rollBack();

    }


    // Registra o erro no log do PHP
    error_log($erro->getMessage());


    // Retorna para a página do produto
    header(
        'Location: detalhes.php?id=' .
        (int) $produtoDesejadoId .
        '&proposta=erro'
    );

    exit;

}

