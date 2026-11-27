
<?php

// Dados de acesso ao banco de dados
$host = 'localhost';
$dbname = 'trocafy';
$dbusuario = '';
$dbsenha = '';

try {

    // Cria a conexão com o banco de dados usando PDO
    $pdo = new PDO(
        "mysql:host=$host;dbname=$dbname;charset=utf8mb4",
        $dbusuario,
        $dbsenha,
        [

            // Faz o PDO apresentar exceções quando ocorrer um erro
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,

            // Define que os resultados serão retornados como arrays associativos
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,

            // Utiliza consultas preparadas nativas do MySQL
            PDO::ATTR_EMULATE_PREPARES => false

        ]
    );

} catch (PDOException $erro) {

    // Registra o erro completo no log do PHP
    error_log($erro->getMessage());

    // Mostra uma mensagem simples ao usuário
    http_response_code(500);
    exit('Não foi possível conectar ao banco de dados.');

}

// Limpa as variáveis que não serão mais utilizadas
unset($host, $dbname, $dbusuario, $dbsenha);
