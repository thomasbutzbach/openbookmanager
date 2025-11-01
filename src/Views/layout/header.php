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
            <?php
            // Allow HTML in flash messages if explicitly marked as safe
            if (!empty($flash['allow_html'])) {
                echo $flash['message'];
            } else {
                echo e($flash['message']);
            }
            ?>
        </div>
    <?php endif; ?>

    <!-- Global Confirmation Dialog -->
    <div x-data="confirmDialog()" x-cloak>
        <div x-show="isOpen"
             @keydown.escape.window="cancel()"
             style="position: fixed; inset: 0; background: rgba(0,0,0,0.5); display: flex; align-items: center; justify-content: center; z-index: 9999;"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0">

            <div @click.away="cancel()"
                 style="background: white; border-radius: 0.5rem; max-width: 500px; width: 90%; box-shadow: 0 20px 60px rgba(0,0,0,0.3);"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 transform scale-95"
                 x-transition:enter-end="opacity-100 transform scale-100"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 transform scale-100"
                 x-transition:leave-end="opacity-0 transform scale-95">

                <!-- Header -->
                <div style="padding: 1.5rem; border-bottom: 1px solid var(--border-color);">
                    <h3 style="margin: 0; color: var(--danger-color);">⚠️ Confirm Action</h3>
                </div>

                <!-- Body -->
                <div style="padding: 1.5rem;">
                    <p style="margin: 0;" x-text="message"></p>
                </div>

                <!-- Footer -->
                <div style="padding: 1rem 1.5rem; border-top: 1px solid var(--border-color); display: flex; gap: 0.75rem; justify-content: flex-end;">
                    <button @click="cancel()" class="btn btn-secondary">Cancel</button>
                    <button @click="confirm()" class="btn btn-danger">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <main>
