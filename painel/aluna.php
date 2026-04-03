<?php
/**
 * aluna.php — Painel da Aluna (Guerreiras Thai).
 *
 * Funcionalidades:
 *   - Ver perfil
 *   - Ver matrículas e turmas
 *   - Ver pagamentos
 *   - Ver frequência
 *   - Ver conquistas
 *   - Ver evolução física
 */

require_once __DIR__ . '/auth.php';
exigirLogin(['admin', 'treinadora', 'aluna']);
require_once __DIR__ . '/../conexao.php';

$uid = usuarioId();

// ── Dados do perfil ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$stmt->execute([':id' => $uid]);
$perfil = $stmt->fetch();

// ── Matrículas ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT m.id, m.data_inicio, m.status, t.nome AS turma, t.horario, t.dia_semana
    FROM matriculas m
    JOIN turmas t ON m.turma_id = t.id
    WHERE m.usuario_id = :uid
    ORDER BY m.data_inicio DESC
");
$stmt->execute([':uid' => $uid]);
$matriculas = $stmt->fetchAll();

// ── Pagamentos ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT p.valor, p.data_venc, p.data_pgto, p.metodo, p.status, t.nome AS turma
    FROM pagamentos p
    JOIN matriculas m ON p.matricula_id = m.id
    JOIN turmas t ON m.turma_id = t.id
    WHERE m.usuario_id = :uid
    ORDER BY p.data_venc DESC
    LIMIT 20
");
$stmt->execute([':uid' => $uid]);
$pagamentos = $stmt->fetchAll();

