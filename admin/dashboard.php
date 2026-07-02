<?php

use App\Core\Database;

$db = Database::getInstance();

$stmt = $db->query("SELECT status, COUNT(*) as total FROM conversations GROUP BY status");
$statusCounts = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

$totalBot = $statusCounts['active_bot'] ?? 0;
$totalWaiting = $statusCounts['waiting_operator'] ?? 0;
$totalOperator = $statusCounts['active_operator'] ?? 0;
$totalClosed = $statusCounts['closed'] ?? 0;

$stmtMsg = $db->query("SELECT COUNT(*) FROM messages");
$totalMessages = $stmtMsg->fetchColumn() ?: 0;

$stmtRecent = $db->query("
    SELECT c.*, a.name as agent_name, o.name as operator_name 
    FROM conversations c 
    LEFT JOIN agents a ON c.agent_id = a.id 
    LEFT JOIN operators o ON c.assigned_operator_id = o.id 
    ORDER BY c.updated_at DESC 
    LIMIT 5
");
$recentConversations = $stmtRecent->fetchAll();

$title = 'Dashboard - Chat SDK Admin';
$pageTitle = 'Dashboard';
$activePage = 'dashboard';

ob_start();
?>

<div class="metrics-grid">
    <div class="metric-card">
        <div class="metric-icon bot-icon">🤖</div>
        <div class="metric-info">
            <h3>Ativas com Bot</h3>
            <p class="metric-value"><?php echo $totalBot; ?></p>
        </div>
    </div>
    
    <div class="metric-card alert-card">
        <div class="metric-icon alert-icon">⏳</div>
        <div class="metric-info">
            <h3>Espera de Operador</h3>
            <p class="metric-value"><?php echo $totalWaiting; ?></p>
        </div>
    </div>
    
    <div class="metric-card active-card">
        <div class="metric-icon operator-icon">👤</div>
        <div class="metric-info">
            <h3>Ativas com Humano</h3>
            <p class="metric-value"><?php echo $totalOperator; ?></p>
        </div>
    </div>
    
    <div class="metric-card closed-card">
        <div class="metric-icon close-icon">✅</div>
        <div class="metric-info">
            <h3>Conversas Fechadas</h3>
            <p class="metric-value"><?php echo $totalClosed; ?></p>
        </div>
    </div>
</div>

<div class="dashboard-details">
    <div class="card-box">
        <div class="card-box-header">
            <h2>Conversas Recentes</h2>
        </div>
        <div class="card-box-content">
            <?php if (empty($recentConversations)): ?>
                <p class="empty-text">Nenhuma conversa recente encontrada.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Sessão</th>
                            <th>Estado</th>
                            <th>Agente</th>
                            <th>Operador</th>
                            <th>Última Atualização</th>
                            <th>Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentConversations as $conv): ?>
                            <tr>
                                <td><?php echo htmlspecialchars(substr($conv['session_id'], 0, 12) . (strlen($conv['session_id']) > 12 ? '...' : '')); ?></td>
                                <td>
                                    <?php
                                    $statusClass = '';
                                    $statusText = '';
                                    switch ($conv['status']) {
                                        case 'active_bot':
                                            $statusClass = 'badge badge-bot';
                                            $statusText = 'Bot';
                                            break;
                                        case 'waiting_operator':
                                            $statusClass = 'badge badge-waiting';
                                            $statusText = 'Aguardando';
                                            break;
                                        case 'active_operator':
                                            $statusClass = 'badge badge-operator';
                                            $statusText = 'Operador';
                                            break;
                                        case 'closed':
                                            $statusClass = 'badge badge-closed';
                                            $statusText = 'Fechado';
                                            break;
                                    }
                                    ?>
                                    <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                                </td>
                                <td><?php echo htmlspecialchars($conv['agent_name'] ?? 'Padrão'); ?></td>
                                <td><?php echo htmlspecialchars($conv['operator_name'] ?? 'Nenhum'); ?></td>
                                <td><?php echo date('d/m/Y H:i', strtotime($conv['updated_at'])); ?></td>
                                <td>
                                    <a href="/admin/conversation-detail?id=<?php echo urlencode($conv['id']); ?>" class="btn btn-sm btn-outline">Abrir</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
