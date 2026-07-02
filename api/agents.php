<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Models\Agent;

global $router;

$router->get('/api/agents', function() {
    $request = new Request();
    Auth::handle($request);
    
    $agentModel = new Agent();
    $records = $agentModel->all();
    
    foreach ($records as &$rec) {
        if (isset($rec['config']) && is_string($rec['config'])) {
            $rec['config'] = json_decode($rec['config'], true) ?? [];
        }
    }
    
    Response::json($records);
});

$router->get('/api/agents/:id', function($id) {
    $request = new Request();
    Auth::handle($request);
    
    $agentModel = new Agent();
    $agent = $agentModel->find($id);
    
    if (!$agent) {
        Response::error('Agente não encontrado', 404);
    }
    
    if (isset($agent['config']) && is_string($agent['config'])) {
        $agent['config'] = json_decode($agent['config'], true) ?? [];
    }
    
    Response::json($agent);
});

$router->post('/api/agents', function() {
    $request = new Request();
    $operator = Auth::handle($request);
    
    if ($operator['role'] !== 'admin') {
        Response::error('Acesso negado', 403);
    }
    
    $body = $request->getBody();
    if (empty($body['name']) || empty($body['provider']) || empty($body['model'])) {
        Response::error('Campos obrigatórios em falta: name, provider, model');
    }
    
    $agentModel = new Agent();
    
    $data = [
        'name' => $body['name'],
        'provider' => $body['provider'],
        'model' => $body['model'],
        'greeting_message' => $body['greeting_message'] ?? '',
        'system_prompt' => $body['system_prompt'] ?? '',
        'temperature' => isset($body['temperature']) ? (float)$body['temperature'] : 0.7,
        'max_tokens' => isset($body['max_tokens']) ? (int)$body['max_tokens'] : 2048,
        'config' => json_encode($body['config'] ?? new \stdClass())
    ];
    
    try {
        $newId = $agentModel->create($data);
        $agent = $agentModel->find($newId);
        if (isset($agent['config']) && is_string($agent['config'])) {
            $agent['config'] = json_decode($agent['config'], true) ?? [];
        }
        Response::json($agent, 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->put('/api/agents/:id', function($id) {
    $request = new Request();
    $operator = Auth::handle($request);
    
    if ($operator['role'] !== 'admin') {
        Response::error('Acesso negado', 403);
    }
    
    $agentModel = new Agent();
    $agent = $agentModel->find($id);
    if (!$agent) {
        Response::error('Agente não encontrado', 404);
    }
    
    $body = $request->getBody();
    
    $data = [];
    if (isset($body['name'])) $data['name'] = $body['name'];
    if (isset($body['provider'])) $data['provider'] = $body['provider'];
    if (isset($body['model'])) $data['model'] = $body['model'];
    if (isset($body['greeting_message'])) $data['greeting_message'] = $body['greeting_message'];
    if (isset($body['system_prompt'])) $data['system_prompt'] = $body['system_prompt'];
    if (isset($body['temperature'])) $data['temperature'] = (float)$body['temperature'];
    if (isset($body['max_tokens'])) $data['max_tokens'] = (int)$body['max_tokens'];
    if (isset($body['config'])) $data['config'] = json_encode($body['config']);
    
    try {
        $agentModel->update($id, $data);
        $updated = $agentModel->find($id);
        if (isset($updated['config']) && is_string($updated['config'])) {
            $updated['config'] = json_decode($updated['config'], true) ?? [];
        }
        Response::json($updated);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});

$router->delete('/api/agents/:id', function($id) {
    $request = new Request();
    $operator = Auth::handle($request);
    
    if ($operator['role'] !== 'admin') {
        Response::error('Acesso negado', 403);
    }
    
    $agentModel = new Agent();
    $agent = $agentModel->find($id);
    if (!$agent) {
        Response::error('Agente não encontrado', 404);
    }
    
    try {
        $agentModel->delete($id);
        Response::json(['success' => true]);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 500);
    }
});
