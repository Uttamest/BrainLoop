<?php
$publicPages = ['home','features','how','about','privacy','terms','login','register','install','error'];
$isApp = $user !== null && !in_array($page, $publicPages, true);
$isAuth = in_array($page, ['login','register','install'], true);
$currentPath = $path ?? request_path();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="description" content="BrainLoop helps you create smarter flashcards, practice active recall, and build a consistent study habit. Learn smarter. Remember longer.">
  <meta name="theme-color" content="#6C5CE7">
  <title><?= e($title) ?><?= $page === 'home' ? '' : ' · BrainLoop' ?></title>
  <link rel="icon" href="<?= e(asset_url('/assets/icons/favicon.svg')) ?>" type="image/svg+xml">
  <link rel="stylesheet" href="<?= e(asset_url('/assets/css/styles.css')) ?>"><link rel="stylesheet" href="<?= e(asset_url('/assets/css/responsive.css')) ?>">
</head>
<body class="<?= $isApp ? 'app-body' : ($isAuth ? 'auth-body' : 'public-body') ?>">
<a class="skip-link" href="#main-content">Skip to content</a>
<?php if ($isApp): ?>
  <aside class="app-sidebar">
    <a class="brand" href="<?= e(url('/dashboard')) ?>" aria-label="BrainLoop dashboard"><?= brand_mark() ?><span>BrainLoop</span></a>
    <span class="sidebar-caption">YOUR LEARNING SPACE</span>
    <nav class="app-nav" aria-label="Application">
    <?php foreach ([['/dashboard','dashboard','Overview'],['/decks','decks','My Decks'],['/study','study','Study'],['/analytics','analytics','Analytics'],['/search','search','Search']] as $item): $active = $item[0] === '/dashboard' ? $currentPath === $item[0] : str_starts_with($currentPath, $item[0]); ?>
      <a href="<?= e(url($item[0])) ?>" class="<?= $active ? 'active' : '' ?>"<?= $active ? ' aria-current="page"' : '' ?>><?= icon($item[1]) ?><span><?= e($item[2]) ?></span><?php if ($active): ?><span class="nav-active-dot"></span><?php endif; ?></a>
    <?php endforeach; ?>
    </nav>
    <div class="sidebar-note"><span class="sidebar-note-icon"><?= icon('sparkles',24) ?></span><strong>Small steps.<br>Stronger memories.</strong><p>A little recall today goes a long way tomorrow.</p><a href="<?= e(url('/study')) ?>">Find your focus <?= icon('arrow',16) ?></a></div>
    <div class="sidebar-bottom"><a class="sidebar-settings <?= $currentPath === '/settings' ? 'active' : '' ?>" href="<?= e(url('/settings')) ?>"><?= icon('settings') ?> Settings</a><div class="sidebar-profile"><span class="avatar"><?= e(avatar_initial($user['full_name'])) ?></span><span class="profile-name"><strong><?= e($user['full_name']) ?></strong><small>Personal workspace</small></span><form action="<?= e(url('/logout')) ?>" method="post"><?= csrf_field() ?><button class="icon-button" title="Log out" aria-label="Log out"><?= icon('logout',19) ?></button></form></div></div>
  </aside>
  <div class="app-workspace"><header class="app-topbar"><span class="breadcrumb">My workspace <span>/</span> <strong><?= e($title) ?></strong></span><a class="topbar-search" href="<?= e(url('/search')) ?>"><?= icon('search',17) ?><span>Search your knowledge</span></a><a class="mobile-brand brand" href="<?= e(url('/dashboard')) ?>"><?= brand_mark(30) ?><span>BrainLoop</span></a><a class="topbar-account" href="<?= e(url('/settings')) ?>" aria-label="Account settings"><span class="avatar avatar-small"><?= e(avatar_initial($user['full_name'])) ?></span></a></header>
  <main id="main-content" class="app-main">
