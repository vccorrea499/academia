<?php
/**
 * api/pagamentos.php — API REST para gerenciar pagamentos.
 *
 * GET    /api/pagamentos.php              — Lista pagamentos (admin) ou da aluna logada
 * GET    /api/pagamentos.php?id=N         — Detalhes de um pagamento
 * POST   /api/pagamentos.php              — Criar novo pagamento (admin)
 * PUT    /api/pagamentos.php?id=N         — Atualizar / marcar como pago (admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET — Listar ou detalhar ────────────────────────────────────────────
if ($metodo === 'GET') {
    apiExigirLogin();

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

    if ($id > 0) {
        $stmt = $pdo->prepare("
            SELECT p.*, u.nome AS aluna, t.nome AS turma
            FROM pagamentos p
            JOIN matriculas m ON p.matricula_id = m.id
            JOIN usuarios u ON m.usuario_id = u.id
            JOIN turmas t ON m.turma_id = t.id
            WHERE p.id = :id
        ");
        $stmt->execute([':id' => $id]);
        $pg = $stmt->fetch();
        if (!$pg) {
            apiErro('Pagamento não encontrado.', 404);
        }

        // Alunas só podem ver seus próprios pagamentos
        if (apiUsuarioNivel() === 'aluna') {
            $stmtCheck = $pdo->prepare("SELECT m.usuario_id FROM pagamentos p JOIN matriculas m ON p.matricula_id = m.id WHERE p.id = :id");
            $stmtCheck->execute([':id' => $id]);
            $donoPg = (int) $stmtCheck->fetchColumn();
            if ($donoPg !== apiUsuarioId()) {
                apiErro('Acesso negado.', 403);
            }
        }

        apiResposta($pg);
    }

    // Listar
    if (apiUsuarioNivel() === 'aluna') {
        $stmt = $pdo->prepare("
            SELECT p.id, p.valor, p.data_venc, p.data_pgto, p.metodo, p.status, t.nome AS turma
            FROM pagamentos p
            JOIN matriculas m ON p.matricula_id = m.id
            JOIN turmas t ON m.turma_id = t.id
            WHERE m.usuario_id = :uid
            ORDER BY p.data_venc DESC
        ");
        $stmt->execute([':uid' => apiUsuarioId()]);
    } else {
        $status = $_GET['status'] ?? null;
        $sql = "
            SELECT p.id, u.nome AS aluna, t.nome AS turma, p.valor, p.data_venc, p.data_pgto, p.metodo, p.status
            FROM pagamentos p
            JOIN matriculas m ON p.matricula_id = m.id
            JOIN usuarios u ON m.usuario_id = u.id
            JOIN turmas t ON m.turma_id = t.id
        ";
        $params = [];
        if ($status !== null && in_array($status, ['pendente', 'pago', 'atrasado', 'cancelado'], true)) {
            $sql .= " WHERE p.status = :status";
            $params[':status'] = $status;
        }
        $sql .= " ORDER BY p.data_venc DESC LIMIT 100";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
    }

    apiResposta(['pagamentos' => $stmt->fetchAll()]);
}

// ── POST — Criar ────────────────────────────────────────────────────────
if ($metodo === 'POST') {
    apiExigirLogin('admin');

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $matriculaId = (int) ($input['matricula_id'] ?? 0);
    $valor       = (float) ($input['valor'] ?? 0);
    $dataVenc    = trim($input['data_venc'] ?? '');

    if ($matriculaId <= 0 || $valor <= 0 || $dataVenc === '') {
        apiErro('Campos obrigatórios: matricula_id, valor, data_venc.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO pagamentos (matricula_id, valor, data_venc)
        VALUES (:mid, :valor, :venc)
    ");
    $stmt->execute([
        ':mid'   => $matriculaId,
        ':valor' => $valor,
        ':venc'  => $dataVenc,
    ]);
    apiResposta(['mensagem' => 'Pagamento criado!', 'id' => (int) $pdo->lastInsertId()], 201);
}

// ── PUT — Atualizar / Marcar pago ───────────────────────────────────────
if ($metodo === 'PUT') {
    apiExigirLogin('admin');

    $id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($id <= 0) {
        apiErro('Informe o ID do pagamento (?id=N).');
    }

    $input = json_decode(file_get_contents('php://input'), true);
    if (empty($input)) {
        apiErro('Nenhum dado enviado.');
    }

    $campos = [];
    $params = [':id' => $id];

    // Marcar como pago
    if (!empty($input['marcar_pago'])) {
        $metodoP = $input['metodo'] ?? 'pix';
        if (!in_array($metodoP, ['pix', 'cartao', 'dinheiro', 'boleto'], true)) {
            $metodoP = 'pix';
        }
        $campos[] = "status = 'pago'";
        $campos[] = "data_pgto = CURDATE()";
        $campos[] = "metodo = :metodo";
        $params[':metodo'] = $metodoP;
    } else {
        $permitidos = ['valor', 'data_venc', 'status', 'metodo'];
        foreach ($permitidos as $campo) {
            if (array_key_exists($campo, $input)) {
                if ($campo === 'status' && !in_array($input[$campo], ['pendente', 'pago', 'atrasado', 'cancelado'], true)) {
                    apiErro('Status inválido.');
                }
                if ($campo === 'metodo' && $input[$campo] !== null && !in_array($input[$campo], ['pix', 'cartao', 'dinheiro', 'boleto'], true)) {
                    apiErro('Método inválido.');
                }
                $campos[] = "$campo = :$campo";
                $params[":$campo"] = $input[$campo];
            }
        }
    }

    if (empty($campos)) {
        apiErro('Nenhum campo válido para atualizar.');
    }

    $sql = "UPDATE pagamentos SET " . implode(', ', $campos) . " WHERE id = :id";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiResposta(['mensagem' => 'Pagamento atualizado!']);
}

apiErro('Método não suportado.', 405);
