<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Models\Conversation;
use App\Models\Agent;
use App\Services\ChatService;
use App\Services\IntentService;
use App\Services\HandoverService;
use App\Services\MemoryService;
use App\Core\LLMRouter;

global $router;

$router->get('/api/conversations', function() {
    $request = new Request();
    Auth::handle($request);
    
    $conditions = [];
    $sessionId = $request->query('sessionId');
    $status = $request->query('status');
    
    if ($sessionId !== null && $sessionId !== '') {
        $conditions['session_id'] = $sessionId;
    }
    if ($status !== null && $status !== '') {
        $conditions['status'] = $status;
    }
    
    $conversationModel = new Conversation();
    $records = $conversationModel->where($conditions, 'updated_at DESC');
    
    foreach ($records as &$rec) {
        if (isset($rec['metadata']) && is_string($rec['metadata'])) {
            $rec['metadata'] = json_decode($rec['metadata'], true) ?? [];
        }
    }
    
    Response::json($records);
});

$router->get('/api/conversations/:id', function($id) {
    $request = new Request();
    Auth::handle($request);
    
    $chatService = new ChatService();
    $conversation = $chatService->getConversation($id);
    
    if (!$conversation) {
        Response::error('Conversa não encontrada', 404);
    }
    
    if (isset($conversation['metadata']) && is_string($conversation['metadata'])) {
        $conversation['metadata'] = json_decode($conversation['metadata'], true) ?? [];
    }
    
    Response::json($conversation);
});

$router->get('/api/conversations/:id/messages', function($id) {
    $request = new Request();
    Auth::handle($request);
    
    $chatService = new ChatService();
    $messages = $chatService->getMessages($id);
    
    foreach ($messages as &$msg) {
        if (isset($msg['tool_calls']) && is_string($msg['tool_calls'])) {
            $msg['tool_calls'] = json_decode($msg['tool_calls'], true) ?? null;
        }
    }
    
    Response::json($messages);
});

$router->post('/api/chat', function() {
    $request = new Request();
    $body = $request->getBody();
    
    $sessionId = $body['sessionId'] ?? null;
    $agentId = $body['agentId'] ?? null;
    $content = $body['content'] ?? null;
    
    if (!$sessionId || !$agentId || !$content) {
        Response::error('sessionId, agentId, and content are required', 400);
    }
    
    try {
        $agentModel = new Agent();
        $agent = $agentModel->find($agentId);
        if (!$agent) {
            Response::error('Agent not found', 404);
        }
        
        $chatService = new ChatService();
        $conversation = $chatService->getOrCreateConversation($agentId, $sessionId);
        
        $chatService->createMessage([
            'conversation_id' => $conversation['id'],
            'role' => 'user',
            'content' => $content,
            'sender_type' => 'user'
        ]);
        
        if (
            $conversation['status'] === 'active_human' ||
            $conversation['status'] === 'handover_requested' ||
            $conversation['status'] === 'waiting_for_agent'
        ) {
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            echo json_encode(['type' => 'status', 'status' => $conversation['status']]) . "\n";
            exit;
        }
        
        $history = $chatService->getMessages($conversation['id']);
        
        $intentService = new IntentService();
        $intent = $intentService->detectIntent(
            $agent['provider'],
            $agent['model'],
            $content,
            $history
        );
        
        if ($intent === 'human_handover' || $intent === 'frustration') {
            $handoverService = new HandoverService();
            $handoverService->requestHandover($conversation['id']);
            
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            echo json_encode(['type' => 'status', 'status' => 'handover_requested']) . "\n";
            exit;
        }
        
        $memoryService = new MemoryService();
        $systemPromptWithMemory = $memoryService->injectMemory($agent['system_prompt'], $sessionId);
        
        $llmResponse = LLMRouter::call(
            $agent['provider'],
            $agent['model'],
            $systemPromptWithMemory,
            $history,
            (float)($agent['temperature'] ?? 0.7),
            (int)($agent['max_tokens'] ?? 2048)
        );
        
        $chatService->createMessage([
            'conversation_id' => $conversation['id'],
            'role' => 'assistant',
            'content' => $llmResponse,
            'sender_type' => 'bot'
        ]);
        
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        echo json_encode(['type' => 'text_delta', 'content' => $llmResponse]) . "\n";
        echo json_encode(['type' => 'finish', 'reason' => 'stop']) . "\n";
        exit;
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});
