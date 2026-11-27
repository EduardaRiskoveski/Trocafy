
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

$vendedorId = (int) $_SESSION['usuario_id'];


// ======================================================
// 4. CRIAR TOKEN CSRF
// ======================================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}


// ======================================================
// 5. PROCESSAR FORMULÁRIOS
// ======================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validar CSRF
    $token = $_POST['csrf_token'] ?? '';

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        exit('Solicitação inválida. Atualize a página.');
    }

    // Receber dados
    $solicitacaoId = filter_input(
        INPUT_POST,
        'solicitacao_id',
        FILTER_VALIDATE_INT
    );

    $acao = $_POST['acao'] ?? '';
    $tipo = $_POST['tipo'] ?? '';

    if (
        !$solicitacaoId ||
        !in_array($acao, ['aceita', 'recusada', 'excluir'], true) ||
        !in_array($tipo, ['compra', 'troca'], true)
    ) {
        exit('Dados inválidos.');
    }


    // ==================================================
    // 5.1. EXCLUIR SOLICITAÇÃO SOMENTE DA PRÓPRIA CONTA
    // ==================================================

    if ($acao === 'excluir') {

        try {

            // Verificar se a solicitação pertence ao usuário
            if ($tipo === 'compra') {

                $sql = "
                    SELECT id
                    FROM solicitacoes_compra
                    WHERE id = ?
                      AND vendedor_id = ?
                    LIMIT 1
                ";

            } else {

                $sql = "
                    SELECT id
                    FROM propostas
                    WHERE id = ?
                      AND usuario_destinatario_id = ?
                    LIMIT 1
                ";
            }

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $solicitacaoId,
                $vendedorId
            ]);

            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                exit('Solicitação não encontrada.');
            }


            // Registrar ocultação somente para o usuário logado
            $sql = "
                INSERT IGNORE INTO solicitacoes_ocultas
                (
                    usuario_id,
                    solicitacao_id,
                    tipo
                )
                VALUES (?, ?, ?)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $vendedorId,
                $solicitacaoId,
                $tipo
            ]);


            // Voltar para a página
            header('Location: minhas_solicitacoes.php?excluida=sucesso');
            exit;

        } catch (Throwable $erro) {

            error_log($erro->getMessage());

            exit('Não foi possível excluir a solicitação.');
        }
    }


    // ==================================================
    // 5.2. PROCESSAR ACEITAÇÃO OU RECUSA
    // ==================================================

    try {

        $pdo->beginTransaction();


        // Identificar participantes
        if ($tipo === 'compra') {

            $sql = "
                SELECT comprador_id, vendedor_id
                FROM solicitacoes_compra
                WHERE id = ?
                  AND vendedor_id = ?
                  AND status = 'pendente'
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $solicitacaoId,
                $vendedorId
            ]);

        } else {

            $sql = "
                SELECT
                    usuario_remetente_id,
                    usuario_destinatario_id
                FROM propostas
                WHERE id = ?
                  AND usuario_destinatario_id = ?
                  AND status = 'pendente'
                LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $solicitacaoId,
                $vendedorId
            ]);
        }


        $participantes = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$participantes) {
            throw new Exception(
                'Solicitação não encontrada ou já respondida.'
            );
        }


        // Definir participantes
        if ($tipo === 'compra') {

            $usuarioRemetente = (int) $participantes['comprador_id'];

            $usuarioDestinatario = (int) $participantes['vendedor_id'];

        } else {

            $usuarioRemetente = (int) $participantes['usuario_remetente_id'];

            $usuarioDestinatario = (int) $participantes['usuario_destinatario_id'];
        }


        // Atualizar status
        if ($tipo === 'compra') {

            $sql = "
                UPDATE solicitacoes_compra
                SET status = ?
                WHERE id = ?
                  AND vendedor_id = ?
                  AND status = 'pendente'
            ";

        } else {

            $sql = "
                UPDATE propostas
                SET status = ?
                WHERE id = ?
                  AND usuario_destinatario_id = ?
                  AND status = 'pendente'
            ";
        }

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            $acao,
            $solicitacaoId,
            $vendedorId
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new Exception(
                'Não foi possível atualizar a solicitação.'
            );
        }


        // Criar conversa quando aceita
        if ($acao === 'aceita') {

            $sql = "
                INSERT INTO conversas (
                    solicitacao_id,
                    tipo,
                    usuario1_id,
                    usuario2_id
                )
                VALUES (?, ?, ?, ?)

                ON DUPLICATE KEY UPDATE
                    id = LAST_INSERT_ID(id)
            ";

            $stmt = $pdo->prepare($sql);

            $stmt->execute([
                $solicitacaoId,
                $tipo,
                $usuarioRemetente,
                $usuarioDestinatario
            ]);
        }


        // Finalizar
        $pdo->commit();

        header('Location: minhas_solicitacoes.php?resposta=sucesso');
        exit;


    } catch (Throwable $erro) {

        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        error_log($erro->getMessage());

        exit(
            'Não foi possível responder à solicitação. '
            . 'Verifique os dados e tente novamente.'
        );
    }
}


