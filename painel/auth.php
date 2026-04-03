<?php
/**
 * auth.php — Guarda de autenticação para os painéis.
 *
 * Inclua este arquivo no topo de cada página do painel.
 * Redireciona para /login.php se o usuário não estiver logado.
 * Opcionalmente, verifica o nível exigido.
 */

session_start();

/**
 * Verifica se o usuário está autenticado.
 * Se $nivelExigido for informado, também verifica o nível.
 *
 * @param string|array|null $nivelExigido Nível(is) permitido(s), ex: 'admin' ou ['admin','treinadora']
 */
function exigirLogin($nivelExigido = null): void
{
    if (empty($_SESSION['usuario_id'])) {
        header('Location: /login.php');
        exit;
    }

    if ($nivelExigido !== null) {
        $permitidos = is_array($nivelExigido) ? $nivelExigido : [$nivelExigido];
        if (!in_array($_SESSION['nivel'], $permitidos, true)) {
            http_response_code(403);
            exit('Acesso negado.');
        }
    }
}

/**
 * Retorna o ID do usuário logado.
 */
function usuarioId(): int
{
    return (int) ($_SESSION['usuario_id'] ?? 0);
}

/**
 * Retorna o nível do usuário logado.
 */
function usuarioNivel(): string
{
    return $_SESSION['nivel'] ?? '';
}

/**
 * Retorna o nome do usuário logado.
 */
function usuarioNome(): string
{
    return $_SESSION['nome'] ?? '';
}
