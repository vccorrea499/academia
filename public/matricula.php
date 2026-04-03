<?php
/**
 * matricula.php — Formulário de Matrícula com Accordions (Mobile-First).
 * Sistema Guerreiras Thai.
 */
session_start();

$sucesso = '';
$erro    = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../conexao.php';

    // Sanitização
    $nome       = trim($_POST['nome'] ?? '');
    $email      = trim($_POST['email'] ?? '');
    $whatsapp   = trim($_POST['whatsapp'] ?? '');
    $cpf        = trim($_POST['cpf'] ?? '');
    $data_nasc  = trim($_POST['data_nasc'] ?? '');
    $senha      = $_POST['senha'] ?? '';
    $turma_id   = (int) ($_POST['turma_id'] ?? 0);

    // Anamnese
    $peso       = !empty($_POST['peso'])  ? (float) $_POST['peso']  : null;
    $altura     = !empty($_POST['altura'])? (float) $_POST['altura']: null;
    $restricoes = trim($_POST['restricoes_med'] ?? '');

    // Validação básica
    if ($nome === '' || $email === '' || $senha === '' || $cpf === '') {
        $erro = 'Preencha os campos obrigatórios (Nome, E-mail, CPF e Senha).';
    } elseif (strpos($email, '@') === false || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $erro = 'Informe um e-mail válido.';
    } elseif (strlen($senha) < 6) {
        $erro = 'A senha deve ter pelo menos 6 caracteres.';
    } else {
        try {
            $pdo->beginTransaction();

            // Criar usuária
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, login, email, senha, whatsapp, data_nasc, cpf, nivel)
                VALUES (:nome, :login, :email, :senha, :whatsapp, :data_nasc, :cpf, 'aluna')
            ");
            $login = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', explode('@', $email)[0]));
            $stmt->execute([
                ':nome'      => $nome,
                ':login'     => $login,
                ':email'     => $email,
                ':senha'     => $senhaHash,
                ':whatsapp'  => $whatsapp ?: null,
                ':data_nasc' => $data_nasc ?: null,
                ':cpf'       => $cpf,
            ]);
            $usuarioId = (int) $pdo->lastInsertId();

            // Criar matrícula se turma selecionada
            if ($turma_id > 0) {
                $stmt2 = $pdo->prepare("
                    INSERT INTO matriculas (usuario_id, turma_id, data_inicio)
                    VALUES (:uid, :tid, CURDATE())
                ");
                $stmt2->execute([':uid' => $usuarioId, ':tid' => $turma_id]);
            }

            // Registrar dados físicos iniciais (anamnese)
            if ($peso || $altura || $restricoes !== '') {
                $stmt3 = $pdo->prepare("
                    INSERT INTO evolucao_fisica (usuario_id, data_registro, peso_kg, altura_cm, restricoes_med)
                    VALUES (:uid, CURDATE(), :peso, :altura, :restr)
                ");
                $stmt3->execute([
                    ':uid'    => $usuarioId,
                    ':peso'   => $peso,
                    ':altura' => $altura,
                    ':restr'  => $restricoes ?: null,
                ]);
            }

            // Notificação de boas-vindas
            $stmt4 = $pdo->prepare("
                INSERT INTO fila_notificacoes (usuario_id, tipo, mensagem)
                VALUES (:uid, 'boas_vindas', :msg)
            ");
            $stmt4->execute([
                ':uid' => $usuarioId,
                ':msg' => 'Bem-vinda à família Guerreiras Thai! 🥊💜',
            ]);

            $pdo->commit();
            $sucesso = 'Matrícula realizada com sucesso! Bem-vinda, Guerreira! 🥊';
        } catch (PDOException $e) {
            $pdo->rollBack();
            if ((string) $e->getCode() === '23000') {
                $erro = 'E-mail ou CPF já cadastrado no sistema.';
            } else {
                $erro = 'Erro ao processar a matrícula. Tente novamente.';
            }
        }
    }
}

// Buscar turmas disponíveis
$turmas = [];
try {
    require_once __DIR__ . '/../conexao.php';
    $turmas = $pdo->query("SELECT id, nome, horario, dia_semana FROM turmas WHERE ativo = 1 ORDER BY nome")->fetchAll();
} catch (Exception $e) {
    // Silencioso — turmas pode estar vazia
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Matrícula — Guerreiras Thai</title>
    <style>
        /* ── Reset & Base (Mobile-First) ──────────────────────── */
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
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: var(--preto);
            color: var(--branco);
            min-height: 100vh;
            padding: 1rem;
        }

        /* ── Header ───────────────────────────────────────────── */
        .page-header {
            text-align: center;
            padding: 1.5rem 0 1rem;
        }
        .page-header .logo { font-size: 2.5rem; }
        .page-header h1 {
            color: var(--roxo-light);
            font-size: 1.5rem;
            margin-top: .25rem;
        }
        .page-header p {
            color: var(--cinza);
            font-size: .85rem;
        }

        /* ── Mensagens ────────────────────────────────────────── */
        .msg {
            padding: .75rem 1rem;
            border-radius: 10px;
            margin-bottom: 1rem;
            font-size: .9rem;
            text-align: center;
        }
        .msg--erro    { background: #5c1a1a; color: var(--erro); }
        .msg--sucesso { background: #1a3a1a; color: var(--sucesso); }

        /* ── Accordion ────────────────────────────────────────── */
        .accordion-btn {
            width: 100%;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: .9rem 1.2rem;
            margin-top: .6rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--roxo), var(--roxo-dark));
            color: var(--branco);
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s;
            -webkit-tap-highlight-color: transparent;
        }
        .accordion-btn:hover { opacity: .9; }

        .accordion-btn .arrow {
            display: inline-block;
            font-size: .75rem;
            transition: transform .3s ease;
        }
        .accordion-btn.active .arrow {
            transform: rotate(180deg);
        }

        .accordion-panel {
            max-height: 0;
            overflow: hidden;
            transition: max-height .35s ease;
            background: var(--preto-card);
            border-radius: 0 0 10px 10px;
        }
        .accordion-panel.open {
            max-height: 800px;
        }
        .accordion-panel-inner {
            padding: 1rem 1.2rem 1.2rem;
        }

        /* ── Campos do formulário ─────────────────────────────── */
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            font-size: .82rem;
            color: var(--roxo-light);
            margin-bottom: .3rem;
            font-weight: 600;
        }
        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: .7rem .9rem;
            border: 1px solid #444;
            border-radius: 8px;
            background: var(--preto);
            color: var(--branco);
            font-size: .95rem;
            transition: border-color .2s;
        }
        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: var(--roxo);
            box-shadow: 0 0 0 3px rgba(123,45,142,.25);
        }
        .form-group textarea { resize: vertical; min-height: 80px; }
        .form-group .hint {
            font-size: .75rem;
            color: var(--cinza);
            margin-top: .2rem;
        }
        .required::after {
            content: ' *';
            color: var(--erro);
        }

        /* ── Grid 2 colunas (tablets+) ────────────────────────── */
        .row { display: flex; gap: .8rem; flex-wrap: wrap; }
        .row .form-group { flex: 1 1 45%; min-width: 0; }

        /* ── Botão de envio ───────────────────────────────────── */
        .btn-submit {
            width: 100%;
            padding: 1rem;
            margin-top: 1.2rem;
            border: none;
            border-radius: 10px;
            background: linear-gradient(135deg, var(--roxo), var(--roxo-dark));
            color: var(--branco);
            font-size: 1.1rem;
            font-weight: 700;
            cursor: pointer;
            transition: opacity .2s, transform .1s;
        }
        .btn-submit:hover { opacity: .9; }
        .btn-submit:active { transform: scale(.98); }

        /* ── Link ─────────────────────────────────────────────── */
        .link-login {
            display: block;
            text-align: center;
            margin-top: 1rem;
            color: var(--roxo-light);
            font-size: .85rem;
            text-decoration: none;
        }
        .link-login:hover { text-decoration: underline; }

        /* ── Desktop ──────────────────────────────────────────── */
        @media (min-width: 600px) {
            body { display: flex; justify-content: center; }
            .container { width: 100%; max-width: 560px; }
        }
    </style>
