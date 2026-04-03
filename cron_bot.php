<?php
/**
 * cron_bot.php — Processador da Fila de Notificações (WhatsApp Bot).
 *
 * Este script lê mensagens pendentes da tabela `fila_notificacoes`,
 * dispara via cURL (simulando integração com API de WhatsApp) e
 * atualiza o status para "enviada" ou "erro".
 *
 * ─── CONFIGURAÇÃO NO CPANEL ──────────────────────────────────────
 * Adicione o seguinte Cron Job (a cada 5 minutos):
 *
 *   * /5 * * * * /usr/local/bin/php /home/iubsit15/public_html/cron_bot.php >> /home/iubsit15/logs/cron_bot.log 2>&1
 *
 * Ajuste o caminho conforme a estrutura de diretórios da sua hospedagem.
 * ──────────────────────────────────────────────────────────────────
 */

// Impedir acesso via navegador (apenas CLI)
if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    exit('Acesso negado.');
}

require_once __DIR__ . '/conexao.php';

// ── Configuração da API de WhatsApp (placeholder) ────────────────
define('WHATSAPP_API_URL', 'https://api.exemplo.com/send-message');
define('WHATSAPP_API_TOKEN', 'SEU_TOKEN_AQUI');
define('MAX_TENTATIVAS', 3);
define('LOTE_MAXIMO', 50);

/**
 * Envia uma mensagem via cURL para a API de WhatsApp.
 *
 * @param  string $whatsapp Número de WhatsApp do destinatário.
 * @param  string $mensagem Texto da mensagem.
 * @return bool   true se a API respondeu com sucesso, false caso contrário.
 */
function enviarWhatsApp(string $whatsapp, string $mensagem): bool
{
    $payload = json_encode([
        'phone'   => $whatsapp,
        'message' => $mensagem,
    ]);

    $ch = curl_init(WHATSAPP_API_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . WHATSAPP_API_TOKEN,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_CONNECTTIMEOUT => 10,
    ]);

    $response = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error) {
        error_log("[cron_bot] cURL error: $error");
        return false;
    }

    return $httpCode >= 200 && $httpCode < 300;
}

// ── Buscar notificações pendentes ────────────────────────────────
$stmt = $pdo->prepare("
    SELECT fn.id, fn.mensagem, fn.tentativas, u.whatsapp, u.nome
    FROM fila_notificacoes fn
    JOIN usuarios u ON u.id = fn.usuario_id
    WHERE fn.status = 'pendente'
      AND fn.tentativas < :max_tentativas
    ORDER BY fn.criado_em ASC
    LIMIT :lote
");
$stmt->bindValue(':max_tentativas', MAX_TENTATIVAS, PDO::PARAM_INT);
$stmt->bindValue(':lote', LOTE_MAXIMO, PDO::PARAM_INT);
$stmt->execute();

$notificacoes = $stmt->fetchAll();

if (empty($notificacoes)) {
    echo date('Y-m-d H:i:s') . " — Nenhuma notificação pendente.\n";
    exit(0);
}

echo date('Y-m-d H:i:s') . " — Processando " . count($notificacoes) . " notificação(ões)...\n";

// ── Preparar statements de atualização ───────────────────────────
$stmtSucesso = $pdo->prepare("
    UPDATE fila_notificacoes
    SET status = 'enviada', enviado_em = NOW(), tentativas = tentativas + 1
    WHERE id = :id
");

$stmtErro = $pdo->prepare("
    UPDATE fila_notificacoes
    SET tentativas = tentativas + 1,
        status = CASE WHEN tentativas + 1 >= :max THEN 'erro' ELSE 'pendente' END
    WHERE id = :id
");

// ── Processar fila ───────────────────────────────────────────────
$enviadas = 0;
$erros    = 0;

foreach ($notificacoes as $n) {
    $whatsapp = $n['whatsapp'];

    if (empty($whatsapp)) {
        echo "  ⚠ ID {$n['id']}: usuário sem WhatsApp — ignorado.\n";
        $stmtErro->execute([':id' => $n['id'], ':max' => MAX_TENTATIVAS]);
        $erros++;
        continue;
    }

    $mensagemPersonalizada = "Olá, {$n['nome']}! {$n['mensagem']}";

    if (enviarWhatsApp($whatsapp, $mensagemPersonalizada)) {
        $stmtSucesso->execute([':id' => $n['id']]);
        echo "  ✔ ID {$n['id']}: enviada para $whatsapp\n";
        $enviadas++;
    } else {
        $stmtErro->execute([':id' => $n['id'], ':max' => MAX_TENTATIVAS]);
        echo "  ✖ ID {$n['id']}: falha ao enviar para $whatsapp (tentativa {$n['tentativas']})\n";
        $erros++;
    }
}

echo date('Y-m-d H:i:s') . " — Concluído: $enviadas enviada(s), $erros erro(s).\n";