// ======================================================
// 6. BUSCAR SOLICITAÇÕES DE COMPRA
// ======================================================

$sql = "
    SELECT
        sc.id,
        sc.mensagem,
        sc.status,
        sc.criado_em,
        p.nome AS produto,
        p.imagem,
        p.preco,
        u.nome AS comprador

    FROM solicitacoes_compra sc

    INNER JOIN produtos p
        ON p.id = sc.produto_id

    INNER JOIN usuarios u
        ON u.id = sc.comprador_id

    WHERE sc.vendedor_id = :vendedor_id

    AND NOT EXISTS (
        SELECT 1
        FROM solicitacoes_ocultas so
        WHERE so.usuario_id = :usuario_oculto
          AND so.solicitacao_id = sc.id
          AND so.tipo = 'compra'
    )

    ORDER BY sc.criado_em DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':vendedor_id' => $vendedorId,
    ':usuario_oculto' => $vendedorId
]);

$solicitacoes = $stmt->fetchAll(PDO::FETCH_ASSOC);


// ======================================================
// 7. BUSCAR PROPOSTAS DE TROCA
// ======================================================

$sql = "
    SELECT
        p.id,
        p.mensagem,
        p.status,
        p.criado_em,

        produto_oferecido.nome AS produto_oferecido,
        produto_desejado.nome AS produto_desejado,

        remetente.nome AS interessado

    FROM propostas p

    INNER JOIN produtos produto_oferecido
        ON produto_oferecido.id = p.produto_oferecido_id

    INNER JOIN produtos produto_desejado
        ON produto_desejado.id = p.produto_desejado_id

    INNER JOIN usuarios remetente
        ON remetente.id = p.usuario_remetente_id

    WHERE p.usuario_destinatario_id = :destinatario_id

    AND NOT EXISTS (
        SELECT 1
        FROM solicitacoes_ocultas so
        WHERE so.usuario_id = :usuario_oculto
          AND so.solicitacao_id = p.id
          AND so.tipo = 'troca'
    )

    ORDER BY p.criado_em DESC
";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':destinatario_id' => $vendedorId,
    ':usuario_oculto' => $vendedorId
]);

$propostas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Minhas solicitações | Trocafy</title>

    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

