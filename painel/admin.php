<?php
/**
 * admin.php — Painel Administrativo (Guerreiras Thai).
 *
 * Funcionalidades:
 *   - Dashboard com estatísticas
 *   - Gerenciar usuárias (CRUD)
 *   - Gerenciar turmas (CRUD)
 *   - Visualizar pagamentos
 *   - Visualizar frequência
 */

require_once __DIR__ . '/auth.php';
exigirLogin('admin');
require_once __DIR__ . '/../conexao.php';

// ── Ações POST ──────────────────────────────────────────────────────────
$msg = '';
$msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        // ── Criar usuária ───────────────────────────────────────────
        if ($acao === 'criar_usuario') {
            $nome   = trim($_POST['nome'] ?? '');
            $login  = trim($_POST['login'] ?? '');
            $email  = trim($_POST['email'] ?? '');
            $senha  = $_POST['nova_senha'] ?? '';
            $nivel  = $_POST['nivel'] ?? 'aluna';
            $cpf    = trim($_POST['cpf'] ?? '');

            if ($nome === '' || $login === '' || $email === '' || $senha === '') {
                throw new RuntimeException('Preencha todos os campos obrigatórios.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('E-mail inválido.');
            }
            if (!in_array($nivel, ['admin', 'treinadora', 'aluna'], true)) {
                throw new RuntimeException('Nível inválido.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO usuarios (nome, login, email, senha, cpf, nivel)
                VALUES (:nome, :login, :email, :senha, :cpf, :nivel)
            ");
            $stmt->execute([
                ':nome'  => $nome,
                ':login' => $login,
                ':email' => $email,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
                ':cpf'   => $cpf ?: null,
                ':nivel' => $nivel,
            ]);
            $msg = 'Usuária criada com sucesso!';
            $msgTipo = 'sucesso';
        }

        // ── Ativar / Desativar usuária ──────────────────────────────
        if ($acao === 'toggle_usuario') {
            $uid   = (int) ($_POST['usuario_id'] ?? 0);
            $ativo = (int) ($_POST['ativo'] ?? 0);
            $stmt = $pdo->prepare("UPDATE usuarios SET ativo = :a WHERE id = :id");
            $stmt->execute([':a' => $ativo, ':id' => $uid]);
            $msg = $ativo ? 'Usuária ativada.' : 'Usuária desativada.';
            $msgTipo = 'sucesso';
        }

        // ── Criar turma ─────────────────────────────────────────────
        if ($acao === 'criar_turma') {
            $tNome    = trim($_POST['turma_nome'] ?? '');
            $tHorario = trim($_POST['turma_horario'] ?? '');
            $tDia     = trim($_POST['turma_dia'] ?? '');
            $tMax     = (int) ($_POST['turma_max'] ?? 20);

            if ($tNome === '') {
                throw new RuntimeException('Nome da turma é obrigatório.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO turmas (nome, horario, dia_semana, max_alunas)
                VALUES (:nome, :horario, :dia, :max)
            ");
            $stmt->execute([
                ':nome'    => $tNome,
                ':horario' => $tHorario ?: null,
                ':dia'     => $tDia ?: null,
                ':max'     => $tMax,
            ]);
            $msg = 'Turma criada com sucesso!';
            $msgTipo = 'sucesso';
        }

        // ── Toggle turma ativa ──────────────────────────────────────
        if ($acao === 'toggle_turma') {
            $tid   = (int) ($_POST['turma_id'] ?? 0);
            $ativo = (int) ($_POST['ativo'] ?? 0);
            $stmt = $pdo->prepare("UPDATE turmas SET ativo = :a WHERE id = :id");
            $stmt->execute([':a' => $ativo, ':id' => $tid]);
            $msg = $ativo ? 'Turma ativada.' : 'Turma desativada.';
            $msgTipo = 'sucesso';
        }

        // ── Registrar pagamento ─────────────────────────────────────
        if ($acao === 'marcar_pago') {
            $pid    = (int) ($_POST['pagamento_id'] ?? 0);
            $metodo = $_POST['metodo'] ?? 'pix';
            if (!in_array($metodo, ['pix', 'cartao', 'dinheiro', 'boleto'], true)) {
                $metodo = 'pix';
            }
            $stmt = $pdo->prepare("
                UPDATE pagamentos SET status = 'pago', data_pgto = CURDATE(), metodo = :m WHERE id = :id
            ");
            $stmt->execute([':m' => $metodo, ':id' => $pid]);
            $msg = 'Pagamento registrado!';
            $msgTipo = 'sucesso';
        }

    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            $msg = 'Registro duplicado (login, e-mail ou CPF já existe).';
        } else {
            $msg = 'Erro no banco de dados.';
        }
        $msgTipo = 'erro';
    } catch (RuntimeException $e) {
        $msg = $e->getMessage();
        $msgTipo = 'erro';
    }
}

// ── Buscar dados para o dashboard ───────────────────────────────────────
$totalAlunas     = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'aluna' AND ativo = 1")->fetchColumn();
$totalTreinadoras = (int) $pdo->query("SELECT COUNT(*) FROM usuarios WHERE nivel = 'treinadora' AND ativo = 1")->fetchColumn();
$totalTurmas     = (int) $pdo->query("SELECT COUNT(*) FROM turmas WHERE ativo = 1")->fetchColumn();
$pgPendentes     = (int) $pdo->query("SELECT COUNT(*) FROM pagamentos WHERE status IN ('pendente','atrasado')")->fetchColumn();
$totalMatriculas = (int) $pdo->query("SELECT COUNT(*) FROM matriculas WHERE status = 'ativa'")->fetchColumn();
$notifPendentes  = (int) $pdo->query("SELECT COUNT(*) FROM fila_notificacoes WHERE status = 'pendente'")->fetchColumn();

// Usuárias
$usuarios = $pdo->query("SELECT id, nome, login, email, nivel, ativo, criado_em FROM usuarios ORDER BY criado_em DESC")->fetchAll();

// Turmas
$turmas = $pdo->query("SELECT id, nome, horario, dia_semana, max_alunas, ativo FROM turmas ORDER BY nome")->fetchAll();

// Pagamentos recentes
$pagamentos = $pdo->query("
    SELECT p.id, u.nome AS aluna, t.nome AS turma, p.valor, p.data_venc, p.data_pgto, p.metodo, p.status
    FROM pagamentos p
    JOIN matriculas m ON p.matricula_id = m.id
    JOIN usuarios u ON m.usuario_id = u.id
    JOIN turmas t ON m.turma_id = t.id
    ORDER BY p.data_venc DESC
    LIMIT 50
")->fetchAll();

// ── Seção visualizada ───────────────────────────────────────────────────
$secao = $_GET['s'] ?? 'dashboard';

$tituloPagina = 'Painel Admin';
require_once __DIR__ . '/header.php';
?>

<!-- ── Navegação de seções ──────────────────────────────────────── -->
<div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
    <a href="?s=dashboard"  class="btn <?= $secao === 'dashboard'  ? 'btn-roxo' : 'btn-cinza' ?>">📊 Dashboard</a>
    <a href="?s=usuarios"   class="btn <?= $secao === 'usuarios'   ? 'btn-roxo' : 'btn-cinza' ?>">👥 Usuárias</a>
    <a href="?s=turmas"     class="btn <?= $secao === 'turmas'     ? 'btn-roxo' : 'btn-cinza' ?>">🥊 Turmas</a>
    <a href="?s=pagamentos" class="btn <?= $secao === 'pagamentos' ? 'btn-roxo' : 'btn-cinza' ?>">💰 Pagamentos</a>
</div>

<?php if ($msg): ?>
    <div class="msg msg--<?= $msgTipo ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($secao === 'dashboard'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  DASHBOARD                                                       -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<h2>📊 Visão Geral</h2>
<div class="stats-grid">
    <div class="stat-card">
        <div class="icon">👩</div>
        <div class="number"><?= $totalAlunas ?></div>
        <div class="label">Alunas ativas</div>
    </div>
    <div class="stat-card">
        <div class="icon">🏋️</div>
        <div class="number"><?= $totalTreinadoras ?></div>
        <div class="label">Treinadoras</div>
    </div>
    <div class="stat-card">
        <div class="icon">🥊</div>
        <div class="number"><?= $totalTurmas ?></div>
        <div class="label">Turmas ativas</div>
    </div>
    <div class="stat-card">
        <div class="icon">📋</div>
        <div class="number"><?= $totalMatriculas ?></div>
        <div class="label">Matrículas ativas</div>
    </div>
    <div class="stat-card">
        <div class="icon">💰</div>
        <div class="number"><?= $pgPendentes ?></div>
        <div class="label">Pagamentos pendentes</div>
    </div>
    <div class="stat-card">
        <div class="icon">📩</div>
        <div class="number"><?= $notifPendentes ?></div>
        <div class="label">Notificações na fila</div>
    </div>
</div>

<?php elseif ($secao === 'usuarios'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  USUÁRIAS                                                        -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<h2>👥 Gerenciar Usuárias</h2>

<details style="margin-bottom:1rem;">
    <summary class="btn btn-roxo" style="cursor:pointer;">+ Nova Usuária</summary>
    <form method="POST" class="form-inline" style="margin-top:.8rem;">
        <input type="hidden" name="acao" value="criar_usuario">
        <div class="form-row">
            <div class="form-group">
                <label>Nome *</label>
                <input type="text" name="nome" required>
            </div>
            <div class="form-group">
                <label>Login *</label>
                <input type="text" name="login" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>E-mail *</label>
                <input type="email" name="email" required>
            </div>
            <div class="form-group">
                <label>Senha *</label>
                <input type="password" name="nova_senha" required minlength="6">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>CPF</label>
                <input type="text" name="cpf" maxlength="14">
            </div>
            <div class="form-group">
                <label>Nível</label>
                <select name="nivel">
                    <option value="aluna">Aluna</option>
                    <option value="treinadora">Treinadora</option>
                    <option value="admin">Admin</option>
                </select>
            </div>
        </div>
        <button type="submit" class="btn btn-roxo">Criar Usuária</button>
    </form>
</details>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Login</th>
                <th>E-mail</th>
                <th>Nível</th>
                <th>Status</th>
                <th>Criado em</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($usuarios as $u): ?>
            <tr>
                <td><?= (int) $u['id'] ?></td>
                <td><?= htmlspecialchars($u['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['login'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><span class="badge badge--<?= htmlspecialchars($u['nivel'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($u['nivel'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <?php if ($u['ativo']): ?>
                        <span class="badge badge--ativa">ativa</span>
                    <?php else: ?>
                        <span class="badge badge--cancelado">inativa</span>
                    <?php endif; ?>
                </td>
                <td><?= htmlspecialchars($u['criado_em'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="toggle_usuario">
                        <input type="hidden" name="usuario_id" value="<?= (int) $u['id'] ?>">
                        <input type="hidden" name="ativo" value="<?= $u['ativo'] ? 0 : 1 ?>">
                        <button type="submit" class="btn btn-sm <?= $u['ativo'] ? 'btn-vermelho' : 'btn-verde' ?>">
                            <?= $u['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($secao === 'turmas'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  TURMAS                                                          -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<h2>🥊 Gerenciar Turmas</h2>

<details style="margin-bottom:1rem;">
    <summary class="btn btn-roxo" style="cursor:pointer;">+ Nova Turma</summary>
    <form method="POST" class="form-inline" style="margin-top:.8rem;">
        <input type="hidden" name="acao" value="criar_turma">
        <div class="form-row">
            <div class="form-group">
                <label>Nome da turma *</label>
                <input type="text" name="turma_nome" required>
            </div>
            <div class="form-group">
                <label>Horário</label>
                <input type="text" name="turma_horario" placeholder="19:00 - 20:00">
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Dia(s) da semana</label>
                <input type="text" name="turma_dia" placeholder="Seg, Qua, Sex">
            </div>
            <div class="form-group">
                <label>Máx. alunas</label>
                <input type="number" name="turma_max" value="20" min="1">
            </div>
        </div>
        <button type="submit" class="btn btn-roxo">Criar Turma</button>
    </form>
</details>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>Horário</th>
                <th>Dia(s)</th>
                <th>Máx.</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($turmas as $t): ?>
            <tr>
                <td><?= (int) $t['id'] ?></td>
                <td><?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['horario'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['dia_semana'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= (int) $t['max_alunas'] ?></td>
                <td>
                    <?php if ($t['ativo']): ?>
                        <span class="badge badge--ativa">ativa</span>
                    <?php else: ?>
                        <span class="badge badge--cancelado">inativa</span>
                    <?php endif; ?>
                </td>
                <td>
                    <form method="POST" style="display:inline;">
                        <input type="hidden" name="acao" value="toggle_turma">
                        <input type="hidden" name="turma_id" value="<?= (int) $t['id'] ?>">
                        <input type="hidden" name="ativo" value="<?= $t['ativo'] ? 0 : 1 ?>">
                        <button type="submit" class="btn btn-sm <?= $t['ativo'] ? 'btn-vermelho' : 'btn-verde' ?>">
                            <?= $t['ativo'] ? 'Desativar' : 'Ativar' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php elseif ($secao === 'pagamentos'): ?>
<!-- ═══════════════════════════════════════════════════════════════ -->
<!--  PAGAMENTOS                                                      -->
<!-- ═══════════════════════════════════════════════════════════════ -->
<h2>💰 Pagamentos</h2>

<div class="table-wrap">
    <table>
        <thead>
            <tr>
                <th>ID</th>
                <th>Aluna</th>
                <th>Turma</th>
                <th>Valor</th>
                <th>Vencimento</th>
                <th>Pagamento</th>
                <th>Método</th>
                <th>Status</th>
                <th>Ação</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($pagamentos as $pg): ?>
            <tr>
                <td><?= (int) $pg['id'] ?></td>
                <td><?= htmlspecialchars($pg['aluna'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($pg['turma'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>R$ <?= number_format((float) $pg['valor'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($pg['data_venc'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $pg['data_pgto'] ? htmlspecialchars($pg['data_pgto'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= $pg['metodo'] ? htmlspecialchars($pg['metodo'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><span class="badge badge--<?= htmlspecialchars($pg['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pg['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                <td>
                    <?php if ($pg['status'] !== 'pago' && $pg['status'] !== 'cancelado'): ?>
                    <form method="POST" style="display:inline-flex; gap:.3rem; align-items:center;">
                        <input type="hidden" name="acao" value="marcar_pago">
                        <input type="hidden" name="pagamento_id" value="<?= (int) $pg['id'] ?>">
                        <select name="metodo" style="padding:.2rem; font-size:.75rem; background:#333; color:#fff; border:1px solid #555; border-radius:4px;">
                            <option value="pix">Pix</option>
                            <option value="dinheiro">Dinheiro</option>
                            <option value="cartao">Cartão</option>
                            <option value="boleto">Boleto</option>
                        </select>
                        <button type="submit" class="btn btn-sm btn-verde">Pago</button>
                    </form>
                    <?php else: ?>
                        ✅
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pagamentos)): ?>
            <tr><td colspan="9" style="text-align:center; color:var(--cinza); padding:2rem;">Nenhum pagamento registrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
