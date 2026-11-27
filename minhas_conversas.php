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
// 4. CRIAR TOKEN DE SEGURANÇA
// ======================================================

if (empty($_SESSION['token_chat'])) {
    $_SESSION['token_chat'] = bin2hex(random_bytes(32));
}

$token = $_SESSION['token_chat'];


// ======================================================
// 5. APAGAR CONVERSA SOMENTE DA CONTA ATUAL
// ======================================================

$mensagemErro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['acao'] ?? '') === 'apagar_conversa') {

    $tokenEnviado = $_POST['token'] ?? '';

    if (!hash_equals($token, $tokenEnviado)) {

        $mensagemErro = 'Solicitação inválida. Atualize a página e tente novamente.';

    } else {

        $conversaId = (int) ($_POST['conversa_id'] ?? 0);

        if ($conversaId > 0) {

            $sqlApagar = "UPDATE conversas
                          SET
                            oculto_usuario1 = CASE
                                WHEN usuario1_id = :usuario1 THEN 1
                                ELSE oculto_usuario1
                            END,
                            oculto_usuario2 = CASE
                                WHEN usuario2_id = :usuario2 THEN 1
                                ELSE oculto_usuario2
                            END
                          WHERE id = :conversa
                          AND (
                              usuario1_id = :usuario3
                              OR usuario2_id = :usuario4
                          )";

            $stmtApagar = $pdo->prepare($sqlApagar);

            $stmtApagar->execute([
                ':usuario1' => $usuarioId,
                ':usuario2' => $usuarioId,
                ':conversa' => $conversaId,
                ':usuario3' => $usuarioId,
                ':usuario4' => $usuarioId
            ]);

            header('Location: minhas_conversas.php');
            exit;
        }
    }
}


// ======================================================
// 6. BUSCAR CONVERSAS VISÍVEIS PARA O USUÁRIO
// ======================================================

$sql = "SELECT
            c.id,
            c.tipo,
            c.solicitacao_id,
            c.criado_em,

            CASE
                WHEN c.usuario1_id = :usuario1
                THEN u2.nome
                ELSE u1.nome
            END AS outro_usuario,

            CASE
                WHEN c.tipo = 'compra' THEN (
                    SELECT p.nome
                    FROM solicitacoes_compra sc
                    INNER JOIN produtos p ON p.id = sc.produto_id
                    WHERE sc.id = c.solicitacao_id
                    LIMIT 1
                )
                WHEN c.tipo = 'troca' THEN (
                    SELECT p.nome
                    FROM propostas pr
                    INNER JOIN produtos p ON p.id = pr.produto_desejado_id
                    WHERE pr.id = c.solicitacao_id
                    LIMIT 1
                )
            END AS produto_nome,

            (
                SELECT m.mensagem
                FROM mensagens m
                WHERE m.conversa_id = c.id
                ORDER BY m.enviado_em DESC, m.id DESC
                LIMIT 1
            ) AS ultima_mensagem,

            (
                SELECT COUNT(*)
                FROM mensagens m
                WHERE m.conversa_id = c.id
                  AND m.destinatario_id = :usuario4
                  AND m.lida = 0
            ) AS mensagens_nao_lidas

        FROM conversas c

        INNER JOIN usuarios u1
            ON u1.id = c.usuario1_id

        INNER JOIN usuarios u2
            ON u2.id = c.usuario2_id

        WHERE
            (
                c.usuario1_id = :usuario2
                AND c.oculto_usuario1 = 0
            )
            OR
            (
                c.usuario2_id = :usuario3
                AND c.oculto_usuario2 = 0
            )

        ORDER BY c.criado_em DESC";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    ':usuario1' => $usuarioId,
    ':usuario2' => $usuarioId,
    ':usuario3' => $usuarioId,
    ':usuario4' => $usuarioId
]);

$conversas = $stmt->fetchAll(PDO::FETCH_ASSOC);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Minhas conversas - Trocafy</title>

    <link rel="stylesheet" href="/asset/style.css">
</head>

<body>

<?php require_once __DIR__ . '/includes/cabecalho.php'; ?>

<main class="pagina-conversas">

    <h1>Minhas conversas</h1>

    <p class="conversas-descricao">
        Acompanhe suas conversas sobre compras e trocas.
    </p>

    <?php if (!empty($mensagemErro)): ?>

        <p class="mensagem-erro">
            <?= e($mensagemErro) ?>
        </p>

    <?php endif; ?>

    <?php if (empty($conversas)): ?>

        <div class="conversas-vazio">
            <p>Você ainda não possui conversas.</p>
        </div>

    <?php else: ?>

        <div class="lista-conversas">

            <?php foreach ($conversas as $conversa): ?>

                <div class="conversa-card">

                    <div class="conversa-informacoes">

                        <h2>
                            <?= e($conversa['outro_usuario']) ?>
                        </h2>

                        <span class="conversa-tipo">
                            <?= $conversa['tipo'] === 'troca'
                                ? '🔄 Conversa sobre troca'
                                : '🛍️ Conversa sobre compra' ?>
                        </span>

                        <?php if (!empty($conversa['produto_nome'])): ?>

                            <p>
                                <strong>Produto:</strong>
                                <?= e($conversa['produto_nome']) ?>
                            </p>

                        <?php endif; ?>

                        <p class="conversa-ultima-mensagem">
                            <?= e(
                                $conversa['ultima_mensagem']
                                ?? 'Nenhuma mensagem enviada ainda.'
                            ) ?>
                        </p>

                        <?php if ((int) $conversa['mensagens_nao_lidas'] > 0): ?>

                            <span class="mensagens-nao-lidas">
                                <?= (int) $conversa['mensagens_nao_lidas'] ?>
                                mensagens não lidas
                            </span>

                        <?php endif; ?>

                    </div>

                    <div class="acoes-conversa">

                        <a
                            href="/chat.php?id=<?= (int) $conversa['id'] ?>"
                            class="botao-abrir-chat"
                        >
                            Abrir conversa
                        </a>

                        <form
                            method="POST"
                            class="form-apagar-chat"
                            onsubmit="return confirm('Tem certeza que deseja apagar esta conversa somente da sua conta?');"
                        >

                            <input
                                type="hidden"
                                name="acao"
                                value="apagar_conversa"
                            >

                            <input
                                type="hidden"
                                name="conversa_id"
                                value="<?= (int) $conversa['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="token"
                                value="<?= e($token) ?>"
                            >

                            <button
                                type="submit"
                                class="botao-apagar-chat"
                            >
                                🗑️ Apagar conversa
                            </button>

                        </form>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</main>

<?php require_once __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>