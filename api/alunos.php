<?php
/**
 * api/alunos.php — API REST para gerenciar alunas/usuárias.
 *
 * GET    /api/alunos.php          — Lista todas as alunas (admin/treinadora)
 * GET    /api/alunos.php?id=N     — Detalhes de uma aluna
 * POST   /api/alunos.php          — Criar nova aluna (admin)
 * PUT    /api/alunos.php?id=N     — Atualizar aluna (admin)
 * DELETE /api/alunos.php?id=N     — Desativar aluna (admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET — Listar ou detalhar ────────────────────────────────────────────
if ($metodo === 'GET') {
    apiExigirLogin(['admin', 'treinadora']);

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT id, nome, login, email, whatsapp, data_nasc, cpf, nivel, ativo, criado_em FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $aluna = $stmt->fetch();
        if (!$aluna) {
            apiErro('Aluna não encontrada.', 404);
        }
        apiResposta($aluna);
    }

    // Listar todas
    $nivel = $_GET['nivel'] ?? null;
    $sql = "SELECT id, nome, login, email, whatsapp, nivel, ativo, criado_em FROM usuarios";
    $params = [];

    if ($nivel !== null && in_array($nivel, ['admin', 'treinadora', 'aluna'], true)) {
        $sql .= " WHERE nivel = :nivel";
        $params[':nivel'] = $nivel;
    }

    $sql .= " ORDER BY nome";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiResposta(['usuarios' => $stmt->fetchAll()]);
}

// ── POST — Criar ────────────────────────────────────────────────────────
if ($metodo === 'POST') {
    apiExigirLogin('admin');

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $nome  = trim($input['nome'] ?? '');
    $login = trim($input['login'] ?? '');
    $email = trim($input['email'] ?? '');
    $senha = $input['senha'] ?? '';
    $nivel = $input['nivel'] ?? 'aluna';
    $cpf   = trim($input['cpf'] ?? '');
    $whatsapp = trim($input['whatsapp'] ?? '');

    if ($nome === '' || $login === '' || $email === '' || $senha === '') {
        apiErro('Campos obrigatórios: nome, login, email, senha.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        apiErro('E-mail inválido.');
    }
    if (!in_array($nivel, ['admin', 'treinadora', 'aluna'], true)) {
        apiErro('Nível inválido. Use: admin, treinadora ou aluna.');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO usuarios (nome, login, email, senha, cpf, whatsapp, nivel)
            VALUES (:nome, :login, :email, :senha, :cpf, :whatsapp, :nivel)
        ");
        $stmt->execute([
            ':nome'     => $nome,
            ':login'    => $login,
            ':email'    => $email,
            ':senha'    => password_hash($senha, PASSWORD_DEFAULT),
            ':cpf'      => $cpf ?: null,
            ':whatsapp' => $whatsapp ?: null,
            ':nivel'    => $nivel,
        ]);
        apiResposta(['mensagem' => 'Usuária criada com sucesso!', 'id' => (int) $pdo->lastInsertId()], 201);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            apiErro('Login, e-mail ou CPF já cadastrado.', 409);
        }
        apiErro('Erro ao criar usuária.', 500);
    }
}

// ── PUT — Atualizar ─────────────────────────────────────────────────────
if ($metodo === 'PUT') {
    apiExigirLogin('admin');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        apiErro('Informe o ID da usuária (?id=N).');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        apiErro('Nenhum dado enviado.');
    }

    $campos = [];
    $params = [':id' => $id];
    $permitidos = ['nome', 'login', 'email', 'whatsapp', 'nivel', 'cpf', 'ativo'];

    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $input)) {
            if ($campo === 'nivel' && !in_array($input[$campo], ['admin', 'treinadora', 'aluna'], true)) {
                apiErro('Nível inválido.');
            }
            if ($campo === 'email' && !filter_var($input[$campo], FILTER_VALIDATE_EMAIL)) {
                apiErro('E-mail inválido.');
            }
            if ($campo === 'ativo') {
                $input[$campo] = (int) (bool) $input[$campo];
            }
            $campos[] = "$campo = :$campo";
            $params[":$campo"] = $input[$campo];
        }
    }

    // Senha separada (precisa de hash)
    if (!empty($input['senha'])) {
        $campos[] = "senha = :senha";
        $params[':senha'] = password_hash($input['senha'], PASSWORD_DEFAULT);
    }

    if (empty($campos)) {
        apiErro('Nenhum campo válido para atualizar.');
    }

    try {
        $sql = "UPDATE usuarios SET " . implode(', ', $campos) . " WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        apiResposta(['mensagem' => 'Usuária atualizada com sucesso!']);
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            apiErro('Login, e-mail ou CPF já cadastrado.', 409);
        }
        apiErro('Erro ao atualizar usuária.', 500);
    }
}

// ── DELETE — Desativar ──────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    apiExigirLogin('admin');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        apiErro('Informe o ID da usuária (?id=N).');
    }

    $stmt = $pdo->prepare("UPDATE usuarios SET ativo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    apiResposta(['mensagem' => 'Usuária desativada com sucesso!']);
}

apiErro('Método não suportado.', 405);
