<?php

require_once __DIR__ . '/core/Autoloader.php';

use App\Core\Request;
use App\Core\Router;
use App\Core\Response;

$request = new Request();
$router = new Router();

$router->get('/api/test', function() {
    Response::json(['status' => 'ok', 'message' => 'Chat SDK PHP API is running']);
});

require_once __DIR__ . '/api/auth.php';
require_once __DIR__ . '/api/agents.php';
require_once __DIR__ . '/api/conversations.php';
require_once __DIR__ . '/api/handover.php';
require_once __DIR__ . '/api/settings.php';
require_once __DIR__ . '/api/polling.php';

$router->dispatch($request->getMethod(), $request->getUri());
