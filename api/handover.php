<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Services\HandoverService;
use App\Services\ChatService;

global $router;

$router->post('/api/handover/:conversationId/request', function($conversationId) {
    $request = new Request();
    Auth::handle($request);
    
    try {
        $handoverService = new HandoverService();
        $handoverService->requestHandover($conversationId);
        Response::json(['success' => true]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->post('/api/handover/:conversationId/assign', function($conversationId) {
    $request = new Request();
    $operator = Auth::handle($request);
    
    try {
        $handoverService = new HandoverService();
        $handoverService->assignOperator($conversationId, $operator['id']);
        Response::json(['success' => true]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->post('/api/handover/:conversationId/reply', function($conversationId) {
    $request = new Request();
    $operator = Auth::handle($request);
    
    $body = $request->getBody();
    if (empty($body['content'])) {
        Response::error('Content is required', 400);
    }
    
    try {
        $chatService = new ChatService();
        $msg = $chatService->createMessage([
            'conversation_id' => $conversationId,
            'role' => 'operator',
            'content' => $body['content'],
            'sender_type' => 'human'
        ]);
        
        Response::json($msg);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->post('/api/handover/:conversationId/resolve', function($conversationId) {
    $request = new Request();
    Auth::handle($request);
    
    try {
        $handoverService = new HandoverService();
        $handoverService->resolveConversation($conversationId);
        Response::json(['success' => true]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});
