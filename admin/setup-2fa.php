<?php

use App\Models\Operator;
use App\Core\Totp;

$opModel = new Operator();
$operatorId = $_SESSION['temp_operator_id'] ?? ($_SESSION['operator']['id'] ?? null);

if (!$operatorId) {
    header('Location: ./');
    exit;
}

$operator = $opModel->find($operatorId);

if (!$operator) {
    header('Location: ./');
    exit;
}

if ($operator['totp_enabled'] == 1 && isset($_SESSION['operator'])) {
    header('Location: dashboard');
    exit;
}

$secret = $_SESSION['temp_totp_secret'] ?? null;
if (!$secret) {
    $secret = Totp::generateSecret();
    $_SESSION['temp_totp_secret'] = $secret;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $code = trim($_POST['code'] ?? '');
    if (Totp::verify($secret, $code)) {
        $opModel->update($operator['id'], [
            'totp_secret' => $secret,
            'totp_enabled' => 1,
            'is_online' => 1
        ]);
        
        $updatedOperator = $opModel->find($operator['id']);
        $_SESSION['operator'] = $updatedOperator;
        
        unset($_SESSION['temp_operator_id']);
        unset($_SESSION['temp_totp_secret']);
        unset($_SESSION['login_step']);
        
        header('Location: dashboard');
        exit;
    } else {
        $error = 'Código de ativação inválido. Tente novamente.';
    }
}

$issuer = 'ChatSDK';
$email = $operator['email'];
$otpauthUrl = "otpauth://totp/{$issuer}:{$email}?secret={$secret}&issuer={$issuer}";
$qrCodeUrl = "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=" . urlencode($otpauthUrl);
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Configurar 2FA - Chat SDK</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card 2fa-card">
            <div class="login-header">
                <h2>Configurar 2FA</h2>
                <p>Proteja a sua conta ativando a autenticação em dois fatores</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <div class="qr-container">
                <img src="<?php echo $qrCodeUrl; ?>" alt="QR Code 2FA">
            </div>

            <div class="setup-instructions">
                <p>1. Instale um aplicativo autenticador (Google Authenticator, Authy, etc.).</p>
                <p>2. Faça scan do QR Code acima ou introduza a chave manualmente:</p>
                <div class="secret-key"><?php echo chunk_split($secret, 4, ' '); ?></div>
            </div>

            <form method="POST" action="setup-2fa">
                <div class="form-group">
                    <label for="code">Código de Confirmação</label>
                    <input type="text" id="code" name="code" placeholder="000000" required autofocus>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Confirmar e Ativar</button>
                <div class="login-footer">
                    <a href="logout">Cancelar</a>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
