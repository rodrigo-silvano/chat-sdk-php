<?php

require_once __DIR__ . '/../core/Autoloader.php';

session_start();

$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);

$scriptName = dirname($_SERVER['SCRIPT_NAME']);
if ($scriptName !== '/' && strpos($path, $scriptName) === 0) {
    $path = substr($path, strlen($scriptName));
}

$path = '/' . ltrim($path, '/');

$configFile = __DIR__ . '/../config/database.php';
if (!file_exists($configFile)) {
    header('Location: ../install/');
    exit;
}

use App\Models\Operator;
use App\Core\Totp;

if ($path === '/logout') {
    if (isset($_SESSION['operator'])) {
        $opModel = new Operator();
        $opModel->update($_SESSION['operator']['id'], ['is_online' => 0]);
    }
    session_destroy();
    header('Location: ./');
    exit;
}

if ($path === '/' || $path === '/index.php' || $path === '/login') {
    if (isset($_SESSION['operator'])) {
        header('Location: dashboard');
        exit;
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (isset($_POST['email']) && isset($_POST['password'])) {
            $email = trim($_POST['email']);
            $password = $_POST['password'];

            $opModel = new Operator();
            $operators = $opModel->where(['email' => $email]);

            if (!empty($operators)) {
                $operator = $operators[0];
                if (password_verify($password, $operator['password_hash'])) {
                    if ($operator['totp_enabled'] == 0) {
                        $_SESSION['temp_operator_id'] = $operator['id'];
                        header('Location: setup-2fa');
                        exit;
                    } else {
                        $_SESSION['temp_operator_id'] = $operator['id'];
                        $_SESSION['login_step'] = '2fa';
                    }
                } else {
                    $error = 'Credenciais inválidas.';
                }
            } else {
                $error = 'Credenciais inválidas.';
            }
        } elseif (isset($_POST['code']) && isset($_SESSION['temp_operator_id']) && isset($_SESSION['login_step']) && $_SESSION['login_step'] === '2fa') {
            $code = trim($_POST['code']);
            $opModel = new Operator();
            $operator = $opModel->find($_SESSION['temp_operator_id']);

            if ($operator && Totp::verify($operator['totp_secret'], $code)) {
                $opModel->update($operator['id'], ['is_online' => 1]);
                $_SESSION['operator'] = $operator;
                unset($_SESSION['temp_operator_id']);
                unset($_SESSION['login_step']);
                header('Location: dashboard');
                exit;
            } else {
                $error = 'Código 2FA inválido.';
            }
        }
    }

    require __DIR__ . '/login-view.php';
    exit;
}

$protected_routes = [
    '/dashboard' => 'dashboard.php',
    '/conversations' => 'conversations.php',
    '/conversation-detail' => 'conversation-detail.php',
    '/agent-settings' => 'agent-settings.php',
    '/settings' => 'settings.php',
    '/setup-2fa' => 'setup-2fa.php',
    '/api/conversations' => 'api.php',
    '/api/messages' => 'api.php',
    '/api/send-message' => 'api.php',
    '/api/takeover' => 'api.php',
    '/api/release' => 'api.php',
    '/api/close' => 'api.php',
];

if (isset($protected_routes[$path])) {
    if (!isset($_SESSION['operator']) && $path !== '/setup-2fa') {
        header('Location: ./');
        exit;
    }

    if ($path === '/setup-2fa' && !isset($_SESSION['operator']) && !isset($_SESSION['temp_operator_id'])) {
        header('Location: ./');
        exit;
    }

    require __DIR__ . '/' . $protected_routes[$path];
    exit;
}

http_response_code(404);
echo "Página não encontrada";