// ── Frequência (últimos 30 registros) ───────────────────────────────────
$stmt = $pdo->prepare("
    SELECT f.data_aula, f.presente, t.nome AS turma
    FROM frequencia f
    JOIN turmas t ON f.turma_id = t.id
    WHERE f.usuario_id = :uid
    ORDER BY f.data_aula DESC
    LIMIT 30
");
$stmt->execute([':uid' => $uid]);
$frequencia = $stmt->fetchAll();

// ── Conquistas ──────────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT c.conquista, c.data_conquista, tc.nome AS tecnica, tc.categoria
    FROM aluna_conquistas c
    LEFT JOIN tecnicas_checklist tc ON c.tecnica_id = tc.id
    WHERE c.usuario_id = :uid
    ORDER BY c.data_conquista DESC
");
$stmt->execute([':uid' => $uid]);
$conquistas = $stmt->fetchAll();

// ── Evolução física ─────────────────────────────────────────────────────
$stmt = $pdo->prepare("
    SELECT data_registro, peso_kg, altura_cm, bf_percent, cintura_cm, quadril_cm, braco_cm, coxa_cm, restricoes_med, observacoes
    FROM evolucao_fisica
    WHERE usuario_id = :uid
    ORDER BY data_registro DESC
    LIMIT 10
");
$stmt->execute([':uid' => $uid]);
$evolucao = $stmt->fetchAll();

// Stats
$totalAulas    = 0;
$totalPresencas = 0;
foreach ($frequencia as $f) {
    $totalAulas++;
    if ($f['presente']) {
        $totalPresencas++;
    }
}

$secao = $_GET['s'] ?? 'resumo';

$tituloPagina = 'Meu Painel';
require_once __DIR__ . '/header.php';
?>

<div style="display:flex; gap:.5rem; flex-wrap:wrap; margin-bottom:1.5rem;">
    <a href="?s=resumo"     class="btn <?= $secao === 'resumo'     ? 'btn-roxo' : 'btn-cinza' ?>">🏠 Resumo</a>
    <a href="?s=pagamentos" class="btn <?= $secao === 'pagamentos' ? 'btn-roxo' : 'btn-cinza' ?>">💰 Pagamentos</a>
    <a href="?s=frequencia" class="btn <?= $secao === 'frequencia' ? 'btn-roxo' : 'btn-cinza' ?>">📋 Frequência</a>
    <a href="?s=conquistas" class="btn <?= $secao === 'conquistas' ? 'btn-roxo' : 'btn-cinza' ?>">🏆 Conquistas</a>
    <a href="?s=evolucao"   class="btn <?= $secao === 'evolucao'   ? 'btn-roxo' : 'btn-cinza' ?>">📈 Evolução</a>
</div>

<?php if ($secao === 'resumo'): ?>
<!-- ═══ RESUMO ═══ -->
<h2>🏠 Olá, <?= htmlspecialchars($perfil['nome'], ENT_QUOTES, 'UTF-8') ?>!</h2>

<div class="stats-grid">
    <div class="stat-card">
        <div class="icon">🥊</div>
        <div class="number"><?= count($matriculas) ?></div>
        <div class="label">Matrículas</div>
    </div>
    <div class="stat-card">
        <div class="icon">📋</div>
        <div class="number"><?= $totalPresencas ?>/<?= $totalAulas ?></div>
        <div class="label">Presenças</div>
    </div>
    <div class="stat-card">
        <div class="icon">🏆</div>
        <div class="number"><?= count($conquistas) ?></div>
        <div class="label">Conquistas</div>
    </div>
</div>

<?php if (!empty($matriculas)): ?>
<div class="section">
    <h2>📌 Minhas Turmas</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Turma</th><th>Horário</th><th>Dia(s)</th><th>Início</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($matriculas as $m): ?>
                <tr>
                    <td><?= htmlspecialchars($m['turma'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($m['horario'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($m['dia_semana'] ?? '—', ENT_QUOTES, 'UTF-8') ?></td>
                    <td><?= htmlspecialchars($m['data_inicio'], ENT_QUOTES, 'UTF-8') ?></td>
                    <td><span class="badge badge--<?= htmlspecialchars($m['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($m['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

<?php elseif ($secao === 'pagamentos'): ?>
<!-- ═══ PAGAMENTOS ═══ -->
<h2>💰 Meus Pagamentos</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>Turma</th><th>Valor</th><th>Vencimento</th><th>Pagamento</th><th>Método</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($pagamentos as $pg): ?>
            <tr>
                <td><?= htmlspecialchars($pg['turma'], ENT_QUOTES, 'UTF-8') ?></td>
                <td>R$ <?= number_format((float) $pg['valor'], 2, ',', '.') ?></td>
                <td><?= htmlspecialchars($pg['data_venc'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $pg['data_pgto'] ? htmlspecialchars($pg['data_pgto'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= $pg['metodo'] ? htmlspecialchars($pg['metodo'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><span class="badge badge--<?= htmlspecialchars($pg['status'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($pg['status'], ENT_QUOTES, 'UTF-8') ?></span></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($pagamentos)): ?>
            <tr><td colspan="6" style="text-align:center; color:var(--cinza); padding:2rem;">Nenhum pagamento encontrado.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($secao === 'frequencia'): ?>
<!-- ═══ FREQUÊNCIA ═══ -->
<h2>📋 Minha Frequência</h2>
<div class="table-wrap">
    <table>
        <thead><tr><th>Data</th><th>Turma</th><th>Presença</th></tr></thead>
        <tbody>
        <?php foreach ($frequencia as $f): ?>
            <tr>
                <td><?= htmlspecialchars($f['data_aula'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($f['turma'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $f['presente'] ? '✅ Presente' : '❌ Ausente' ?></td>
            </tr>
        <?php endforeach; ?>
        <?php if (empty($frequencia)): ?>
            <tr><td colspan="3" style="text-align:center; color:var(--cinza); padding:2rem;">Nenhum registro de frequência.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php elseif ($secao === 'conquistas'): ?>
<!-- ═══ CONQUISTAS ═══ -->
<h2>🏆 Minhas Conquistas</h2>
<?php if (!empty($conquistas)): ?>
<div class="table-wrap">
    <table>
        <thead><tr><th>Data</th><th>Conquista</th><th>Técnica</th><th>Categoria</th></tr></thead>
        <tbody>
        <?php foreach ($conquistas as $c): ?>
            <tr>
                <td><?= htmlspecialchars($c['data_conquista'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= htmlspecialchars($c['conquista'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $c['tecnica'] ? htmlspecialchars($c['tecnica'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
                <td><?= $c['categoria'] ? htmlspecialchars($c['categoria'], ENT_QUOTES, 'UTF-8') : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php else: ?>
    <p style="color:var(--cinza); padding:1rem;">Nenhuma conquista registrada ainda. Continue treinando! 🥊</p>
<?php endif; ?>

<?php elseif ($secao === 'evolucao'): ?>
<!-- ═══ EVOLUÇÃO FÍSICA ═══ -->
<h2>📈 Evolução Física</h2>
<?php if (!empty($evolucao)): ?>
<div class="table-wrap">
    <table>
        <thead>
            <tr><th>Data</th><th>Peso</th><th>Altura</th><th>BF%</th><th>Cintura</th><th>Quadril</th><th>Braço</th><th>Coxa</th></tr>
        </thead>
        <tbody>
        <?php foreach ($evolucao as $ev): ?>
            <tr>
                <td><?= htmlspecialchars($ev['data_registro'], ENT_QUOTES, 'UTF-8') ?></td>
                <td><?= $ev['peso_kg'] ? number_format((float) $ev['peso_kg'], 1, ',', '.') . ' kg' : '—' ?></td>
                <td><?= $ev['altura_cm'] ? number_format((float) $ev['altura_cm'], 1, ',', '.') . ' cm' : '—' ?></td>
                <td><?= $ev['bf_percent'] ? number_format((float) $ev['bf_percent'], 1, ',', '.') . '%' : '—' ?></td>
                <td><?= $ev['cintura_cm'] ? number_format((float) $ev['cintura_cm'], 1, ',', '.') . ' cm' : '—' ?></td>
                <td><?= $ev['quadril_cm'] ? number_format((float) $ev['quadril_cm'], 1, ',', '.') . ' cm' : '—' ?></td>
                <td><?= $ev['braco_cm'] ? number_format((float) $ev['braco_cm'], 1, ',', '.') . ' cm' : '—' ?></td>
                <td><?= $ev['coxa_cm'] ? number_format((float) $ev['coxa_cm'], 1, ',', '.') . ' cm' : '—' ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if (!empty($evolucao[0]['restricoes_med'])): ?>
    <div class="form-inline" style="margin-top:1rem;">
        <label>Restrições médicas</label>
        <p style="color:var(--cinza); margin-top:.3rem;"><?= htmlspecialchars($evolucao[0]['restricoes_med'], ENT_QUOTES, 'UTF-8') ?></p>
    </div>
<?php endif; ?>
<?php else: ?>
    <p style="color:var(--cinza); padding:1rem;">Nenhum registro de evolução física encontrado.</p>
<?php endif; ?>
<?php endif; ?>

<?php require_once __DIR__ . '/footer.php'; ?>
