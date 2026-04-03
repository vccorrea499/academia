<?php
/**
 * conexao.php — Conexão PDO com MySQL para o sistema Guerreiras Thai.
 *
 * Este arquivo é incluído por todos os scripts que precisam de acesso ao
 * banco de dados. A conexão utiliza charset utf8mb4, modo de erro EXCEPTION
 * e desativa emulação de prepared statements para segurança máxima.
 */

$host = 'localhost';
$db   = 'iubsit15_academia';
$user = 'iubsit15_academiuser';
$pass = '@Vanvan123';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Erro ao conectar ao banco de dados.');
}
