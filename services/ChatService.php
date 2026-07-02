<?php

namespace App\Services;

use App\Models\Conversation;
use App\Models\Message;
use Exception;

class ChatService
{
    public function getOrCreateConversation(string $agentId, string $sessionId): array
    {
        $conversationModel = new Conversation();
        $records = $conversationModel->where([
            'agent_id' => $agentId,
            'session_id' => $sessionId
        ]);

        if (!empty($records)) {
            return $records[0];
        }

        $newId = $conversationModel->create([
            'agent_id' => $agentId,
            'session_id' => $sessionId,
            'status' => 'active_bot',
            'metadata' => '{}'
        ]);

        $conversation = $conversationModel->find($newId);
        if (!$conversation) {
            throw new Exception('Erro ao criar conversa');
        }

        return $conversation;
    }

    public function getConversation(string $id): ?array
    {
        $conversationModel = new Conversation();
        return $conversationModel->find($id);
    }

    public function getMessages(string $conversationId): array
    {
        $messageModel = new Message();
        return $messageModel->where(['conversation_id' => $conversationId], 'created_at ASC');
    }

    public function createMessage(array $data): array
    {
        $messageModel = new Message();
        $newId = $messageModel->create([
            'conversation_id' => $data['conversation_id'],
            'role' => $data['role'],
            'content' => $data['content'],
            'sender_type' => $data['sender_type'],
            'tool_calls' => $data['tool_calls'] ?? null
        ]);

        $message = $messageModel->find($newId);
        if (!$message) {
            throw new Exception('Erro ao criar mensagem');
        }

        return $message;
    }
}
