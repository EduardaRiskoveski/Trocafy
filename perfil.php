<?php

session_start();

// Verifica se o usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}

// Importa a conexão com o banco de dados
require_once __DIR__ . '/config/conexao.php';

// Importa as funções auxiliares do projeto
require_once __DIR__ . '/includes/funcoes.php';


// Pega o ID do usuário que está conectado
$id = $_SESSION['usuario_id'] ?? null;

// Verifica se existe um usuário conectado
if (!$id) {

    // Se não estiver logado, redireciona para o login
    header('Location: login.php');
    exit;
}

// BUSCAR DADOS DO USUÁRIO NO BANCO

// Prepara uma consulta para buscar os dados do usuário
$stmt = $pdo->prepare("
    SELECT id, nome, email, foto, criado_em
    FROM usuarios
    WHERE id = ?
");

// Executa a consulta usando o ID do usuário
$stmt->execute([$id]);

// Guarda os dados encontrados na variável $usuario
$usuario = $stmt->fetch();

// Verifica se o usuário foi encontrado
if (!$usuario) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// BUSCAR OS ANÚNCIOS DO USUÁRIO

// Prepara uma consulta para buscar os produtos
$stmt = $pdo->prepare("
    SELECT id, nome, descricao, imagem, estado, criado_em
    FROM produtos
    WHERE usuario_id = ?
    ORDER BY criado_em DESC
");

// Executa a consulta usando o ID do usuário
$stmt->execute([$id]);

// Guarda todos os produtos encontrados em uma lista
$produtos = $stmt->fetchAll();

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <!-- Define a codificação de caracteres -->
    <meta charset="UTF-8">

    <!-- Adapta o site para celulares e computadores -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Título que aparece na aba do navegador -->
    <title>Meu perfil | Trocafy</title>

    <!-- Conecta o arquivo CSS ao HTML -->
    <link rel="stylesheet" href="asset/style.css">

</head>

<body>

<!-- Inclui o cabeçalho do site -->
<?php include __DIR__ . '/includes/cabecalho.php'; ?>

<!-- Área principal da página de perfil -->
<main class="perfil">

    <!-- INFORMAÇÕES DO PERFIL -->
    <section class="perfil-info">

        <!-- Área da foto do usuário -->
        <div class="perfil-foto">

            <!-- Verifica se o usuário possui uma foto -->
            <?php if (!empty($usuario['foto'])): ?>

                <!-- Mostra a foto cadastrada -->
                <img src="<?= e($usuario['foto']) ?>" alt="Foto do perfil">

            <?php else: ?>

                <!-- Mostra um avatar padrão caso não tenha foto -->
                <div class="avatar-padrao">👤</div>

            <?php endif; ?>

        </div>

        <!-- Informações pessoais do usuário -->
        <div class="perfil-dados">

            <span class="perfil-legenda">MEU PERFIL</span>

            <!-- Mostra o nome do usuário -->
            <h1><?= e($usuario['nome']) ?></h1>

            <!-- Mostra o e-mail do usuário -->
            <p><?= e($usuario['email']) ?></p>

            <!-- Mostra a data em que a conta foi criada -->
            <small>
                Membro desde
                <?= date('d/m/Y', strtotime($usuario['criado_em'])) ?>
            </small>

            <!-- Botões de ação do perfil -->
            <div class="perfil-acoes">

                <!-- Leva para a página de edição -->
                <a href="editar_perfil.php" class="botao-editar">
                    Editar perfil
                </a>

                <!-- Leva para a página de anúncios -->
                <a href="anunciar.php" class="botao-anunciar">
                    + Novo anúncio
                </a>

            </div>

        </div>

    </section>

    <!-- SEÇÃO DOS ANÚNCIOS -->
    <section class="perfil-produtos">

        <!-- Título e quantidade de anúncios -->
        <div class="titulo-anuncios">

            <div>
                <span>SEUS PRODUTOS</span>
                <h2>Meus anúncios</h2>
            </div>

            <!-- Conta quantos produtos o usuário possui -->
            <p><?= count($produtos) ?> anúncio(s)</p>

        </div>

        <!-- Lista que recebe os cartões dos produtos -->
        <div class="lista-produtos">

            <!-- Verifica se existem produtos cadastrados -->
            <?php if (count($produtos) > 0): ?>

                <!-- Percorre todos os produtos encontrados -->
                <?php foreach ($produtos as $produto): ?>

                    <!-- Cartão de cada produto -->
                    <article class="produto">

                        <!-- Área da imagem do produto -->
                        <div class="produto-imagem">

                            <!-- Verifica se existe uma imagem -->
                            <?php if (!empty($produto['imagem'])): ?>

                                <!-- Mostra a imagem do produto -->
                                <img
                                    src="<?= e($produto['imagem']) ?>"
                                    alt="<?= e($produto['nome']) ?>"
                                >

                            <?php else: ?>

                                <!-- Imagem padrão caso não tenha foto -->
                                <div class="imagem-padrao">📦</div>

                            <?php endif; ?>

                        </div>

                        <!-- Informações do produto -->
                        <div class="produto-info">

                            <!-- Nome do produto -->
                            <h3><?= e($produto['nome']) ?></h3>

                            <!-- Descrição do produto -->
                            <p><?= e($produto['descricao']) ?></p>

                            <!-- Estado de conservação -->
                            <span class="produto-estado">
                                <?= e($produto['estado'] ?? 'Estado não informado') ?>
                            </span>

                            <!-- Link para ver os detalhes do produto -->
                            <a href="detalhes.php?id=<?= (int)$produto['id'] ?>">
                                Ver produto →
                            </a>

                        </div>

                    </article>

                <!-- Finaliza a repetição dos produtos -->
                <?php endforeach; ?>

            <?php else: ?>

                <!-- Aparece quando o usuário não possui anúncios -->
                <div class="sem-produtos">

                    <div class="icone-vazio">📦</div>

                    <h3>Nenhum anúncio por enquanto</h3>

                    <p>
                        Seus produtos aparecerão aqui quando você publicar
                        seu primeiro anúncio.
                    </p>

                    <!-- Botão para criar o primeiro anúncio -->
                    <a href="anunciar.php">
                        Anunciar meu primeiro produto
                    </a>

                </div>

            <?php endif; ?>

        </div>

    </section>

</main>

<!-- Inclui o rodapé do site -->
<?php include __DIR__ . '/includes/rodape.php'; ?>

</body>
</html>
