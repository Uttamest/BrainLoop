<nav class="breadcrumb" aria-label="Breadcrumb"><a href="<?= e(url('/decks')) ?>">My decks</a><?= icon('chevron',14) ?><a href="<?= e(url('/decks/' . ((int) $deck['id']))) ?>"><?= e($deck['title']) ?></a><?= icon('chevron',14) ?><span>Import flashcards</span></nav>
<header class="page-heading"><div><p class="eyebrow">BRING YOUR KNOWLEDGE WITH YOU</p><h1>A whole collection, in one step<span class="heading-dot">.</span></h1><p>Import flashcards into <strong><?= e($deck['title']) ?></strong> from a CSV file.</p></div></header>
<div class="form-layout"><section class="panel form-panel">
<form method="post" action="<?= e(url('/decks/' . ((int) $deck['id']) . '/import')) ?>" enctype="multipart/form-data">
<?= csrf_field() ?><input type="hidden" name="MAX_FILE_SIZE" value="1048576">
<div class="form-field"><label for="csv">Your CSV file</label><input id="csv" type="file" name="csv" accept=".csv,text/csv" required><p class="field-hint">UTF-8 CSV, up to 1 MB and 500 flashcards. Your existing cards are kept.</p><?= field_error('csv') ?></div>
<div class="import-instructions"><h2>A simple, predictable format</h2><p>Include a header row with <code>question,answer,explanation</code>. The explanation column is optional. Each following row becomes a card.</p>
<pre class="csv-example">question,answer,explanation
What is the basic unit of life?,The cell.,The smallest unit of living organisms.
What molecule carries genetic information?,DNA.,</pre>
<p class="field-hint">Wrap cells containing commas or line breaks in double quotes. Questions and answers cannot be empty. Questions allow 2,000 characters, answers 5,000, and explanations 5,000.</p></div>
<div class="form-actions"><a class="btn btn-secondary" href="<?= e(url('/decks/' . ((int) $deck['id']))) ?>">Back to deck</a><button class="btn btn-primary" type="submit"><?= icon('upload',18) ?> Import flashcards</button></div>
</form></section><aside class="tip-card"><span class="empty-icon"><?= icon('upload',30) ?></span><h2>A smoother way to move your notes.</h2><p>Save your spreadsheet as a UTF-8 CSV before uploading. Keep one clear question and one answer in each row.</p><div class="tip-divider"></div><p>Want a copy of this deck? CSV export is available from the deck page.</p><a class="text-link" href="<?= e(url('/decks/' . ((int) $deck['id']) . '/export')) ?>">Export this deck <?= icon('download',16) ?></a></aside></div>
