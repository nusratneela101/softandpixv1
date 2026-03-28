<?php
$flash = getFlashMessage();
$csrf_token = generateCsrfToken();

try {
    $unread_count = $pdo->query("SELECT COUNT(*) FROM contact_messages WHERE is_read = 0")->fetchColumn();
} catch(Exception $e) {
    $unread_count = 0;
}

$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Softandpix</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css" rel="stylesheet">
    <link href="assets/css/admin.css" rel="stylesheet">
</head>
<body>
<div class="d-flex" id="wrapper">
    <!-- Sidebar -->
    <div id="sidebar-wrapper">
        <div class="sidebar-brand">
            <a href="index.php">
                <img src="../assets/img/SoftandPix -LOGO.png" alt="Softandpix" style="max-height:40px; filter: brightness(10);">
            </a>
        </div>
        <div class="sidebar-heading">Admin Panel</div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'index.php' ? 'active' : ''; ?>" href="index.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <span class="sidebar-section-title">Website Sections</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'hero.php' ? 'active' : ''; ?>" href="hero.php">
                    <i class="bi bi-image"></i> Hero
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'about.php' ? 'active' : ''; ?>" href="about.php">
                    <i class="bi bi-info-circle"></i> About
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'values.php' ? 'active' : ''; ?>" href="values.php">
                    <i class="bi bi-star"></i> Values
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'stats.php' ? 'active' : ''; ?>" href="stats.php">
                    <i class="bi bi-bar-chart"></i> Stats
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'features.php' ? 'active' : ''; ?>" href="features.php">
                    <i class="bi bi-check2-square"></i> Features
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'services.php' ? 'active' : ''; ?>" href="services.php">
                    <i class="bi bi-grid"></i> Services
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'pricing.php' ? 'active' : ''; ?>" href="pricing.php">
                    <i class="bi bi-tag"></i> Pricing
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'faq.php' ? 'active' : ''; ?>" href="faq.php">
                    <i class="bi bi-question-circle"></i> FAQ
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'portfolio.php' ? 'active' : ''; ?>" href="portfolio.php">
                    <i class="bi bi-images"></i> Portfolio
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'testimonials.php' ? 'active' : ''; ?>" href="testimonials.php">
                    <i class="bi bi-chat-quote"></i> Testimonials
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'team.php' ? 'active' : ''; ?>" href="team.php">
                    <i class="bi bi-people"></i> Team
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'clients.php' ? 'active' : ''; ?>" href="clients.php">
                    <i class="bi bi-building"></i> Clients
                </a>
            </li>
            <!-- Users & Roles -->
            <li class="nav-item">
                <span class="sidebar-section-title">User Management</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['users.php','users_add.php','users_edit.php','user_view.php']) ? 'active' : ''; ?>" href="users.php">
                    <i class="bi bi-people"></i> All Users
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'roles.php' ? 'active' : ''; ?>" href="roles.php">
                    <i class="bi bi-shield-check"></i> Roles
                </a>
            </li>
            <!-- Projects -->
            <li class="nav-item">
                <span class="sidebar-section-title">Projects</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['projects.php','project_add.php','project_edit.php','project_view.php']) ? 'active' : ''; ?>" href="projects.php">
                    <i class="bi bi-kanban"></i> All Projects
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'deadline_requests.php' ? 'active' : ''; ?>" href="deadline_requests.php">
                    <i class="bi bi-calendar-x"></i> Deadline Requests
                </a>
            </li>
            <!-- Communication -->
            <li class="nav-item">
                <span class="sidebar-section-title">Communication</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'chat.php' ? 'active' : ''; ?>" href="chat.php">
                    <i class="bi bi-chat-dots"></i> Chat
                </a>
            </li>
            <li class="nav-item">
                <?php
                $lcw_unread = 0;
                try {
                    $lcw_unread = (int)$pdo->query("SELECT COUNT(*) FROM live_contact_messages WHERE is_read=0 AND sender_type='guest'")->fetchColumn();
                } catch (Exception $e) {}
                ?>
                <a class="nav-link <?php echo in_array($current_page, ['live_contacts.php','live_contact_chat.php']) ? 'active' : ''; ?>" href="live_contacts.php">
                    <i class="bi bi-headset"></i> Live Contacts
                    <?php if ($lcw_unread > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo $lcw_unread; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['email_dashboard.php','email_sent.php']) ? 'active' : ''; ?>" href="email_dashboard.php">
                    <i class="bi bi-send"></i> Email
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'email_templates.php' ? 'active' : ''; ?>" href="email_templates.php">
                    <i class="bi bi-file-earmark-text"></i> Email Templates
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'email_webmail.php' ? 'active' : ''; ?>" href="email_webmail.php">
                    <i class="bi bi-mailbox"></i> Webmail
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'email_accounts.php' ? 'active' : ''; ?>" href="email_accounts.php">
                    <i class="bi bi-at"></i> Email Accounts
                </a>
            </li>
            <!-- Invoices -->
            <li class="nav-item">
                <span class="sidebar-section-title">Finance</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo in_array($current_page, ['invoices.php','invoice_create.php','invoice_edit.php','invoice_view.php']) ? 'active' : ''; ?>" href="invoices.php">
                    <i class="bi bi-receipt"></i> Invoices
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'notifications.php' ? 'active' : ''; ?>" href="notifications.php">
                    <i class="bi bi-bell"></i> Notifications
                </a>
            </li>
            <li class="nav-item">
                <span class="sidebar-section-title">Settings</span>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'settings.php' ? 'active' : ''; ?>" href="settings.php">
                    <i class="bi bi-gear"></i> Site Settings
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'messages.php' ? 'active' : ''; ?>" href="messages.php">
                    <i class="bi bi-envelope"></i> Messages
                    <?php if ($unread_count > 0): ?>
                    <span class="badge bg-danger ms-1"><?php echo (int)$unread_count; ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link text-danger" href="logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </li>
        </ul>
    </div>
    <!-- End Sidebar -->

    <!-- Content Wrapper -->
    <div id="page-content-wrapper">
        <!-- Top Navbar -->
        <nav class="navbar navbar-expand-lg navbar-light bg-light border-bottom top-navbar">
            <div class="container-fluid">
                <button class="btn btn-sm btn-outline-secondary" id="sidebarToggle">
                    <i class="bi bi-list"></i>
                </button>
                <div class="ms-auto d-flex align-items-center">
                    <span class="me-3 text-muted">Welcome, <strong><?php echo h($_SESSION['admin_username'] ?? 'Admin'); ?></strong></span>
                    <a href="logout.php" class="btn btn-sm btn-outline-danger">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </div>
        </nav>

        <!-- Flash Messages -->
        <div class="container-fluid mt-3">
            <?php if ($flash): ?>
            <div class="alert alert-<?php echo $flash['type'] === 'success' ? 'success' : 'danger'; ?> alert-dismissible fade show auto-dismiss" role="alert">
                <?php echo h($flash['message']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php endif; ?>