<main class="solicitacoes-container">

    <h1>Minhas solicitações</h1>

    <p class="solicitacoes-descricao">
        Aqui você pode acompanhar os pedidos de compra
        e as propostas de troca recebidas.
    </p>


    <!-- MENSAGENS -->

    <?php if (
        isset($_GET['resposta']) &&
        $_GET['resposta'] === 'sucesso'
    ): ?>

        <div class="mensagem sucesso">
            Solicitação respondida com sucesso!
        </div>

    <?php endif; ?>


    <?php if (
        isset($_GET['excluida']) &&
        $_GET['excluida'] === 'sucesso'
    ): ?>

        <div class="mensagem sucesso">
            Solicitação excluída da sua conta com sucesso!
        </div>

    <?php endif; ?>


    <!-- ==========================================
         SOLICITAÇÕES DE COMPRA
    =========================================== -->

    <h2>Solicitações de compra</h2>

    <?php if (empty($solicitacoes)): ?>

        <div class="solicitacoes-vazias">
            <p>
                Você ainda não recebeu solicitações de compra.
            </p>
        </div>

    <?php else: ?>

        <div class="lista-solicitacoes">

            <?php foreach ($solicitacoes as $solicitacao): ?>

                <article class="card-solicitacao">

                    <h2>
                        <?= e($solicitacao['produto']) ?>
                    </h2>

                    <p>
                        <strong>Comprador:</strong>
                        <?= e($solicitacao['comprador']) ?>
                    </p>

                    <p>
                        <strong>Preço anunciado:</strong>

                        R$ <?= number_format(
                            (float) $solicitacao['preco'],
                            2,
                            ',',
                            '.'
                        ) ?>
                    </p>

                    <p>
                        <strong>Mensagem:</strong>
                    </p>

                    <p class="mensagem-comprador">
                        <?= e($solicitacao['mensagem'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Data:</strong>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime($solicitacao['criado_em'])
                        ) ?>
                    </p>

                    <p>
                        <strong>Status:</strong>

                        <span class="status-solicitacao status-<?= e($solicitacao['status']) ?>">
                            <?= e(ucfirst($solicitacao['status'])) ?>
                        </span>
                    </p>


                    <!-- ACEITAR E RECUSAR -->

                    <?php if ($solicitacao['status'] === 'pendente'): ?>

                        <div class="botoes-solicitacao">

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="solicitacao_id"
                                    value="<?= (int) $solicitacao['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="tipo"
                                    value="compra"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="aceita"
                                >

                                <button type="submit" class="btn-aceitar">
                                    Aceitar
                                </button>

                            </form>


                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="solicitacao_id"
                                    value="<?= (int) $solicitacao['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="tipo"
                                    value="compra"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="recusada"
                                >

                                <button type="submit" class="btn-recusar">
                                    Recusar
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>


                    <!-- EXCLUIR COMPRA -->

                    <form
                        method="POST"
                        onsubmit="return confirm('Deseja excluir esta solicitação somente da sua conta?');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <input
                            type="hidden"
                            name="solicitacao_id"
                            value="<?= (int) $solicitacao['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="tipo"
                            value="compra"
                        >

                        <input
                            type="hidden"
                            name="acao"
                            value="excluir"
                        >

                        <button type="submit" class="btn-recusar">
                            🗑️ Excluir solicitação
                        </button>

                    </form>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- ==========================================
         PROPOSTAS DE TROCA
    =========================================== -->

    <h2>Propostas de troca</h2>

    <?php if (empty($propostas)): ?>

        <div class="solicitacoes-vazias">

            <p>
                Você ainda não recebeu propostas de troca.
            </p>

        </div>

    <?php else: ?>

        <div class="lista-solicitacoes">

            <?php foreach ($propostas as $proposta): ?>

                <article class="card-solicitacao">

                    <h2>
                        Proposta de troca
                    </h2>

                    <p>
                        <strong>Interessado:</strong>

                        <?= e($proposta['interessado']) ?>
                    </p>

                    <p>
                        <strong>Seu produto:</strong>

                        <?= e($proposta['produto_desejado']) ?>
                    </p>

                    <p>
                        <strong>Produto oferecido:</strong>

                        <?= e($proposta['produto_oferecido']) ?>
                    </p>

                    <p>
                        <strong>Mensagem:</strong>
                    </p>

                    <p class="mensagem-comprador">
                        <?= e($proposta['mensagem'] ?? '') ?>
                    </p>

                    <p>
                        <strong>Data:</strong>

                        <?= date(
                            'd/m/Y H:i',
                            strtotime($proposta['criado_em'])
                        ) ?>
                    </p>

                    <p>
                        <strong>Status:</strong>

                        <span class="status-solicitacao status-<?= e($proposta['status']) ?>">
                            <?= e(ucfirst($proposta['status'])) ?>
                        </span>
                    </p>


                    <!-- ACEITAR E RECUSAR -->

                    <?php if ($proposta['status'] === 'pendente'): ?>

                        <div class="botoes-solicitacao">

                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="solicitacao_id"
                                    value="<?= (int) $proposta['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="tipo"
                                    value="troca"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="aceita"
                                >

                                <button type="submit" class="btn-aceitar">
                                    Aceitar
                                </button>

                            </form>


                            <form method="POST">

                                <input
                                    type="hidden"
                                    name="csrf_token"
                                    value="<?= e($_SESSION['csrf_token']) ?>"
                                >

                                <input
                                    type="hidden"
                                    name="solicitacao_id"
                                    value="<?= (int) $proposta['id'] ?>"
                                >

                                <input
                                    type="hidden"
                                    name="tipo"
                                    value="troca"
                                >

                                <input
                                    type="hidden"
                                    name="acao"
                                    value="recusada"
                                >

                                <button type="submit" class="btn-recusar">
                                    Recusar
                                </button>

                            </form>

                        </div>

                    <?php endif; ?>


                    <!-- EXCLUIR TROCA -->

                    <form
                        method="POST"
                        onsubmit="return confirm('Deseja excluir esta proposta somente da sua conta?');"
                    >

                        <input
                            type="hidden"
                            name="csrf_token"
                            value="<?= e($_SESSION['csrf_token']) ?>"
                        >

                        <input
                            type="hidden"
                            name="solicitacao_id"
                            value="<?= (int) $proposta['id'] ?>"
                        >

                        <input
                            type="hidden"
                            name="tipo"
                            value="troca"
                        >

                        <input
                            type="hidden"
                            name="acao"
                            value="excluir"
                        >

                        <button type="submit" class="btn-recusar">
                            🗑️ Excluir solicitação
                        </button>

                    </form>

                </article>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>


    <!-- VOLTAR AO INÍCIO -->

    <a href="index.php" class="btn-voltar">
        Voltar ao início
    </a>

</main>

</body>
</html>
