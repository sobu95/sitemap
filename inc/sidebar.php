<?php
// Sprawdzenie, czy użytkownik jest administratorem
$stmt = $conn->prepare("SELECT role FROM users WHERE id = ?");
$stmt->bind_param('i', $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$is_admin = ($user['role'] === 'admin');

$error = '';
$success = '';

?>

<style>
    .sidebar {
        position: fixed;
        top: 0;
        bottom: 0;
        left: 0;
        z-index: 100;
        height: 100%;
        width: 250px;
        background-color: #f8f9fa;
        border-right: 1px solid #dee2e6;
    }

    .sidebar-sticky {
        position: relative;
        height: 100%;
        display: flex;
        flex-direction: column;
    }

    .nav-links {
        flex-grow: 1;
        display: flex;
        flex-direction: column;
        padding: 15px 0;
    }

    .additional-tools {
        margin: 15px;
        text-align: left;
    }

    .logout-button {
        margin: 15px;
        padding: 10px;
        background-color: #007bff;
        color: #fff;
        text-align: center;
        text-decoration: none;
        border: none;
        border-radius: 5px;
        font-size: 16px;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 10px;
        cursor: pointer;
    }

    .logout-button:hover {
        background-color: #0056b3;
    }
</style>

<div class="container-fluid">
    <div class="row">
        <nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
            <div class="position-sticky sidebar-sticky">
                <h3 style="margin-top:20px; margin-left: 15px; color:black; font-size:1.5rem;">Panel użytkownika</h3>
                <div class="nav-links">
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="dashboard.php">Domeny</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="profile.php">Profil</a>
                        </li>
                        <?php if ($is_admin): ?>
                        <li class="nav-item">
                            <a class="nav-link" href="admin.php">Panel administratora</a>
                        </li>
                        <?php endif; ?>
                    </ul>
                </div>
                <div class="additional-tools">
                    <h6 style="color: black; font-size: 1.2rem;">Dodatkowe narzędzia</h6>
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link" href="checker/">
                                Status Code Checker
                            </a>
                        </li>
                    </ul>
                </div>
                <a href="logout.php" class="logout-button">
                    <span>&#128274;</span> Wyloguj się
                </a>
            </div>
        </nav>
    </div>
</div>
