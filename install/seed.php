<?php

$pdoInstance = null;

if (isset($pdo) && $pdo instanceof PDO) {
    $pdoInstance = $pdo;
} else {
    $configPath = __DIR__ . '/../config/database.php';
    if (!file_exists($configPath)) {
        http_response_code(400);
        echo "A instalacao precisa de ser concluida primeiro.";
        exit;
    }
    $config = require $configPath;
    try {
        $dsn = "mysql:host=" . $config['host'] . ";dbname=" . $config['dbname'] . ";charset=utf8mb4";
        $pdoInstance = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        echo "Erro de conexao: " . $e->getMessage();
        exit;
    }
}

$agentStmt = $pdoInstance->query("SELECT id FROM agents LIMIT 1");
$agent = $agentStmt->fetch(PDO::FETCH_ASSOC);
$agentId = $agent ? $agent['id'] : null;

$operatorStmt = $pdoInstance->query("SELECT id FROM operators LIMIT 1");
$operator = $operatorStmt->fetch(PDO::FETCH_ASSOC);
$operatorId = $operator ? $operator['id'] : null;

if (!$agentId || !$operatorId) {
    if (!isset($pdo)) {
        echo "Erro: E necessario ter pelo menos um agente e um operador registados.";
        exit;
    }
    return;
}

$settings = [
    ['id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)), 'setting_key' => 'company_name', 'setting_value' => 'Empresa de Demonstracao'],
    ['id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)), 'setting_key' => 'theme_color', 'setting_value' => '#3b82f6'],
    ['id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)), 'setting_key' => 'welcome_title', 'setting_value' => 'Suporte ao Cliente'],
];

foreach ($settings as $setting) {
    $stmt = $pdoInstance->prepare("INSERT INTO settings (id, setting_key, setting_value) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
    $stmt->execute([$setting['id'], $setting['setting_key'], $setting['setting_value']]);
}

$convId = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff));
$sessionId = bin2hex(random_bytes(16));

$stmtConv = $pdoInstance->prepare("INSERT INTO conversations (id, agent_id, session_id, status, assigned_operator_id, metadata) VALUES (?, ?, ?, 'active_bot', ?, '{}')");
$stmtConv->execute([$convId, $agentId, $sessionId, $operatorId]);

$messages = [
    [
        'id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
        'conversation_id' => $convId,
        'role' => 'user',
        'content' => 'Ola! Gostaria de saber mais informacoes sobre os vossos servicos.',
        'sender_type' => 'visitor'
    ],
    [
        'id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
        'conversation_id' => $convId,
        'role' => 'assistant',
        'content' => 'Ola! Sou o assistente virtual. Oferecemos solucoes integradas de chat para o seu site. Como posso ajudar com mais detalhes?',
        'sender_type' => 'bot'
    ],
    [
        'id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
        'conversation_id' => $convId,
        'role' => 'user',
        'content' => 'Preciso de falar com um operador humano, e possivel?',
        'sender_type' => 'visitor'
    ],
    [
        'id' => sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x', mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0x0fff) | 0x4000, mt_rand(0, 0x3fff) | 0x8000, mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)),
        'conversation_id' => $convId,
        'role' => 'operator',
        'content' => 'Ola! O meu nome e admin e vou dar seguimento ao seu atendimento. Em que posso ajudar?',
        'sender_type' => 'operator'
    ]
];

foreach ($messages as $msg) {
    $stmtMsg = $pdoInstance->prepare("INSERT INTO messages (id, conversation_id, role, content, sender_type) VALUES (?, ?, ?, ?, ?)");
    $stmtMsg->execute([$msg['id'], $msg['conversation_id'], $msg['role'], $msg['content'], $msg['sender_type']]);
}

if (!isset($pdo)) {
    echo "Base de dados populada com dados de teste com sucesso.";
}
