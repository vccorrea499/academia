<?php
/**
 * treinadora.php — Painel da Treinadora (Guerreiras Thai).
 *
 * Funcionalidades:
 *   - Visualizar turmas
 *   - Marcar frequência das alunas
 *   - Visualizar progresso das alunas
 *   - Registrar conquistas
 */

require_once __DIR__ . '/auth.php';
exigirLogin(['admin', 'treinadora']);
require_once __DIR__ . '/../conexao.php';

$msg = '';
$msgTipo = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $acao = $_POST['acao'] ?? '';

    try {
        // ── Registrar frequência ────────────────────────────────
        if ($acao === 'registrar_frequencia') {
            $turmaId  = (int) ($_POST['turma_id'] ?? 0);
            $dataAula = $_POST['data_aula'] ?? date('Y-m-d');
            $presentes = $_POST['presentes'] ?? [];

            if ($turmaId <= 0) {
                throw new RuntimeException('Selecione uma turma.');
            }

            // Buscar todas as alunas matriculadas nessa turma
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

            foreach ($alunas as $alunaId) {
                $presente = in_array((string) $alunaId, $presentes, true) ? 1 : 0;
                $stmtFreq->execute([
                    ':uid'     => (int) $alunaId,
                    ':tid'     => $turmaId,
                    ':data'    => $dataAula,
                    ':presente' => $presente,
                ]);
            }

            $msg = 'Frequência registrada com sucesso!';
            $msgTipo = 'sucesso';
        }

        // ── Registrar conquista ─────────────────────────────────
        if ($acao === 'registrar_conquista') {
            $alunaId   = (int) ($_POST['aluna_id'] ?? 0);
            $tecnicaId = (int) ($_POST['tecnica_id'] ?? 0);
            $conquista = trim($_POST['conquista'] ?? '');

            if ($alunaId <= 0 || $conquista === '') {
                throw new RuntimeException('Preencha aluna e conquista.');
            }

            $stmt = $pdo->prepare("
                INSERT INTO aluna_conquistas (usuario_id, tecnica_id, conquista, data_conquista)
                VALUES (:uid, :tid, :c, CURDATE())
            ");
            $stmt->execute([
                ':uid' => $alunaId,
                ':tid' => $tecnicaId > 0 ? $tecnicaId : null,
                ':c'   => $conquista,
            ]);
            $msg = 'Conquista registrada!';
            $msgTipo = 'sucesso';
        }
    } catch (PDOException $e) {
        $msg = 'Erro no banco de dados.';
        $msgTipo = 'erro';
    } catch (RuntimeException $e) {
        $msg = $e->getMessage();
        $msgTipo = 'erro';
    }
}

// ── Dados ───────────────────────────────────────────────────────────────
$turmas = $pdo->query("SELECT id, nome, horario, dia_semana FROM turmas WHERE ativo = 1 ORDER BY nome")->fetchAll();

