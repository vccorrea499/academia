<?php
/**
 * setup.php — Executa o DDL e insere o administrador padrão.
 *
 * Rode este script UMA VEZ para criar as tabelas e o admin:
 *   php setup.php
 *
 * O admin padrão será criado com login "admin" e senha "konex2026"
 * usando password_hash() do PHP (bcrypt).
 */

require_once __DIR__ . '/conexao.php';

// ── 1. Ler e executar o DDL ──────────────────────────────────────
$sqlFile = __DIR__ . '/database/schema.sql';

if (!file_exists($sqlFile)) {
    exit("Arquivo schema.sql não encontrado em: $sqlFile\n");
}

$sql = file_get_contents($sqlFile);

try {
    $pdo->exec($sql);
    echo "✔ Tabelas criadas com sucesso.\n";
} catch (PDOException $e) {
    exit("Erro ao criar tabelas: " . $e->getMessage() . "\n");
}

// ── 2. Inserir o administrador padrão ────────────────────────────
$loginAdmin = 'admin';
$senhaAdmin = password_hash('konex2026', PASSWORD_DEFAULT);

try {
    $stmt = $pdo->prepare(
        "INSERT INTO usuarios (nome, login, email, senha, nivel)
         VALUES (:nome, :login, :email, :senha, 'admin')
         ON DUPLICATE KEY UPDATE nome = VALUES(nome)"
    );
    $stmt->execute([
        ':nome'  => 'Administrador',
        ':login' => $loginAdmin,
        ':email' => 'admin@guerreirasthai.com',
        ':senha' => $senhaAdmin,
    ]);

    echo "✔ Administrador padrão criado (login: admin / senha: konex2026).\n";
} catch (PDOException $e) {
    exit("Erro ao inserir admin: " . $e->getMessage() . "\n");
}

echo "\n✅ Setup concluído com sucesso!\n";
