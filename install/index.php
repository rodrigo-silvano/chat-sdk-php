<?php

session_start();

$configFile = __DIR__ . '/../config/database.php';
$appFile = __DIR__ . '/../config/app.php';

if (file_exists($configFile) && file_exists($appFile)) {
    echo "<!DOCTYPE html>
    <html lang='pt-PT'>
    <head>
        <meta charset='UTF-8'>
        <title>Instalação Concluída</title>
        <style>
            body { background: #0b0f19; color: #f3f4f6; font-family: sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
            .card { background: #111827; padding: 2rem; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.3); border: 1px solid #1f2937; text-align: center; max-width: 400px; }
            h2 { color: #f87171; }
        </style>
    </head>
    <body>
        <div class='card'>
            <h2>Instalação Bloqueada</h2>
            <p>O Chat SDK já se encontra instalado. Se deseja reinstalar, elimine os ficheiros <code>config/database.php</code> e <code>config/app.php</code>.</p>
            <p>Por questões de segurança, <strong>elimine a pasta <code>install</code></strong> do seu servidor.</p>
            <a href='../admin/' style='color: #3b82f6; text-decoration: none;'>Ir para o Painel Admin</a>
        </div>
    </body>
    </html>";
    exit;
}

$step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
$error = '';
$success = '';

function generateUuid() {
    return sprintf(
        '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
        mt_rand(0, 0xffff), mt_rand(0, 0xffff),
        mt_rand(0, 0xffff),
        mt_rand(0, 0x0fff) | 0x4000,
        mt_rand(0, 0x3fff) | 0x8000,
        mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
    );
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1) {
        $host = $_POST['db_host'] ?? 'localhost';
        $dbname = $_POST['db_name'] ?? '';
        $username = $_POST['db_user'] ?? '';
        $password = $_POST['db_pass'] ?? '';

        try {
            $pdo = null;
            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);
            } catch (PDOException $e) {
                try {
                    $rootPdo = new PDO("mysql:host=$host;charset=utf8mb4", $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                    $rootPdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
                    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password, [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                    ]);
                } catch (PDOException $e2) {
                    throw new Exception("Falha ao ligar à base de dados '$dbname'. Se estiver num alojamento partilhado (cPanel), crie a base de dados e associe o utilizador MySQL antes de iniciar a instalação. Detalhes: " . $e2->getMessage());
                }
            }

            $sql = file_get_contents(__DIR__ . '/schema.sql');
            $pdo->exec($sql);

            $_SESSION['db_host'] = $host;
            $_SESSION['db_name'] = $dbname;
            $_SESSION['db_user'] = $username;
            $_SESSION['db_pass'] = $password;

            header('Location: index.php?step=2');
            exit;
        } catch (Exception $e) {
            $error = 'Erro na base de dados: ' . $e->getMessage();
        }
    } elseif ($step === 2) {
        $email = $_POST['admin_email'] ?? '';
        $name = $_POST['admin_name'] ?? '';
        $password = $_POST['admin_pass'] ?? '';
        $password_conf = $_POST['admin_pass_conf'] ?? '';
        $run_seeding = isset($_POST['run_seeding']) && $_POST['run_seeding'] === '1';

        if (empty($email) || empty($name) || empty($password)) {
            $error = 'Por favor, preencha todos os campos.';
        } elseif ($password !== $password_conf) {
            $error = 'As passwords não coincidem.';
        } elseif (strlen($password) < 8) {
            $error = 'A password deve conter pelo menos 8 caracteres.';
        } else {
            $host = $_SESSION['db_host'] ?? '';
            $dbname = $_SESSION['db_name'] ?? '';
            $username = $_SESSION['db_user'] ?? '';
            $db_pass = $_SESSION['db_pass'] ?? '';

            try {
                $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $db_pass, [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
                ]);

                $operatorId = generateUuid();
                $passHash = password_hash($password, PASSWORD_BCRYPT);
                
                $stmt = $pdo->prepare("INSERT INTO operators (id, name, email, password_hash, role) VALUES (?, ?, ?, ?, 'admin')");
                $stmt->execute([$operatorId, $name, $email, $passHash]);

                $agentId = generateUuid();
                $stmtAgent = $pdo->prepare("INSERT INTO agents (id, name, greeting_message, system_prompt, provider, model, temperature, max_tokens, config) VALUES (?, 'Assistente', 'Olá! Como posso ajudar?', 'És um assistente útil e cortês.', 'openai', 'gpt-4o', 0.7, 2048, '{}')");
                $stmtAgent->execute([$agentId]);

                if ($run_seeding) {
                    require __DIR__ . '/seed.php';
                }

                if (!is_dir(__DIR__ . '/../config')) {
                    mkdir(__DIR__ . '/../config', 0755, true);
                }

                $dbContent = "<?php\n\nreturn [\n    'host' => '" . addslashes($host) . "',\n    'dbname' => '" . addslashes($dbname) . "',\n    'username' => '" . addslashes($username) . "',\n    'password' => '" . addslashes($db_pass) . "',\n];\n";
                file_put_contents($configFile, $dbContent);

                $jwtSecret = bin2hex(random_bytes(32));
                $encKey = bin2hex(random_bytes(32));
                
                $appContent = "<?php\n\nreturn [\n    'jwt_secret' => '$jwtSecret',\n    'encryption_key' => '$encKey',\n    'default_agent_id' => '$agentId',\n];\n";
                file_put_contents($appFile, $appContent);

                session_destroy();
                $success = true;
            } catch (Exception $e) {
                $error = 'Erro ao criar conta de administrador: ' . $e->getMessage();
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Instalação — Chat SDK</title>
    <style>
        body { background: #0b0f19; color: #f3f4f6; font-family: system-ui, -apple-system, sans-serif; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; }
        .container { background: #111827; border: 1px solid #1f2937; border-radius: 12px; padding: 2.5rem; width: 100%; max-width: 480px; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.5); }
        h1 { font-size: 1.5rem; margin-top: 0; margin-bottom: 1.5rem; color: #ffffff; text-align: center; }
        .step-indicator { display: flex; justify-content: space-between; margin-bottom: 2rem; }
        .step { flex: 1; text-align: center; padding-bottom: 0.5rem; border-bottom: 2px solid #1f2937; color: #9ca3af; font-size: 0.875rem; }
        .step.active { border-bottom-color: #3b82f6; color: #3b82f6; font-weight: bold; }
        .form-group { margin-bottom: 1.25rem; }
        label { display: block; font-size: 0.875rem; margin-bottom: 0.5rem; color: #d1d5db; }
        input[type="text"], input[type="password"], input[type="email"] { width: 100%; padding: 0.75rem; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #ffffff; font-size: 1rem; box-sizing: border-box; }
        input:focus { outline: none; border-color: #3b82f6; }
        .btn { display: block; width: 100%; padding: 0.75rem; background: #3b82f6; border: none; border-radius: 6px; color: #ffffff; font-size: 1rem; cursor: pointer; text-align: center; font-weight: bold; margin-top: 1.5rem; text-decoration: none; }
        .btn:hover { background: #2563eb; }
        .error { background: #7f1d1d; border: 1px solid #f87171; color: #fca5a5; padding: 0.75rem; border-radius: 6px; font-size: 0.875rem; margin-bottom: 1.5rem; }
        .success-box { text-align: center; }
        .success-icon { font-size: 3rem; color: #10b981; margin-bottom: 1rem; }
    </style>
</head>
<body>
<div class="container">
    <?php if ($success): ?>
        <div class="success-box">
            <div class="success-icon">✓</div>
            <h1>Instalação Concluída!</h1>
            <p>O Chat SDK foi configurado com sucesso e a base de dados foi inicializada.</p>
            <p style="color: #ef4444; font-weight: bold; margin: 1.5rem 0;">IMPORTANTE: Elimine a pasta "install" do seu servidor por motivos de segurança.</p>
            <a href="../admin/" class="btn">Aceder ao Painel Admin</a>
        </div>
    <?php else: ?>
        <h1>Instalação do Chat SDK</h1>
        
        <div class="step-indicator">
            <div class="step <?php echo $step === 1 ? 'active' : ''; ?>">1. Base de Dados</div>
            <div class="step <?php echo $step === 2 ? 'active' : ''; ?>">2. Administrador</div>
        </div>

        <?php if ($error): ?>
            <div class="error"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>

        <?php if ($step === 1): ?>
            <form method="post" action="index.php?step=1">
                <div class="form-group">
                    <label for="db_host">Servidor MySQL (Host)</label>
                    <input type="text" id="db_host" name="db_host" value="localhost" required>
                </div>
                <div class="form-group">
                    <label for="db_name">Nome da Base de Dados</label>
                    <input type="text" id="db_name" name="db_name" value="chat_sdk" required>
                </div>
                <div class="form-group">
                    <label for="db_user">Utilizador MySQL</label>
                    <input type="text" id="db_user" name="db_user" required>
                </div>
                <div class="form-group">
                    <label for="db_pass">Password MySQL</label>
                    <input type="password" id="db_pass" name="db_pass">
                </div>
                <button type="submit" class="btn">Ligar e Configurar</button>
            </form>
        <?php elseif ($step === 2): ?>
            <form method="post" action="index.php?step=2">
                <div class="form-group">
                    <label for="admin_name">Nome do Administrador</label>
                    <input type="text" id="admin_name" name="admin_name" placeholder="Ex: Rodrigo" required>
                </div>
                <div class="form-group">
                    <label for="admin_email">Email do Administrador</label>
                    <input type="email" id="admin_email" name="admin_email" placeholder="Ex: admin@dominio.com" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass">Password do Administrador</label>
                    <input type="password" id="admin_pass" name="admin_pass" required>
                </div>
                <div class="form-group">
                    <label for="admin_pass_conf">Confirmar Password</label>
                    <input type="password" id="admin_pass_conf" name="admin_pass_conf" required>
                </div>
                <div class="form-group" style="display: flex; align-items: center; gap: 0.5rem; margin-top: 1.5rem;">
                    <input type="checkbox" id="run_seeding" name="run_seeding" value="1">
                    <label for="run_seeding" style="margin-bottom: 0; cursor: pointer;">Instalar dados de teste (Seeding)</label>
                </div>
                <button type="submit" class="btn">Finalizar Instalação</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
