<?php
/**
 * api/dashboard.php — API para dados do dashboard.
 *
 * GET /api/dashboard.php — Retorna estatísticas do sistema (admin)
 */

require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../conexao.php';

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    apiExigirLogin('admin');

    $stats = [
        'alunas_ativas'      => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'aluna' AND ativo = 1")->fetchColumn(),
        'treinadoras'        => (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'treinadora' AND ativo = 1")->fetchColumn(),
        'turmas_ativas'      => (int) $pdo->query("SELECT COUNT(*) FROM turmas WHERE ativo = 1")->fetchColumn(),
        'matriculas_ativas'  => (int) $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn(),
        'pagamentos_pendentes' => (int) $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status IN ('pendente','atrasado')")->fetchColumn(),
        'notificacoes_fila'  => (int) $pdo->query("SELECT COUNT(*) FROM fila_notificacoes WHERE status = 'pendente'")->fetchColumn(),
    ];

    apiResposta($stats);
}

apiErro('Método não suportado.', 405);
