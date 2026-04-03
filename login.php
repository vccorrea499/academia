<?php
/**
 * login.php — Autenticação via PDO + password_verify().
 *
 * Redireciona para o painel correto de acordo com o nível do usuário:
 *   admin      → /painel/admin.php
 *   treinadora → /painel/treinadora.php
 *   aluna      → /painel/aluna.php
 */

session_start();

// Se já logado, redirecionar
if (!empty($_SESSION['usuario_id'])) {
    redirecionarPorNivel($_SESSION['nivel']);
    exit;
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/conexao.php';

    $login = trim($_POST['login'] ?? '');
    $senha = $_POST['senha'] ?? '';

    if ($login === '' || $senha === '') {
        $erro = 'Preencha todos os campos.';
    } else {
        $stmt = $pdo->prepare(
            "SELECT id, nome, login, senha, nivel FROM usuarios WHERE login = :login AND ativo = 1 LIMIT 1"
        );
        $stmt->execute([':login' => $login]);
        $user = $stmt->fetch();

        if ($user && password_verify($senha, $user['senha'])) {
            // Regenerar ID da sessão para prevenir fixation
            session_regenerate_id(true);

            $_SESSION['usuario_id'] = (int) $user['id'];
            $_SESSION['nome']       = $user['nome'];
            $_SESSION['login']      = $user['login'];
            $_SESSION['nivel']      = $user['nivel'];

            redirecionarPorNivel($user['nivel']);
            exit;
        } else {
            $erro = 'Login ou senha inválidos.';
        }
    }
}

/**
 * Redireciona o usuário para o painel correto de acordo com o nível.
 */
function redirecionarPorNivel(string $nivel): void
{
    $destinos = [
        'admin'      => '/painel/admin.php',
        'treinadora' => '/painel/treinadora.php',
        'aluna'      => '/painel/aluna.php',
    ];

    $url = $destinos[$nivel] ?? '/login.php';
    header("Location: $url");
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — Guerreiras Thai</title>
    <style>
        /* ── Reset & Base ─────────────────────────────────────── */
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #1a1a1a;
            color: #fff;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        /* ── Card de Login ────────────────────────────────────── */
        .login-card {
            background: #2d2d2d;
            border: 2px solid #7b2d8e;
            border-radius: 16px;
            padding: 2rem 1.5rem;
            width: 100%;
            max-width: 400px;
            margin: 1rem;
            box-shadow: 0 8px 32px rgba(123, 45, 142, .3);
        }

        .login-card h1 {
            text-align: center;
            color: #c084fc;
            font-size: 1.6rem;
            margin-bottom: .25rem;
        }
        .login-card .subtitle {
            text-align: center;
            color: #a1a1a1;
            font-size: .85rem;
            margin-bottom: 1.5rem;
        }

        /* ── Logo Emoji ───────────────────────────────────────── */
        .logo {
            text-align: center;
            font-size: 3rem;
            margin-bottom: .5rem;
        }

        /* ── Form ─────────────────────────────────────────────── */
        .form-group { margin-bottom: 1.2rem; }

        .form-group label {
            display: block;
            font-size: .85rem;
            color: #c084fc;
            margin-bottom: .35rem;
            font-weight: 600;
        }

        .form-group input {
            width: 100%;
            padding: .75rem 1rem;
            border: 1px solid #444;
            border-radius: 8px;
            background: #1a1a1a;
            color: #fff;
            font-size: 1rem;
            transition: border-color .2s;
        }
        .form-group input:focus {
            outline: none;
            border-color: #7b2d8e;
            box-shadow: 0 0 0 3px rgba(123,45,142,.25);
        }

        /* ── Botão ────────────────────────────────────────────── */
        .btn-login {
            width: 100%;
            padding: .85rem;
            border: none;
            border-radius: 8px;
            background: linear-gradient(135deg, #7b2d8e, #5b1a6e);
            color: #fff;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
        }
        .btn-login:hover { opacity: .9; }
        .btn-login:active { transform: scale(.98); }

        /* ── Erro ─────────────────────────────────────────────── */
        .erro {
            background: #5c1a1a;
            color: #f87171;
            padding: .65rem 1rem;
            border-radius: 8px;
            font-size: .85rem;
            margin-bottom: 1rem;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="login-card">
        <div class="logo">🥊</div>
        <h1>Guerreiras Thai</h1>
        <p class="subtitle">Área restrita — faça seu login</p>

        <?php if ($erro): ?>
            <div class="erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
        <?php endif; ?>

        <form method="POST" action="login.php" autocomplete="off">
            <div class="form-group">
                <label for="login">Usuário</label>
                <input type="text" id="login" name="login" placeholder="Seu login"
                       value="<?= htmlspecialchars($_POST['login'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                       required autofocus>
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>
                <input type="password" id="senha" name="senha" placeholder="Sua senha" required>
            </div>

            <button type="submit" class="btn-login">Entrar</button>
        </form>
    </div>
</body>
</html>
