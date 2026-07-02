<?php

use App\Core\Database;

$conversationId = $_GET['id'] ?? '';

if (empty($conversationId)) {
    header('Location: conversations');
    exit;
}

$db = Database::getInstance();

$stmtConv = $db->prepare("
    SELECT c.*, a.name as agent_name, o.name as operator_name 
    FROM conversations c 
    LEFT JOIN agents a ON c.agent_id = a.id 
    LEFT JOIN operators o ON c.assigned_operator_id = o.id 
    WHERE c.id = ? 
    LIMIT 1
");
$stmtConv->execute([$conversationId]);
$conv = $stmtConv->fetch();

if (!$conv) {
    header('Location: conversations');
    exit;
}

$stmtMsgs = $db->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
$stmtMsgs->execute([$conversationId]);
$messages = $stmtMsgs->fetchAll();

$title = 'Conversa #' . substr($conv['session_id'], 0, 8) . ' - Chat SDK';
$pageTitle = 'Chat - Cliente #' . substr($conv['session_id'], 0, 8);
$activePage = 'conversations';

$myOperatorId = $_SESSION['operator']['id'];
$isAssignedToMe = ($conv['assigned_operator_id'] === $myOperatorId && $conv['status'] === 'active_operator');

$extraJs = '<script>const currentConversationId = ' . json_encode($conv['id']) . '; const myOperatorId = ' . json_encode($myOperatorId) . ';</script>';
$extraJs .= '<script src="/admin/assets/js/conversations.js"></script>';

ob_start();
?>

<div class="chat-container">
    <div class="chat-sidebar">
        <div class="chat-meta-box">
            <h3>Detalhes da Conversa</h3>
            <div class="meta-row">
                <span class="label">Sessão:</span>
                <span class="val"><?php echo htmlspecialchars($conv['session_id']); ?></span>
            </div>
            <div class="meta-row">
                <span class="label">Estado:</span>
                <span class="val" id="chat-status-badge">
                    <?php
                    $statusClass = '';
                    $statusText = '';
                    switch ($conv['status']) {
                        case 'active_bot':
                            $statusClass = 'badge badge-bot';
                            $statusText = 'Ativa com Bot';
                            break;
                        case 'waiting_operator':
                            $statusClass = 'badge badge-waiting';
                            $statusText = 'Aguardando Operador';
                            break;
                        case 'active_operator':
                            $statusClass = 'badge badge-operator';
                            $statusText = 'Com Operador (' . htmlspecialchars($conv['operator_name'] ?? '') . ')';
                            break;
                        case 'closed':
                            $statusClass = 'badge badge-closed';
                            $statusText = 'Fechada';
                            break;
                    }
                    ?>
                    <span class="<?php echo $statusClass; ?>"><?php echo $statusText; ?></span>
                </span>
            </div>
            <div class="meta-row">
                <span class="label">Agente:</span>
                <span class="val"><?php echo htmlspecialchars($conv['agent_name'] ?? 'Padrão'); ?></span>
            </div>
            <div class="meta-row">
                <span class="label">Criada em:</span>
                <span class="val"><?php echo date('d/m/Y H:i', strtotime($conv['created_at'])); ?></span>
            </div>
        </div>

        <div class="chat-actions-box">
            <h3>Ações do Operador</h3>
            <div class="action-buttons">
                <button class="btn btn-block btn-primary <?php echo $isAssignedToMe ? 'hidden' : ''; ?>" id="takeoverChatBtn" data-id="<?php echo htmlspecialchars($conv['id']); ?>">
                    Assumir Conversa
                </button>
                <button class="btn btn-block btn-outline <?php echo !$isAssignedToMe ? 'hidden' : ''; ?>" id="releaseChatBtn" data-id="<?php echo htmlspecialchars($conv['id']); ?>">
                    Devolver ao Bot
                </button>
                <button class="btn btn-block btn-danger <?php echo $conv['status'] === 'closed' ? 'hidden' : ''; ?>" id="closeChatBtn" data-id="<?php echo htmlspecialchars($conv['id']); ?>">
                    Fechar Conversa
                </button>
            </div>
        </div>
    </div>

    <div class="chat-area">
        <div class="chat-messages" id="chatMessages">
            <?php if (empty($messages)): ?>
                <div class="chat-system-message">
                    <span>Nenhuma mensagem trocada.</span>
                </div>
            <?php else: ?>
                <?php foreach ($messages as $msg): ?>
                    <?php
                    $senderClass = '';
                    $senderTitle = '';
                    switch ($msg['sender_type']) {
                        case 'client':
                            $senderClass = 'msg-client';
                            $senderTitle = 'Cliente';
                            break;
                        case 'bot':
                            $senderClass = 'msg-bot';
                            $senderTitle = 'Assistente Bot';
                            break;
                        case 'operator':
                            $senderClass = 'msg-operator';
                            $senderTitle = 'Operador Humano';
                            break;
                    }
                    ?>
                    <div class="message-row <?php echo $senderClass; ?>" data-time="<?php echo $msg['created_at']; ?>">
                        <div class="message-bubble">
                            <div class="message-meta"><?php echo $senderTitle; ?> • <?php echo date('H:i', strtotime($msg['created_at'])); ?></div>
                            <div class="message-content"><?php echo nl2br(htmlspecialchars($msg['content'])); ?></div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="chat-input-area <?php echo !$isAssignedToMe ? 'disabled' : ''; ?>" id="chatInputArea">
            <form id="sendMessageForm">
                <input type="text" id="messageTextInput" placeholder="Escreva a sua mensagem aqui..." autocomplete="off" <?php echo !$isAssignedToMe ? 'disabled' : ''; ?>>
                <button type="submit" class="btn btn-primary" <?php echo !$isAssignedToMe ? 'disabled' : ''; ?>>Enviar</button>
            </form>
            <div class="input-disabled-overlay <?php echo $isAssignedToMe ? 'hidden' : ''; ?>" id="inputOverlay">
                <span>Deve assumir a conversa para poder enviar mensagens.</span>
            </div>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