$turmaSelec = (int) ($_GET['turma'] ?? 0);
$alunasTurma = [];
if ($turmaSelec > 0) {
    $stmt = $pdo->prepare("
        SELECT u.id, u.nome, u.whatsapp
        FROM usuarios u
        JOIN matriculas m ON m.usuario_id = u.id
        WHERE m.turma_id = :tid AND m.status = 'ativa' AND u.ativo = 1
        ORDER BY u.nome
    ");
    $stmt->execute([':tid' => $turmaSelec]);
    $alunasTurma = $stmt->fetchAll();
}

// Todas as alunas (para conquistas)
$todasAlunas = $pdo->query("SELECT id, nome FROM usuarios WHERE nivel = 'aluna' AND ativo = 1 ORDER BY nome")->fetchAll();

// Técnicas
$tecnicas = $pdo->query("SELECT id, nome, categoria FROM tecnicas_checklist ORDER BY categoria, nome")->fetchAll();

$secao = $_GET['s'] ?? 'frequencia';

$tituloPagina = 'Painel Treinadora';
require_once __DIR__ . '/header.php';
?>

<div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
    <a href="?s=frequencia" class="btn <?= $secao === 'frequencia' ? 'btn-roxo' : 'btn-cinza' ?>">📋 Frequência</a>
    <a href="?s=conquistas" class="btn <?= $secao === 'conquistas' ? 'btn-roxo' : 'btn-cinza' ?>">🏆 Conquistas</a>
    <a href="?s=turmas"     class="btn <?= $secao === 'turmas' ? 'btn-roxo' : 'btn-cinza' ?>">🥊 Turmas</a>
</div>

<?php if ($msg): ?>
    <div class="msg msg--<?= $msgTipo ?>"><?= htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>

<?php if ($secao === 'frequencia'): ?>
<!-- ═══ FREQUÊNCIA ═══ -->
<h2>📋 Registrar Frequência</h2>

<div class="form-inline" style="margin-bottom:1rem;">
    <form method="GET" style="display:flex; gap:.8rem; flex-wrap:wrap; align-items:end;">
        <input type="hidden" name="s" value="frequencia">
        <div class="form-group">
            <label>Turma</label>
            <select name="turma" onchange="this.form.submit()">
                <option value="">— Selecionar turma —</option>
                <?php foreach ($turmas as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $turmaSelec === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?>
                        <?= $t['horario'] ? ' — ' . htmlspecialchars($t['horario'], ENT_QUOTES, 'UTF-8') : '' ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
</div>

<?php if ($turmaSelec > 0 && !empty($alunasTurma)): ?>
<form method="POST" class="form-inline">
    <input type="hidden" name="acao" value="registrar_frequencia">
    <input type="hidden" name="turma_id" value="<?= $turmaSelec ?>">

    <div class="form-row">
        <div class="form-group" style="flex:0 0 200px;">
            <label>Data da aula</label>
            <input type="date" name="data_aula" value="<?= date('Y-m-d') ?>" required>
        </div>
    </div>

    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Presente</th>
                    <th>Nome</th>
                    <th>WhatsApp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($alunasTurma as $a): ?>
                <tr>
                    <td>
                        <input type="checkbox" name="presentes[]" value="<?= (int) $a['id'] ?>" checked
                               style="width:18px; height:18px; accent-color:var(--roxo);">
                    </td>
                    <td><?= htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($a['whatsapp'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <button type="submit" class="btn btn-roxo" style="margin-top:.8rem;">Salvar Frequência</button>
</form>
<?php elseif ($turmaSelec > 0): ?>
    <p style="color:var(--cinza); padding:1rem;">Nenhuma aluna matriculada nesta turma.</p>
<?php endif; ?>

<?php elseif ($secao === 'conquistas'): ?>
<!-- ═══ CONQUISTAS ═══ -->
<h2>🏆 Registrar Conquista</h2>

<form method="POST" class="form-inline">
    <input type="hidden" name="acao" value="registrar_conquista">
    <div class="form-row">
        <div class="form-group">
            <label>Aluna *</label>
            <select name="aluna_id" required>
                <option value="">— Selecionar —</option>
                <?php foreach ($todasAlunas as $a): ?>
                    <option value="<?= (int) $a['id'] ?>"><?= htmlspecialchars($a['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="form-group">
            <label>Técnica (opcional)</label>
            <select name="tecnica_id">
                <option value="0">— Nenhuma —</option>
                <?php foreach ($tecnicas as $tc): ?>
                    <option value="<?= (int) $tc['id'] ?>">[<?= htmlspecialchars($tc['categoria'], ENT_QUOTES, 'UTF-8') ?>] <?= htmlspecialchars($tc['nome'], ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </div>
    <div class="form-row">
        <div class="form-group">
            <label>Conquista / Descrição *</label>
            <input type="text" name="conquista" placeholder="Ex: Dominou o Jab" required>
        </div>
    </div>
    <button type="submit" class="btn btn-roxo">Registrar Conquista</button>
</form>

<?php elseif ($secao === 'turmas'): ?>
<!-- ═══ TURMAS ═══ -->
<h2>🥊 Minhas Turmas</h2>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Nome</th><th>Horário</th><th>Dia(s)</th></tr>
        </thead>
        <tbody>
        <?php foreach ($turmas as $t): ?>
            <tr>
                <td><?= htmlspecialchars($t['nome'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['horario'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($t['dia_semana'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
