<?php

use App\Models\Setting;
use App\Core\Database;

$settingModel = new Setting();

$allSettings = $settingModel->all();
$settingsMap = [];
foreach ($allSettings as $s) {
    $settingsMap[$s['setting_key']] = $s['setting_value'];
}

$success = '';
$error = '';

$keys = [
    'openai_api_key',
    'anthropic_api_key',
    'gemini_api_key',
    'smtp_host',
    'smtp_port',
    'smtp_user',
    'smtp_pass',
    'smtp_from_email',
    'smtp_from_name'
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $db = Database::getInstance();
    $db->beginTransaction();
    try {
        foreach ($keys as $key) {
            $value = trim($_POST[$key] ?? '');
            $existing = $settingModel->where(['setting_key' => $key]);
            if (!empty($existing)) {
                $settingModel->update($existing[0]['id'], ['setting_value' => $value]);
            } else {
                $settingModel->create([
                    'setting_key' => $key,
                    'setting_value' => $value
                ]);
            }
        }
        $db->commit();
        $success = 'Configurações globais atualizadas com sucesso.';
        
        $allSettings = $settingModel->all();
        $settingsMap = [];
        foreach ($allSettings as $s) {
            $settingsMap[$s['setting_key']] = $s['setting_value'];
        }
    } catch (Exception $e) {
        $db->rollBack();
        $error = 'Erro ao guardar as configurações: ' . $e->getMessage();
    }
}

$title = 'Configurações Gerais - Chat SDK Admin';
$pageTitle = 'Configurações Gerais';
$activePage = 'settings';

ob_start();
?>

<div class="settings-grid">
    <div class="card-box">
        <div class="card-box-header">
            <h2>Chaves de API dos Provedores AI</h2>
        </div>
        <div class="card-box-content">
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form method="POST" action="settings">
                <div class="form-group">
                    <label for="openai_api_key">OpenAI API Key</label>
                    <input type="password" id="openai_api_key" name="openai_api_key" value="<?php echo htmlspecialchars($settingsMap['openai_api_key'] ?? ''); ?>" placeholder="sk-...">
                </div>

                <div class="form-group">
                    <label for="anthropic_api_key">Anthropic API Key</label>
                    <input type="password" id="anthropic_api_key" name="anthropic_api_key" value="<?php echo htmlspecialchars($settingsMap['anthropic_api_key'] ?? ''); ?>" placeholder="sk-ant-...">
                </div>

                <div class="form-group">
                    <label for="gemini_api_key">Google Gemini API Key</label>
                    <input type="password" id="gemini_api_key" name="gemini_api_key" value="<?php echo htmlspecialchars($settingsMap['gemini_api_key'] ?? ''); ?>">
                </div>

                <hr class="form-divider">

                <h3>Configurações SMTP</h3>

                <div class="form-row">
                    <div class="form-group col-8">
                        <label for="smtp_host">Servidor SMTP (Host)</label>
                        <input type="text" id="smtp_host" name="smtp_host" value="<?php echo htmlspecialchars($settingsMap['smtp_host'] ?? ''); ?>" placeholder="smtp.exemplo.com">
                    </div>
                    <div class="form-group col-4">
                        <label for="smtp_port">Porta SMTP</label>
                        <input type="number" id="smtp_port" name="smtp_port" value="<?php echo htmlspecialchars($settingsMap['smtp_port'] ?? ''); ?>" placeholder="587">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="smtp_user">Utilizador SMTP</label>
                        <input type="text" id="smtp_user" name="smtp_user" value="<?php echo htmlspecialchars($settingsMap['smtp_user'] ?? ''); ?>">
                    </div>
                    <div class="form-group col-6">
                        <label for="smtp_pass">Palavra-passe SMTP</label>
                        <input type="password" id="smtp_pass" name="smtp_pass" value="<?php echo htmlspecialchars($settingsMap['smtp_pass'] ?? ''); ?>">
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group col-6">
                        <label for="smtp_from_email">E-mail do Remetente</label>
                        <input type="email" id="smtp_from_email" name="smtp_from_email" value="<?php echo htmlspecialchars($settingsMap['smtp_from_email'] ?? ''); ?>" placeholder="noreply@exemplo.com">
                    </div>
                    <div class="form-group col-6">
                        <label for="smtp_from_name">Nome do Remetente</label>
                        <input type="text" id="smtp_from_name" name="smtp_from_name" value="<?php echo htmlspecialchars($settingsMap['smtp_from_name'] ?? ''); ?>" placeholder="Chat SDK Alertas">
                    </div>
                </div>

                <button type="submit" class="btn btn-primary">Guardar Configurações</button>
            </form>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layouts/main.php';
