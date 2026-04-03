<?php
/**
 * api/auth.php — Middleware de autenticação para a API.
 *
 * Verifica se o usuário está logado via sessão.
 * Retorna JSON em caso de erro.
 */

session_start();
header('Content-Type: application/json; charset=utf-8');

/**
 * Exige que o usuário esteja autenticado via sessão.
 * Opcionalmente, verifica o nível exigido.
 *
 * @param string|array|null $nivelExigido
 */
function apiExigirLogin($nivelExigido = null): void
{
    if (empty($_SESSION['usuario_id'])) {
        http_response_code(401);
        echo json_encode(['erro' => 'Não autenticado. Faça login primeiro.']);
        exit;
    }

    if ($nivelExigido !== null) {
        $permitidos = is_array($nivelExigido) ? $nivelExigido : [$nivelExigido];
        if (!in_array($_SESSION['nivel'], $permitidos, true)) {
            http_response_code(403);
            echo json_encode(['erro' => 'Acesso negado.']);
            exit;
        }
    }
}

/**
 * Retorna resposta JSON padronizada.
 */
function apiResposta(array $dados, int $codigo = 200): void
{
    http_response_code($codigo);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Retorna erro JSON padronizado.
 */
function apiErro(string $mensagem, int $codigo = 400): void
{
    http_response_code($codigo);
    echo json_encode(['erro' => $mensagem], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Retorna o ID do usuário logado na API.
 */
function apiUsuarioId(): int
{
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

/**
 * Retorna o nível do usuário logado na API.
 */
function apiUsuarioNivel(): string
{
    return $_SESSION['nivel'] ?? '';
}
