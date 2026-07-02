<?php

require_once dirname(__DIR__) . '/core/Autoloader.php';

use App\Core\Request;
use App\Models\Conversation;
use App\Models\Agent;
use App\Models\Message;
use App\Providers\LlmRouter;

if (function_exists('apache_setenv')) {
    apache_setenv('no-gzip', '1');
}
ini_set('zlib.output_compression', '0');
ini_set('implicit_flush', '1');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');

$request = new Request();
$conversationId = $request->get('conversation_id') ?? $request->query('conversation_id');
$messageText = $request->get('message') ?? $request->query('message');

if (empty($conversationId)) {
    echo "event: error\ndata: " . json_encode(['error' => 'ID da conversacao em falta']) . "\n\n";
    flush();
    exit;
}

$conversationModel = new Conversation();
$conversation = $conversationModel->find($conversationId);

if (!$conversation) {
    echo "event: error\ndata: " . json_encode(['error' => 'Conversacao nao encontrada']) . "\n\n";
    flush();
    exit;
}

$agentModel = new Agent();
$agent = $agentModel->find($conversation['agent_id']);

if (!$agent) {
    echo "event: error\ndata: " . json_encode(['error' => 'Agente nao encontrado']) . "\n\n";
    flush();
    exit;
}

$messageModel = new Message();

if (!empty($messageText)) {
    $messageModel->create([
        'conversation_id' => $conversationId,
        'role' => 'user',
        'content' => $messageText,
        'sender_type' => 'user'
    ]);
}

$allMessages = $messageModel->where(['conversation_id' => $conversationId], 'created_at ASC');

if (empty($allMessages)) {
    echo "event: error\ndata: " . json_encode(['error' => 'Historico de mensagens vazio']) . "\n\n";
    flush();
    exit;
}

$messagesForLlm = [];
if (!empty($agent['system_prompt'])) {
    $messagesForLlm[] = [
        'role' => 'system',
        'content' => $agent['system_prompt']
    ];
}

foreach ($allMessages as $msg) {
    $messagesForLlm[] = [
        'role' => $msg['role'],
        'content' => $msg['content']
    ];
}

$router = new LlmRouter();
$generator = $router->route($agent, $messagesForLlm);

$fullResponseText = '';

foreach ($generator as $event) {
    if ($event['type'] === 'text_delta') {
        $fullResponseText .= $event['content'];
        echo "data: " . json_encode(['text' => $event['content']]) . "\n\n";
    } elseif ($event['type'] === 'error') {
        echo "event: error\ndata: " . json_encode(['error' => $event['content']]) . "\n\n";
    } elseif ($event['type'] === 'finish') {
        echo "data: [DONE]\n\n";
    }

    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

if (!empty($fullResponseText)) {
    $messageModel->create([
        'conversation_id' => $conversationId,
        'role' => 'assistant',
        'content' => $fullResponseText,
        'sender_type' => 'bot'
    ]);
}
