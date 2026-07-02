<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Chat SDK Admin</title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="login-body">
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">
                    <h2>Chat SDK</h2>
                </div>
                <p>Painel de Operações</p>
            </div>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>

            <?php if (isset($_SESSION['login_step']) && $_SESSION['login_step'] === '2fa'): ?>
                <form method="POST" action="login">
                    <div class="form-group">
                        <label for="code">Código de Autenticação (2FA)</label>
                        <input type="text" id="code" name="code" placeholder="000000" autocomplete="one-time-code" required autofocus>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Verificar e Entrar</button>
                    <div class="login-footer">
                        <a href="logout">Cancelar</a>
                    </div>
                </form>
            <?php else: ?>
                <form method="POST" action="login">
                    <div class="form-group">
                        <label for="email">E-mail</label>
                        <input type="email" id="email" name="email" placeholder="operador@exemplo.com" required autofocus>
                    </div>
                    <div class="form-group">
                        <label for="password">Palavra-passe</label>
                        <input type="password" id="password" name="password" placeholder="••••••••" required>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">Entrar</button>
                </form>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
