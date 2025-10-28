<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($config['app']['name']) ?></title>
    <link rel="stylesheet" href="<?= asset('css/style.css') ?>">
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <div class="nav-brand">
                <a href="/"><?= e($config['app']['name']) ?></a>
            </div>

            <ul class="nav-menu">
                <li><a href="/">Dashboard</a></li>
                <li><a href="/books/">Books</a></li>
                <li><a href="/authors/">Authors</a></li>
                <li><a href="/categories/">Categories</a></li>
                <li><a href="/wishlist/">Wishlist</a></li>
                <li><a href="/settings/" title="Settings">⚙️</a></li>
            </ul>

            <div class="nav-user">
                <span>👤 <?= e($_SESSION['username'] ?? 'Guest') ?></span>
                <a href="/logout.php" class="btn btn-sm">Logout</a>
            </div>
        </div>
    </nav>

    <?php if ($flash = getFlash()): ?>
        <div class="flash-message alert alert-<?= e($flash['type']) ?>">
            <?= e($flash['message']) ?>
        </div>
    <?php endif; ?>

    <main>
