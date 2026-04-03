<?php
/**
 * header.php — Cabeçalho compartilhado dos painéis (CSS + Navbar).
 *
 * Variáveis esperadas antes do include:
 *   $tituloPagina — título da página (ex: 'Painel Admin')
 */
$tituloPagina = $tituloPagina ?? 'Guerreiras Thai';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($tituloPagina, ENT_QUOTES, 'UTF-8') ?> — Guerreiras Thai</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --roxo:       #7b2d8e;
            --roxo-light: #c084fc;
            --roxo-dark:  #5b1a6e;
            --preto:      #1a1a1a;
            --preto-card: #2d2d2d;
            --branco:     #ffffff;
            --cinza:      #a1a1a1;
            --erro:       #f87171;
            --sucesso:    #4ade80;
            --aviso:      #fbbf24;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--preto);
            color: var(--branco);
            min-height: 100vh;
        }

        /* ── Navbar ──────────────────────────────────────── */
        .navbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .8rem 1.2rem;
            background: var(--preto-card);
            border-bottom: 2px solid var(--roxo);
        }
        .navbar .brand {
            display: flex;
            align-items: center;
            gap: .5rem;
            font-weight: 700;
            color: var(--roxo-light);
            font-size: 1.1rem;
            text-decoration: none;
        }
        .navbar .nav-links {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .navbar .nav-links a {
            color: var(--cinza);
            text-decoration: none;
            font-size: .85rem;
            transition: color .2s;
        }
        .navbar .nav-links a:hover { color: var(--roxo-light); }
        .navbar .user-info {
            font-size: .82rem;
            color: var(--cinza);
        }
        .navbar .user-info strong { color: var(--roxo-light); }

        /* ── Layout ──────────────────────────────────────── */
        .container {
            max-width: 1100px;
            margin: 0 auto;
            padding: 1.5rem 1rem;
        }

        h2 {
            color: var(--roxo-light);
            font-size: 1.3rem;
            margin-bottom: 1rem;
            border-bottom: 1px solid #444;
            padding-bottom: .5rem;
        }

        /* ── Cards ───────────────────────────────────────── */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        .stat-card {
            background: var(--preto-card);
            border: 1px solid #444;
            border-radius: 12px;
            padding: 1.2rem;
            text-align: center;
        }
        .stat-card .icon { font-size: 2rem; margin-bottom: .3rem; }
        .stat-card .number {
            font-size: 1.8rem;
            font-weight: 700;
            color: var(--roxo-light);
        }
        .stat-card .label {
            font-size: .8rem;
            color: var(--cinza);
            margin-top: .2rem;
        }

        /* ── Tabelas ─────────────────────────────────────── */
        .table-wrap {
            overflow-x: auto;
            margin-bottom: 2rem;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .88rem;
        }
        thead th {
            background: var(--preto-card);
            color: var(--roxo-light);
            padding: .7rem .6rem;
            text-align: left;
            font-weight: 600;
            border-bottom: 2px solid var(--roxo);
        }
        tbody td {
            padding: .65rem .6rem;
            border-bottom: 1px solid #333;
        }
        tbody tr:hover { background: rgba(123,45,142,.08); }

        /* ── Badges ──────────────────────────────────────── */
        .badge {
            display: inline-block;
            padding: .15rem .6rem;
            border-radius: 20px;
            font-size: .75rem;
            font-weight: 600;
        }
        .badge--ativa  { background: #1a3a1a; color: var(--sucesso); }
        .badge--pago   { background: #1a3a1a; color: var(--sucesso); }
        .badge--pendente { background: #3a2a0a; color: var(--aviso); }
        .badge--atrasado { background: #5c1a1a; color: var(--erro); }
        .badge--cancelado, .badge--cancelada, .badge--trancada { background: #333; color: var(--cinza); }
        .badge--admin { background: #2a1a3a; color: var(--roxo-light); }
        .badge--treinadora { background: #1a2a3a; color: #60a5fa; }
        .badge--aluna { background: #1a3a1a; color: var(--sucesso); }

        /* ── Botões ──────────────────────────────────────── */
        .btn {
            display: inline-block;
            padding: .5rem 1rem;
            border: none;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: opacity .2s;
        }
        .btn:hover { opacity: .85; }
        .btn-roxo { background: linear-gradient(135deg, var(--roxo), var(--roxo-dark)); color: #fff; }
        .btn-verde { background: #166534; color: #fff; }
        .btn-vermelho { background: #7f1d1d; color: #fff; }
        .btn-cinza { background: #444; color: #fff; }
        .btn-sm { padding: .3rem .6rem; font-size: .78rem; }

        /* ── Formulários modais inline ───────────────────── */
        .form-inline {
            background: var(--preto-card);
            border: 1px solid #444;
            border-radius: 12px;
            padding: 1.2rem;
            margin-bottom: 1.5rem;
        }
        .form-inline .form-row {
            display: flex;
            flex-wrap: wrap;
            gap: .8rem;
            margin-bottom: .8rem;
        }
        .form-inline .form-group {
            flex: 1 1 200px;
        }
        .form-inline label {
            display: block;
            font-size: .8rem;
            color: var(--roxo-light);
            margin-bottom: .25rem;
            font-weight: 600;
        }
        .form-inline input,
        .form-inline select,
        .form-inline textarea {
            width: 100%;
            padding: .55rem .7rem;
            border: 1px solid #444;
            border-radius: 8px;
            background: var(--preto);
            color: #fff;
            font-size: .88rem;
        }
        .form-inline input:focus,
        .form-inline select:focus {
            outline: none;
            border-color: var(--roxo);
            box-shadow: 0 0 0 3px rgba(123,45,142,.25);
        }

        /* ── Mensagens flash ─────────────────────────────── */
        .msg {
            padding: .65rem 1rem;
            border-radius: 8px;
            margin-bottom: 1rem;
            font-size: .88rem;
        }
        .msg--sucesso { background: #1a3a1a; color: var(--sucesso); }
        .msg--erro    { background: #5c1a1a; color: var(--erro); }

        /* ── Logout ──────────────────────────────────────── */
        .btn-logout {
            background: none;
            border: 1px solid #555;
            color: var(--cinza);
            padding: .3rem .8rem;
            border-radius: 6px;
            font-size: .78rem;
            cursor: pointer;
        }
        .btn-logout:hover { border-color: var(--erro); color: var(--erro); }

        /* ── Sections ────────────────────────────────────── */
        .section { margin-bottom: 2rem; }

        /* ── Responsive ──────────────────────────────────── */
        @media (max-width: 600px) {
            .stats-grid { grid-template-columns: repeat(2, 1fr); }
            .navbar { flex-wrap: wrap; gap: .5rem; }
        }
    </style>
</head>
<body>
<nav class="navbar">
    <a href="#" class="brand">🥊 Guerreiras Thai</a>
    <div class="nav-links">
        <span class="user-info">Olá, <strong><?= htmlspecialchars(usuarioNome(), ENT_QUOTES, 'UTF-8') ?></strong></span>
        <a href="/painel/logout.php" class="btn-logout">Sair</a>
    </div>
</nav>
<div class="container">
