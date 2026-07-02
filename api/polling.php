<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Core\Database;

global $router;

$router->get('/api/polling/conversations', function() {
    $request = new Request();
    Auth::handle($request);
    
    $since = $request->query('since');
    if (!$since) {
        $since = date('Y-m-d H:i:s', time() - 300);
    }
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM conversations WHERE updated_at > :since ORDER BY updated_at DESC");
        $stmt->execute(['since' => $since]);
        $records = $stmt->fetchAll();
        
        foreach ($records as &$rec) {
            if (isset($rec['metadata']) && is_string($rec['metadata'])) {
                $rec['metadata'] = json_decode($rec['metadata'], true) ?? [];
            }
        }
        
        Response::json($records);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->get('/api/polling/messages', function() {
    $request = new Request();
    Auth::handle($request);
    
    $conversationId = $request->query('conversationId');
    if (!$conversationId) {
        Response::error('O parâmetro conversationId é obrigatório', 400);
    }
    
    $since = $request->query('since');
    if (!$since) {
        $since = date('Y-m-d H:i:s', time() - 300);
    }
    
    try {
        $db = Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM messages WHERE conversation_id = :conversationId AND created_at > :since ORDER BY created_at ASC");
        $stmt->execute([
            'conversationId' => $conversationId,
            'since' => $since
        ]);
        $records = $stmt->fetchAll();
        
        foreach ($records as &$rec) {
            if (isset($rec['tool_calls']) && is_string($rec['tool_calls'])) {
                $rec['tool_calls'] = json_decode($rec['tool_calls'], true) ?? null;
            }
        }
        
        Response::json($records);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});
