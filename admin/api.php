<?php

header('Content-Type: application/json');

use App\Core\Database;
use App\Models\Conversation;
use App\Models\Message;

$db = Database::getInstance();
$convModel = new Conversation();
$msgModel = new Message();

switch ($path) {
    case '/api/conversations':
        $stmt = $db->query("
            SELECT c.*, a.name as agent_name, o.name as operator_name,
                   (SELECT content FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_content,
                   (SELECT created_at FROM messages WHERE conversation_id = c.id ORDER BY created_at DESC LIMIT 1) as last_message_time
            FROM conversations c 
            LEFT JOIN agents a ON c.agent_id = a.id 
            LEFT JOIN operators o ON c.assigned_operator_id = o.id 
            ORDER BY c.updated_at DESC
        ");
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmtCount = $db->query("SELECT COUNT(*) FROM conversations WHERE status = 'waiting_operator'");
        $waitingCount = (int)$stmtCount->fetchColumn();
        
        echo json_encode([
            'status' => 'success',
            'conversations' => $conversations,
            'waiting_count' => $waitingCount
        ]);
        break;

    case '/api/messages':
        $conversationId = $_GET['conversation_id'] ?? '';
        $after = $_GET['after'] ?? '';
        
        if (empty($conversationId)) {
            echo json_encode(['status' => 'error', 'message' => 'ID da conversa é obrigatório']);
            exit;
        }

        $stmtConv = $db->prepare("
            SELECT c.*, o.name as operator_name 
            FROM conversations c 
            LEFT JOIN operators o ON c.assigned_operator_id = o.id 
            WHERE c.id = ? 
            LIMIT 1
        ");
        $stmtConv->execute([$conversationId]);
        $conv = $stmtConv->fetch(PDO::FETCH_ASSOC);
        
        if (!$conv) {
            echo json_encode(['status' => 'error', 'message' => 'Conversa não encontrada']);
            exit;
        }
        
        if (!empty($after)) {
            $stmtMsgs = $db->prepare("SELECT * FROM messages WHERE conversation_id = ? AND created_at > ? ORDER BY created_at ASC");
            $stmtMsgs->execute([$conversationId, $after]);
        } else {
            $stmtMsgs = $db->prepare("SELECT * FROM messages WHERE conversation_id = ? ORDER BY created_at ASC");
            $stmtMsgs->execute([$conversationId]);
        }
        $messages = $stmtMsgs->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'status' => 'success',
            'conversation' => $conv,
            'messages' => $messages
        ]);
        break;

    case '/api/send-message':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $conversationId = $data['conversation_id'] ?? '';
        $content = trim($data['content'] ?? '');
        
        if (empty($conversationId) || empty($content)) {
            echo json_encode(['status' => 'error', 'message' => 'Parâmetros inválidos']);
            exit;
        }
        
        $conv = $convModel->find($conversationId);
        if (!$conv || $conv['status'] === 'closed') {
            echo json_encode(['status' => 'error', 'message' => 'Conversa inválida ou fechada']);
            exit;
        }
        
        $myOperatorId = $_SESSION['operator']['id'];
        if ($conv['assigned_operator_id'] !== $myOperatorId || $conv['status'] !== 'active_operator') {
            echo json_encode(['status' => 'error', 'message' => 'Não tem permissão para enviar mensagens nesta conversa']);
            exit;
        }
        
        $msgId = $msgModel->create([
            'conversation_id' => $conversationId,
            'role' => 'assistant',
            'content' => $content,
            'sender_type' => 'operator'
        ]);
        
        $db->prepare("UPDATE conversations SET updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        
        echo json_encode([
            'status' => 'success',
            'message_id' => $msgId
        ]);
        break;

    case '/api/takeover':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $conversationId = $data['conversation_id'] ?? '';
        
        $conv = $convModel->find($conversationId);
        if (!$conv) {
            echo json_encode(['status' => 'error', 'message' => 'Conversa não encontrada']);
            exit;
        }
        
        $myOperatorId = $_SESSION['operator']['id'];
        $convModel->update($conversationId, [
            'status' => 'active_operator',
            'assigned_operator_id' => $myOperatorId,
            'updated_at' => date('Y-m-d H:i:s')
        ]);
        
        echo json_encode(['status' => 'success', 'message' => 'Conversa assumida com sucesso']);
        break;

    case '/api/release':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $conversationId = $data['conversation_id'] ?? '';
        
        $conv = $convModel->find($conversationId);
        if (!$conv) {
            echo json_encode(['status' => 'error', 'message' => 'Conversa não encontrada']);
            exit;
        }
        
        $db->prepare("UPDATE conversations SET status = 'active_bot', assigned_operator_id = NULL, updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        
        echo json_encode(['status' => 'success', 'message' => 'Conversa devolvida ao bot']);
        break;

    case '/api/close':
        $data = json_decode(file_get_contents('php://input'), true) ?? $_POST;
        $conversationId = $data['conversation_id'] ?? '';
        
        $conv = $convModel->find($conversationId);
        if (!$conv) {
            echo json_encode(['status' => 'error', 'message' => 'Conversa não encontrada']);
            exit;
        }
        
        $db->prepare("UPDATE conversations SET status = 'closed', updated_at = NOW() WHERE id = ?")->execute([$conversationId]);
        
        echo json_encode(['status' => 'success', 'message' => 'Conversa fechada com sucesso']);
        break;

    default:
        http_response_code(404);
        echo json_encode(['status' => 'error', 'message' => 'Endpoint não encontrado']);
        break;
}
