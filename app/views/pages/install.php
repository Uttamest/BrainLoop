<section class="container section">
<header class="page-heading"><div><p class="eyebrow">A QUICK LOCAL SETUP</p><h1>Make room for BrainLoop<span class="heading-dot">.</span></h1><p>Connect your local XAMPP database once, then start your learning space.</p></div></header>
<div class="form-layout">
<section class="panel form-panel">
<div class="notice notice-info" role="note"><?= icon('info',20) ?><span>Start <strong>Apache</strong> and <strong>MySQL</strong> in the XAMPP Control Panel before continuing. This setup works only from this computer.</span></div>
<?php if (!empty($errors['setup'])): ?><div class="notice notice-error" role="alert"><?= icon('info',20) ?><span><?= e($errors['setup']) ?></span></div><?php endif; ?>
<form method="post" action="<?= e(url('/setup')) ?>" autocomplete="off">
<?= csrf_field() ?>
<div class="form-grid">
<div class="form-field"><label for="setup-host">Database host</label><select id="setup-host" name="host"><option value="127.0.0.1" <?= old('host','127.0.0.1') === '127.0.0.1' ? 'selected' : '' ?>>127.0.0.1</option><option value="localhost" <?= old('host') === 'localhost' ? 'selected' : '' ?>>localhost</option></select><?= field_error('host') ?></div>
<div class="form-field"><label for="setup-port">MySQL port</label><input id="setup-port" name="port" type="number" min="1" max="65535" step="1" inputmode="numeric" required value="<?= e(old('port','3306')) ?>" aria-describedby="setup-port-hint"><p id="setup-port-hint" class="field-hint">Usually 3306. Use the port shown in XAMPP if you changed it.</p><?= field_error('port') ?></div>
</div>
<div class="form-field"><label for="setup-name">Database name</label><input id="setup-name" name="name" required maxlength="64" pattern="[A-Za-z0-9_]{1,64}" value="<?= e(old('name','brainloop')) ?>" aria-describedby="setup-name-hint"><p id="setup-name-hint" class="field-hint">Use letters, numbers, and underscores. A new database is created if needed. Compatible existing BrainLoop tables are kept.</p><?= field_error('name') ?></div>
<div class="form-field"><label for="setup-admin-user">MySQL administrator</label><input id="setup-admin-user" name="admin_user" required maxlength="80" value="<?= e(old('admin_user','root')) ?>" autocomplete="off" aria-describedby="setup-admin-hint"><p id="setup-admin-hint" class="field-hint">A fresh local XAMPP installation usually uses <strong>root</strong>. This is your database administrator, not your BrainLoop learning account.</p><?= field_error('admin_user') ?></div>
<div class="form-field"><label for="setup-admin-password">MySQL administrator password <span class="optional">if set</span></label><input id="setup-admin-password" name="admin_password" type="password" maxlength="4096" autocomplete="off" aria-describedby="setup-password-hint"><p id="setup-password-hint" class="field-hint">Leave this blank only if your local XAMPP MySQL account has no password. The administrator password is used for this setup only and is not saved by BrainLoop.</p><?= field_error('admin_password') ?></div>
<div class="form-actions"><button class="btn btn-primary" type="submit"><?= icon('check',18) ?> Set up BrainLoop <?= icon('arrow',18) ?></button></div>
</form>
</section>
<aside class="tip-card"><span class="empty-icon"><?= icon('shield',30) ?></span><h2>A fresh start, safely.</h2><p>Setup creates the BrainLoop tables and a separate database account restricted to this database. It saves private application settings and then closes this installer.</p><div class="tip-divider"></div><p><strong>Your existing data stays put.</strong><br>No database, deck, card, or review is deleted. If the selected database belongs to another application, setup stops and asks for a new name.</p><div class="tip-divider"></div><p>Once setup finishes, create your own BrainLoop account. No default student account or sample review history is added.</p></aside>
</div>
</section>
