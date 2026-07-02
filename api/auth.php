<?php

use App\Core\Request;
use App\Core\Response;
use App\Core\Auth;
use App\Services\AuthService;

global $router;

$router->post('/api/auth/register', function() {
    $request = new Request();
    $body = $request->getBody();
    
    if (empty($body['name']) || empty($body['email']) || empty($body['password'])) {
        Response::error('Campos obrigatórios em falta: name, email, password');
    }
    
    try {
        $authService = new AuthService();
        $operator = $authService->register($body);
        Response::json(['operator' => $operator], 201);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/api/auth/login', function() {
    $request = new Request();
    $body = $request->getBody();
    
    if (empty($body['email']) || empty($body['password'])) {
        Response::error('E-mail e palavra-passe são obrigatórios');
    }
    
    try {
        $authService = new AuthService();
        $result = $authService->login($body);
        Response::json($result);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 401);
    }
});

$router->post('/api/auth/2fa/setup', function() {
    $request = new Request();
    $operator = Auth::handle($request);
    
    try {
        $authService = new AuthService();
        $result = $authService->setup2Fa($operator['id']);
        Response::json($result);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
});

$router->post('/api/auth/2fa/verify', function() {
    $request = new Request();
    $operator = Auth::handle($request);
    $body = $request->getBody();
    
    if (empty($body['token'])) {
        Response::error('Token é obrigatório', 400);
    }
    
    try {
        $authService = new AuthService();
        $result = $authService->verify2Fa($operator['id'], $body['token']);
        Response::json($result);
    } catch (Exception $e) {
        Response::error($e->getMessage(), 400);
    }
});
