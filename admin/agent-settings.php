<?php

use App\Models\Agent;

$configFile = __DIR__ . '/../config/app.php';
$agentId = null;
if (file_exists($configFile)) {
    $appConfig = require $configFile;
    $agentId = $appConfig['default_agent_id'] ?? null;
}

$agentModel = new Agent();
$agent = null;

if ($agentId) {
    $agent = $agentModel->find($agentId);
}

if (!$agent) {
    $agents = $agentModel->all();
    if (!empty($agents)) {
        $agent = $agents[0];
    }
}

if (!$agent) {
    echo "Nenhum agente configurado no sistema.";
    exit;
}

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $greeting = trim($_POST['greeting_message'] ?? '');
    $prompt = trim($_POST['system_prompt'] ?? '');
    $provider = trim($_POST['provider'] ?? 'openai');
    $model = trim($_POST['model'] ?? '');
    $temp = (float)($_POST['temperature'] ?? 0.7);
    $maxTokens = (int)($_POST['max_tokens'] ?? 2048);

    if (empty($name) || empty($model)) {
        $error = 'Por favor, preencha todos os campos obrigatórios.';
    } else {
        $updateData = [
            'name' => $name,
            'greeting_message' => $greeting,
            'system_prompt' => $prompt,
            'provider' => $provider,
            'model' => $model,
            'temperature' => $temp,
            'max_tokens' => $maxTokens
        ];

        if ($agentModel->update($agent['id'], $updateData)) {
            $success = 'Definições do agente atualizadas com sucesso.';
            $agent = $agentModel->find($agent['id']);
        } else {
            $error = 'Erro ao atualizar as definições do agente.';
        }
    }
}

$title = 'Definições do Agente - Chat SDK Admin';
$pageTitle = 'Definições do Agente';
$activePage = 'agent-settings';

ob_start();
?>

<div class="card-box">
    <div class="card-box-header">
        <h2>Editar Definições do Agente AI</h2>
    </div>
    <div class="card-box-content">
        <?php if ($success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <form method="POST" action="agent-settings">
            <div class="form-row">
                <div class="form-group col-6">
                    <label for="name">Nome do Agente *</label>
                    <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($agent['name']); ?>" required>
                </div>
                <div class="form-group col-6">
                    <label for="provider">Provedor *</label>
                    <select id="provider" name="provider" required>
                        <option value="openai" <?php echo $agent['provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                        <option value="anthropic" <?php echo $agent['provider'] === 'anthropic' ? 'selected' : ''; ?>>Anthropic</option>
                        <option value="gemini" <?php echo $agent['provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                        <option value="ollama" <?php echo $agent['provider'] === 'ollama' ? 'selected' : ''; ?>>Ollama (Local)</option>
                        <option value="groq" <?php echo $agent['provider'] === 'groq' ? 'selected' : ''; ?>>Groq</option>
                    </select>
                </div>
            </div>

            <div class="form-row">
                <div class="form-group col-6">
                    <label for="model">Modelo *</label>
                    <input type="text" id="model" name="model" value="<?php echo htmlspecialchars($agent['model']); ?>" placeholder="ex: gpt-4o ou claude-3-5-sonnet" required>
                </div>
                <div class="form-group col-3">
                    <label for="temperature">Temperatura (0.0 - 1.0)</label>
                    <input type="number" id="temperature" name="temperature" value="<?php echo htmlspecialchars($agent['temperature']); ?>" min="0" max="1" step="0.1">
                </div>
                <div class="form-group col-3">
                    <label for="max_tokens">Tokens Máximos</label>
                    <input type="number" id="max_tokens" name="max_tokens" value="<?php echo htmlspecialchars($agent['max_tokens']); ?>" min="1" max="8192">
                </div>
            </div>

            <div class="form-group">
                <label for="greeting_message">Mensagem de Boas-vindas</label>
                <input type="text" id="greeting_message" name="greeting_message" value="<?php echo htmlspecialchars($agent['greeting_message']); ?>">
            </div>

            <div class="form-group">
                <label for="system_prompt">Prompt de Sistema</label>
                <textarea id="system_prompt" name="system_prompt" rows="8"><?php echo htmlspecialchars($agent['system_prompt']); ?></textarea>
            </div>

            <button type="submit" class="btn btn-primary">Guardar Alterações</button>
        </form>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
