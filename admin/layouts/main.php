<!DOCTYPE html>
<html lang="pt-PT">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title ?? 'Painel Admin - Chat SDK'; ?></title>
    <link rel="stylesheet" href="/admin/assets/css/admin.css">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="admin-body">
    <div class="admin-wrapper">
        <aside class="admin-sidebar" id="sidebar">
            <div class="sidebar-header">
                <h2>Chat SDK</h2>
                <button class="sidebar-close" id="sidebarCloseBtn">&times;</button>
            </div>
            <nav class="sidebar-menu">
                <a href="/admin/dashboard" class="menu-item <?php echo ($activePage ?? '') === 'dashboard' ? 'active' : ''; ?>">
                    <span class="icon">📊</span>
                    <span class="text">Dashboard</span>
                </a>
                <a href="/admin/conversations" class="menu-item <?php echo ($activePage ?? '') === 'conversations' ? 'active' : ''; ?>">
                    <span class="icon">💬</span>
                    <span class="text">Conversas</span>
                </a>
                <a href="/admin/agent-settings" class="menu-item <?php echo ($activePage ?? '') === 'agent-settings' ? 'active' : ''; ?>">
                    <span class="icon">🤖</span>
                    <span class="text">Definições do Agente</span>
                </a>
                <a href="/admin/settings" class="menu-item <?php echo ($activePage ?? '') === 'settings' ? 'active' : ''; ?>">
                    <span class="icon">⚙️</span>
                    <span class="text">Configurações Gerais</span>
                </a>
            </nav>
            <div class="sidebar-footer">
                <a href="/admin/logout" class="menu-item logout-item">
                    <span class="icon">🚪</span>
                    <span class="text">Sair</span>
                </a>
            </div>
        </aside>

        <div class="admin-main">
            <header class="admin-header">
                <div class="header-left">
                    <button class="sidebar-toggle" id="sidebarToggleBtn">☰</button>
                    <h1><?php echo $pageTitle ?? ''; ?></h1>
                </div>
                <div class="header-right">
                    <div class="operator-profile">
                        <span class="status-indicator online"></span>
                        <span class="operator-name"><?php echo htmlspecialchars($_SESSION['operator']['name'] ?? 'Operador'); ?></span>
                        <span class="operator-role"><?php echo htmlspecialchars($_SESSION['operator']['role'] ?? 'operador'); ?></span>
                    </div>
                </div>
            </header>

            <main class="admin-content">
                <?php echo $content; ?>
            </main>
        </div>
    </div>
    <script src="/admin/assets/js/admin.js"></script>
    <?php echo $extraJs ?? ''; ?>
</body>
</html>