<?php else: ?>
  <header class="site-header"><div class="container header-inner"><a class="brand" href="<?= e(url('/')) ?>" aria-label="BrainLoop home"><?= brand_mark() ?><span>BrainLoop</span></a><nav class="desktop-nav" aria-label="Main"><a href="<?= e(url('/features')) ?>"<?= $page === 'features' ? ' aria-current="page"' : '' ?>>Features</a><a href="<?= e(url('/how-it-works')) ?>"<?= $page === 'how' ? ' aria-current="page"' : '' ?>>How it works</a><a href="<?= e(url('/about')) ?>"<?= $page === 'about' ? ' aria-current="page"' : '' ?>>About</a></nav><div class="header-actions"><?php if ($user): ?><a class="btn btn-primary btn-small" href="<?= e(url('/dashboard')) ?>">My workspace <?= icon('arrow',16) ?></a><?php else: ?><a class="login-link" href="<?= e(url('/login')) ?>">Log in</a><a class="btn btn-primary btn-small" href="<?= e(url('/register')) ?>">Get Started <?= icon('arrow',16) ?></a><?php endif; ?></div><details class="mobile-menu"><summary aria-label="Open navigation"><?= icon('menu',23) ?></summary><nav aria-label="Mobile"><a href="<?= e(url('/features')) ?>">Features</a><a href="<?= e(url('/how-it-works')) ?>">How it works</a><a href="<?= e(url('/about')) ?>">About</a><a href="<?= e(url('/login')) ?>">Log in</a><a href="<?= e(url('/register')) ?>">Get started</a></nav></details></div></header>
  <main id="main-content">
<?php endif; ?>
<?php if (!empty($flashes)): ?><div class="flash-container<?= !$isApp ? ' container' : '' ?>"><?php foreach ($flashes as $flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= icon($flash['type'] === 'success' ? 'check' : 'info') ?><span><?= e($flash['message']) ?></span></div><?php endforeach; ?></div><?php endif; ?>
<?php if (!empty($errors['_form'])): ?><div class="notice notice-error<?= !$isApp ? ' container' : '' ?>" role="alert"><?= icon('info') ?><span><?= e($errors['_form']) ?></span></div><?php endif; ?>
<?php if (!empty($errors['action'])): ?><div class="notice notice-error<?= !$isApp ? ' container' : '' ?>" role="alert"><?= icon('info') ?><span><?= e($errors['action']) ?></span></div><?php endif; ?>
<?= $content ?>
</main>
<?php if ($isApp): ?><footer class="app-footer"><span>&copy; <?= date('Y') ?> BrainLoop</span><span>Learn smarter. Remember longer.</span><a href="<?= e(url('/about')) ?>">Meet the project team <?= icon('arrow',14) ?></a></footer></div><nav class="mobile-app-nav" aria-label="Mobile application"><a href="<?= e(url('/dashboard')) ?>"<?= $currentPath === '/dashboard' ? ' aria-current="page"' : '' ?>><?= icon('dashboard') ?><span>Overview</span></a><a href="<?= e(url('/decks')) ?>"<?= str_starts_with($currentPath, '/decks') ? ' aria-current="page"' : '' ?>><?= icon('decks') ?><span>Decks</span></a><a href="<?= e(url('/study')) ?>"<?= str_starts_with($currentPath, '/study') ? ' aria-current="page"' : '' ?>><?= icon('study') ?><span>Study</span></a><a href="<?= e(url('/analytics')) ?>"<?= $currentPath === '/analytics' ? ' aria-current="page"' : '' ?>><?= icon('analytics') ?><span>Analytics</span></a><a href="<?= e(url('/settings')) ?>"<?= $currentPath === '/settings' ? ' aria-current="page"' : '' ?>><?= icon('settings') ?><span>Account</span></a></nav><?php elseif (!$isAuth): require __DIR__ . '/../partials/footer.php'; else: ?><footer class="auth-footer">&copy; <?= date('Y') ?> BrainLoop <span>·</span> <a href="<?= e(url('/privacy')) ?>">Privacy</a> <span>·</span> <a href="<?= e(url('/terms')) ?>">Terms</a></footer><?php endif; ?>
</body>
</html>
