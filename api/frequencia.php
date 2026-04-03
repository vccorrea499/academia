<?php
/**
 * api/frequencia.php — API REST para gerenciar frequência.
 *
 * GET    /api/frequencia.php                — Frequência da aluna logada ou por turma (admin/treinadora)
 * POST   /api/frequencia.php                — Registrar frequência (admin/treinadora)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET — Listar frequência ─────────────────────────────────────────────
if ($metodo === 'GET') {
    apiExigirLogin();

    $turmaId = isset($_GET['turma_id']) ? (int) $_GET['turma_id'] : 0;
    $data    = $_GET['data'] ?? null;

    // Aluna vê apenas a própria frequência
    if (apiUsuarioNivel() === 'aluna') {
        $sql = "
            SELECT f.data_aula, f.presente, t.nome AS turma
            FROM frequencia f
            JOIN turmas t ON f.turma_id = t.id
            WHERE f.usuario_id = :uid
        ";
        $params = [':uid' => apiUsuarioId()];

        if ($turmaId > 0) {
            $sql .= " AND f.turma_id = :tid";
            $params[':tid'] = $turmaId;
        }
        $sql .= " ORDER BY f.data_aula DESC LIMIT 50";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        apiResposta(['frequencia' => $stmt->fetchAll()]);
    }

    // Admin/Treinadora pode ver por turma e data
    $sql = "
        SELECT f.id, f.data_aula, f.presente, u.nome AS aluna, t.nome AS turma
        FROM frequencia f
        JOIN usuarios u ON f.usuario_id = u.id
        JOIN turmas t ON f.turma_id = t.id
        WHERE 1=1
    ";
    $params = [];

    if ($turmaId > 0) {
        $sql .= " AND f.turma_id = :tid";
        $params[':tid'] = $turmaId;
    }
    if ($data !== null) {
        $sql .= " AND f.data_aula = :data";
        $params[':data'] = $data;
    }

    $sql .= " ORDER BY f.data_aula DESC, u.nome LIMIT 200";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    apiResposta(['frequencia' => $stmt->fetchAll()]);
}

// ── POST — Registrar frequência ─────────────────────────────────────────
if ($metodo === 'POST') {
    apiExigirLogin(['admin', 'treinadora']);

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $turmaId  = (int) ($input['turma_id'] ?? 0);
    $dataAula = trim($input['data_aula'] ?? date('Y-m-d'));
    $presentes = $input['presentes'] ?? []; // Array de usuario_id

    if ($turmaId <= 0) {
        apiErro('Campo obrigatório: turma_id.');
    }
    if (!is_array($presentes)) {
        apiErro('O campo "presentes" deve ser um array de IDs de alunas.');
    }

    // Buscar todas as alunas matriculadas
    $stmt = $pdo->prepare("
        SELECT m.usuario_id FROM matriculas m WHERE m.turma_id = :tid AND m.status = 'ativa'
    ");
    $stmt->execute([':tid' => $turmaId]);
    $alunas = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmtFreq = $pdo->prepare("
        INSERT INTO frequencia (usuario_id, turma_id, data_aula, presente)
        VALUES (:uid, :tid, :data, :presente)
        ON DUPLICATE KEY UPDATE presente = VALUES(presente)
    ");

    $presentes = array_map('intval', $presentes);
    foreach ($alunas as $alunaId) {
        $presente = in_array((int) $alunaId, $presentes, true) ? 1 : 0;
        $stmtFreq->execute([
            ':uid'     => (int) $alunaId,
            ':tid'     => $turmaId,
            ':data'    => $dataAula,
            ':presente' => $presente,
        ]);
    }

    apiResposta(['mensagem' => 'Frequência registrada!', 'total_alunas' => count($alunas)], 201);
}

apiErro('Método não suportado.', 405);