</head>
<body>
<div class="container">

    <div class="page-header">
        <div class="logo">🥊</div>
        <h1>Guerreiras Thai</h1>
        <p>Formulário de Matrícula</p>
    </div>

    <?php if ($sucesso): ?>
        <div class="msg msg--sucesso"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>
    <?php if ($erro): ?>
        <div class="msg msg--erro"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
    <?php endif; ?>

    <form method="POST" action="" autocomplete="off" id="formMatricula">

        <!-- ═══ Seção 1: Dados Pessoais ═══ -->
        <button type="button" class="accordion-btn active">
            Dados Pessoais <span class="arrow">▼</span>
        </button>
        <div class="accordion-panel open">
            <div class="accordion-panel-inner">
                <div class="form-group">
                    <label for="nome" class="required">Nome completo</label>
                    <input type="text" id="nome" name="nome" placeholder="Maria da Silva"
                           value="<?= htmlspecialchars($_POST['nome'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="row">
                    <div class="form-group">
                        <label for="cpf" class="required">CPF</label>
                        <input type="text" id="cpf" name="cpf" placeholder="000.000.000-00"
                               maxlength="14" inputmode="numeric"
                               value="<?= htmlspecialchars($_POST['cpf'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                    </div>
                    <div class="form-group">
                        <label for="data_nasc">Data de Nascimento</label>
                        <input type="date" id="data_nasc" name="data_nasc"
                               value="<?= htmlspecialchars($_POST['data_nasc'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="whatsapp">WhatsApp</label>
                    <input type="tel" id="whatsapp" name="whatsapp" placeholder="(11) 99999-9999"
                           inputmode="tel"
                           value="<?= htmlspecialchars($_POST['whatsapp'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                </div>
            </div>
        </div>

        <!-- ═══ Seção 2: Acesso e Turma ═══ -->
        <button type="button" class="accordion-btn">
            Acesso &amp; Turma <span class="arrow">▼</span>
        </button>
        <div class="accordion-panel">
            <div class="accordion-panel-inner">
                <div class="form-group">
                    <label for="email" class="required">E-mail</label>
                    <input type="email" id="email" name="email" placeholder="voce@email.com"
                           value="<?= htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>" required>
                </div>
                <div class="form-group">
                    <label for="senha" class="required">Senha</label>
                    <input type="password" id="senha" name="senha" placeholder="Mínimo 6 caracteres" required>
                    <span class="hint">Será usada para acessar seu painel de aluna.</span>
                </div>
                <div class="form-group">
                    <label for="turma_id">Turma desejada</label>
                    <select id="turma_id" name="turma_id">
                        <option value="0">— Selecionar depois —</option>
                        <?php foreach ($turmas as $t): ?>
                            <option value="<?= (int) $t['id'] ?>"
                                <?= ((int)($_POST['turma_id'] ?? 0) === (int)$t['id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?>
                                <?= $t['horario'] ? ' — ' . htmlspecialchars($t['horario'], ENT_QUOTES, 'UTF-8') : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>

        <!-- ═══ Seção 3: Saúde / Anamnese ═══ -->
        <button type="button" class="accordion-btn">
            Saúde &amp; Anamnese <span class="arrow">▼</span>
        </button>
        <div class="accordion-panel">
            <div class="accordion-panel-inner">
                <div class="row">
                    <div class="form-group">
                        <label for="peso">Peso (kg)</label>
                        <input type="number" id="peso" name="peso" step="0.1" min="30" max="250"
                               placeholder="65.0" inputmode="decimal"
                               value="<?= htmlspecialchars($_POST['peso'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                    <div class="form-group">
                        <label for="altura">Altura (cm)</label>
                        <input type="number" id="altura" name="altura" step="0.1" min="100" max="250"
                               placeholder="165.0" inputmode="decimal"
                               value="<?= htmlspecialchars($_POST['altura'] ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label for="restricoes_med">Restrições médicas / Observações</label>
                    <textarea id="restricoes_med" name="restricoes_med"
                              placeholder="Ex: Asma, lesão no joelho, alergia..."><?= htmlspecialchars($_POST['restricoes_med'] ?? '', ENT_QUOTES, 'UTF-8') ?></textarea>
                </div>
            </div>
        </div>

        <button type="submit" class="btn-submit">Finalizar Matrícula 🥊</button>
    </form>

    <a href="../login.php" class="link-login">Já tem conta? Faça login</a>
</div>

<script>
    /* ── Accordion Toggle ─────────────────────────────────────── */
    document.querySelectorAll('.accordion-btn').forEach(function (btn) {
        btn.addEventListener('click', function () {
            this.classList.toggle('active');
            var panel = this.nextElementSibling;
            panel.classList.toggle('open');
        });
    });

    /* ── Máscara de CPF ───────────────────────────────────────── */
    document.getElementById('cpf').addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 9) {
            v = v.replace(/(\d{3})(\d{3})(\d{3})(\d{1,2})/, '$1.$2.$3-$4');
        } else if (v.length > 6) {
            v = v.replace(/(\d{3})(\d{3})(\d{1,3})/, '$1.$2.$3');
        } else if (v.length > 3) {
            v = v.replace(/(\d{3})(\d{1,3})/, '$1.$2');
        }
        this.value = v;
    });

    /* ── Máscara de WhatsApp ──────────────────────────────────── */
    document.getElementById('whatsapp').addEventListener('input', function () {
        var v = this.value.replace(/\D/g, '').substring(0, 11);
        if (v.length > 6) {
            v = v.replace(/(\d{2})(\d{5})(\d{1,4})/, '($1) $2-$3');
        } else if (v.length > 2) {
            v = v.replace(/(\d{2})(\d{1,5})/, '($1) $2');
        }
        this.value = v;
    });
</script>
</body>
</html>
