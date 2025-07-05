<?php
// Sprawdzenie, czy użytkownik jest administratorem
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$is_admin = ($user['role'] === 'admin');

// Określenie aktywnej strony
$current_page = basename($_SERVER['PHP_SELF']);
?>

<aside class="sidebar">
    <div class="logo">SiteMap Checker</div>
    
    <nav class="nav-menu">
        <ul>
            <li class="<?= ($current_page == 'dashboard.php') ? 'active' : '' ?>">
                <a href="dashboard.php">
                    <i class="fa-solid fa-globe"></i>
                    Domeny
                </a>
            </li>
            <li class="<?= ($current_page == 'profile.php') ? 'active' : '' ?>">
                <a href="profile.php">
                    <i class="fa-solid fa-user"></i>
                    Profil
                </a>
            </li>
            <?php if ($is_admin): ?>
            <li class="<?= ($current_page == 'admin.php') ? 'active' : '' ?>">
                <a href="admin.php">
                    <i class="fa-solid fa-shield-halved"></i>
                    Panel administratora
                </a>
            </li>
            <?php endif; ?>
        </ul>
    </nav>

    <div class="tools">
        <div class="tools-header">Narzędzia dodatkowe</div>
        <ul>
            <li>
                <a href="checker/">
                    <i class="fa-solid fa-stethoscope"></i>
                    Status Code Checker
                </a>
            </li>
            <li>
                <a href="url-to-text.php">
                    <i class="fa-solid fa-link"></i>
                    Wyciąganie URL
                </a>
            </li>
            <li>
                <a href="image_info.php">
                    <i class="fa-solid fa-image"></i>
                    Info o grafikach
                </a>
            </li>
            <li>
                <a href="usuwacz-parametrow.php">
                    <i class="fa-solid fa-filter"></i>
                    Filtr URL
                </a>
            </li>
        </ul>
    </div>

    <a href="logout.php" class="logout-btn">
        <i class="fa-solid fa-lock"></i>
        Wyloguj się
    </a>
</aside>