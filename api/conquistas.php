<?php
/**
 * api/conquistas.php — API REST para gerenciar conquistas das alunas.
 *
 * GET    /api/conquistas.php              — Conquistas da aluna logada ou todas (admin/treinadora)
 * GET    /api/conquistas.php?aluna_id=N   — Conquistas de uma aluna específica
 * POST   /api/conquistas.php              — Registrar conquista (admin/treinadora)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

// ── GET — Listar conquistas ─────────────────────────────────────────────
if ($metodo === 'GET') {
    apiExigirLogin();

    $alunaId = isset($_GET['aluna_id']) ? (int) $_GET['aluna_id'] : 0;

    // Aluna só vê as próprias conquistas
    if (apiUsuarioNivel() === 'aluna') {
        $alunaId = apiUsuarioId();
    }

    if ($alunaId > 0) {
        $stmt = $pdo->prepare("
            SELECT c.id, c.conquista, c.data_conquista, tc.nome AS tecnica, tc.categoria
            FROM aluna_conquistas c
            LEFT JOIN tecnicas_checklist tc ON c.tecnica_id = tc.id
            WHERE c.usuario_id = :uid
            ORDER BY c.data_conquista DESC
        ");
        $stmt->execute([':uid' => $alunaId]);
    } else {
        $stmt = $pdo->query("
            SELECT c.id, u.nome AS aluna, c.conquista, c.data_conquista, tc.nome AS tecnica, tc.categoria
            FROM aluna_conquistas c
            JOIN usuarios u ON c.usuario_id = u.id
            LEFT JOIN tecnicas_checklist tc ON c.tecnica_id = tc.id
            ORDER BY c.data_conquista DESC
            LIMIT 100
        ");
    }

    apiResposta(['conquistas' => $stmt->fetchAll()]);
}

// ── POST — Registrar conquista ──────────────────────────────────────────
if ($metodo === 'POST') {
    apiExigirLogin(['admin', 'treinadora']);

    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;

    $alunaId   = (int) ($input['aluna_id'] ?? 0);
    $tecnicaId = (int) ($input['tecnica_id'] ?? 0);
    $conquista = trim($input['conquista'] ?? '');

    if ($alunaId <= 0 || $conquista === '') {
        apiErro('Campos obrigatórios: aluna_id, conquista.');
    }

    $stmt = $pdo->prepare("
        INSERT INTO aluna_conquistas (usuario_id, tecnica_id, conquista, data_conquista)
        VALUES (:uid, :tid, :conquista, CURDATE())
    ");
    $stmt->execute([
        ':uid'       => $alunaId,
        ':tid'       => $tecnicaId > 0 ? $tecnicaId : null,
        ':conquista' => $conquista,
    ]);

    apiResposta(['mensagem' => 'Conquista registrada!', 'id' => (int) $pdo->lastInsertId()], 201);
}

apiErro('Método não suportado.', 405);
