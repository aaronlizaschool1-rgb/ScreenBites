<?php
if (session_status() === PHP_SESSION_NONE) { 
    session_start();
    }


if (!defined('BASE_URL')) { 
    define('BASE_URL', '/ScreenBites/public'); 
    }

$pageTitle  = $pageTitle  ?? 'Dashboard';
$activePage = $activePage ?? '';


$userName = $_SESSION['full_name'] ?? 'Demo Admin';
$userRole = $_SESSION['role']      ?? 'admin';   
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($pageTitle) ?> | ScreenBites</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700&family=Public+Sans:wght@400;500;600&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>
<div class="app">

<?php require __DIR__ . '/sidebar.php'; ?>

  <div class="app-main">
    <header class="topbar">
      <button class="menu-btn" id="menuBtn" aria-label="Toggle navigation">&#9776;</button>
      <h1 class="topbar-title"><?= htmlspecialchars($pageTitle) ?></h1>
      <div class="topbar-user">
        <span class="user-name"><?= htmlspecialchars($userName) ?></span>
        <span class="role-pill role-<?= htmlspecialchars($userRole) ?>"><?= htmlspecialchars($userRole) ?></span>
      </div>
    </header>

    <main class="content">
      <?php if (!empty($_SESSION['flash'])): ?>
        <div class="alert alert-<?= htmlspecialchars($_SESSION['flash']['type']) ?>" role="alert">
          <?= htmlspecialchars($_SESSION['flash']['message']) ?>
        </div>
        <?php unset($_SESSION['flash']); ?>
      <?php endif; ?>