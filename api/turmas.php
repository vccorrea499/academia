<?php
/**
 * api/turmas.php — API REST para gerenciar turmas.
 *
 * GET    /api/turmas.php          — Lista todas as turmas
 * GET    /api/turmas.php?id=N     — Detalhes de uma turma
 * POST   /api/turmas.php          — Criar nova turma (admin)
 * PUT    /api/turmas.php?id=N     — Atualizar turma (admin)
 * DELETE /api/turmas.php?id=N     — Desativar turma (admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET — Listar ou detalhar ────────────────────────────────────────────
if ($metodo === 'GET') {
    apiExigirLogin();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("SELECT * FROM turmas WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $turma = $stmt->fetch();
        if (!$turma) {
            apiErro('Turma não encontrada.', 404);
        }

        // Contar alunas matriculadas
        $stmtCount = $pdo->prepare("SELECT COUNT(*) FROM matriculas WHERE turma_id = :tid AND status = 'ativa'");
        $stmtCount->execute([':tid' => $id]);
        $turma['alunas_ativas'] = (int) $stmtCount->fetchColumn();

        apiResposta($turma);
    }

    // Listar todas
    $apenasAtivas = isset($_GET['ativas']) && $_GET['ativas'] === '1';
    $sql = "SELECT id, nome, horario, dia_semana, max_alunas, ativo, criado_em FROM turmas";
    if ($apenasAtivas) {
        $sql .= " WHERE ativo = 1";
    }
    $sql .= " ORDER BY nome";

    apiResposta(['turmas' => $pdo->query($sql)->fetchAll()]);
}

// ── POST — Criar ────────────────────────────────────────────────────────
if ($metodo === 'POST') {
    apiExigirLogin('admin');

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $nome    = trim($input['nome'] ?? '');
    $horario = trim($input['horario'] ?? '');
    $dia     = trim($input['dia_semana'] ?? '');
    $max     = (int) ($input['max_alunas'] ?? 20);
    $desc    = trim($input['descricao'] ?? '');

    if ($nome === '') {
        apiErro('Nome da turma é obrigatório.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO turmas (nome, descricao, horario, dia_semana, max_alunas)
        VALUES (:nome, :desc, :horario, :dia, :max)
    ");
    $stmt->execute([
        ':nome'    => $nome,
        ':desc'    => $desc ?: null,
        ':horario' => $horario ?: null,
        ':dia'     => $dia ?: null,
        ':max'     => $max,
    ]);
    apiResposta(['mensagem' => 'Turma criada com sucesso!', 'id' => (int) $pdo->lastInsertId()], 201);
}

// ── PUT — Atualizar ─────────────────────────────────────────────────────
if ($metodo === 'PUT') {
    apiExigirLogin('admin');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        apiErro('Informe o ID da turma (?id=N).');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        apiErro('Nenhum dado enviado.');
    }

    $campos = [];
    $params = [':id' => $id];
    $permitidos = ['nome', 'descricao', 'horario', 'dia_semana', 'max_alunas', 'ativo'];

    foreach ($permitidos as $campo) {
        if (array_key_exists($campo, $input)) {
            if ($campo === 'ativo') {
                $input[$campo] = (int) (bool) $input[$campo];
            }
            if ($campo === 'max_alunas') {
                $input[$campo] = (int) $input[$campo];
            }
            $campos[] = "$campo = :$campo";
            $params[":$campo"] = $input[$campo];
        }
    }

    if (empty($campos)) {
        apiErro('Nenhum campo válido para atualizar.');
    }

    $sql = "UPDATE turmas SET " . implode(', ', $campos) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiResposta(['mensagem' => 'Turma atualizada com sucesso!']);
}

// ── DELETE — Desativar ──────────────────────────────────────────────────
if ($metodo === 'DELETE') {
    apiExigirLogin('admin');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        apiErro('Informe o ID da turma (?id=N).');
    }

    $stmt = $pdo->prepare("UPDATE turmas SET ativo = 0 WHERE id = :id");
    $stmt->execute([':id' => $id]);
    apiResposta(['mensagem' => 'Turma desativada com sucesso!']);
}

apiErro('Método não suportado.', 405);
