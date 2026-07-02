<?php

use App\Core\Database;

$db = Database::getInstance();

$stmt = $db->query("
    SELECT c.*, a.name as agent_name, o.name as operator_name,
           (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_content,
           (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
    FROM conversations c 
    LEFT JOIN agents a ON c.agent_id = a.id 
    LEFT JOIN operators o ON c.assigned_operator_id = o.id 
    ORDER BY c.updated_at DESC
");
$conversations = $stmt->fetchAll();

$title = 'Gestão de Conversas - Chat SDK Admin';
$pageTitle = 'Conversas';
$activePage = 'conversations';

$extraJs = '<script src="/admin/assets/js/conversations.js"></script>';

ob_start();
?>

<div class="conversations-container">
    <div class="filters-bar">
        <button class="filter-btn active" data-filter="all">Todas</button>
        <button class="filter-btn" data-filter="waiting_operator">
            Aguardando <span class="badge badge-waiting badge-count" id="count-waiting">0</span>
        </button>
        <button class="filter-btn" data-filter="active_operator">Em Conversa</button>
        <button class="filter-btn" data-filter="active_bot">Com Bot</button>
        <button class="filter-btn" data-filter="closed">Fechadas</button>
    </div>

    <div class="conversations-list" id="conversationsList">
        <?php if (empty($conversations)): ?>
            <p class="empty-text">Nenhuma conversa encontrada.</p>
        <?php else: ?>
            <?php foreach ($conversations as $conv): ?>
                <div class="conversation-card" data-id="<?php echo htmlspecialchars($conv['id']); ?>" data-status="<?php echo htmlspecialchars($conv['status']); ?>">
                    <div class="card-header">
                        <span class="session-id">Cliente #<?php echo htmlspecialchars(substr($conv['session_id'], 0, 8)); ?></span>
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
                        <span class="status-badge <?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                    </div>
                    <div class="card-body">
                        <p class="last-message">
                            <?php echo htmlspecialchars($conv['last_message_content'] ?? 'Nenhuma mensagem trocada ainda.'); ?>
                        </p>
                    </div>
                    <div class="card-footer">
                        <span class="time-elapsed">
                            <?php echo $conv['last_message_time'] ? date('H:i d/m/Y', strtotime($conv['last_message_time'])) : date('H:i d/m/Y', strtotime($conv['updated_at'])); ?>
                        </span>
                        <div class="card-actions">
                            <?php if ($conv['status'] === 'waiting_operator' || $conv['status'] === 'active_bot'): ?>
                                <button class="btn btn-sm btn-primary takeover-btn" data-id="<?php echo htmlspecialchars($conv['id']); ?>">Assumir</button>
                            <?php endif; ?>
                            <a href="/admin/conversation-detail?id=<?php echo urlencode($conv['id']); ?>" class="btn btn-sm btn-outline">Ver Chat</a>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
