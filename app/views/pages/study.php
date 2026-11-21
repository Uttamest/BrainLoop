<?php $studyPercent = $total > 0 ? (int) round(($session['completed'] / $total) * 100) : 0; ?>
<div class="study-shell">
<div class="study-top"><a class="text-link" href="<?= e(url(!empty($deck['id']) ? '/decks/'.(int)$deck['id'] : '/study')) ?>"><?= icon('arrow-left',17) ?> <?= !empty($deck['id']) ? 'Back to deck' : 'Study overview' ?></a><span class="badge <?= $session['mode'] === 'practice' ? 'badge-teal' : 'badge-purple' ?>"><?= $session['mode'] === 'practice' ? 'PRACTICE MODE' : 'DUE REVIEW' ?></span><form method="post" action="<?= e(url('/study/exit')) ?>"><?= csrf_field() ?><button class="btn btn-ghost btn-small" type="submit">Finish session <?= icon('close',16) ?></button></form></div>
<header class="study-heading"><p class="eyebrow"><?= e($deck['title'] ?? 'All decks') ?></p><h1>A little more memorable.</h1><p>Card <?= (int) $position ?> of <?= (int) $total ?> <span aria-hidden="true">·</span> <?= (int) $session['completed'] ?> completed</p></header>
<div class="study-progress"><div class="progress-track" role="progressbar" aria-label="Study session progress" aria-valuenow="<?= $studyPercent ?>" aria-valuemin="0" aria-valuemax="100"><span class="progress-fill" style="width: <?= $studyPercent ?>%"></span></div><span><?= $studyPercent ?>%</span></div>
<article class="study-flashcard <?= $revealed ? 'is-revealed' : '' ?>">
<div class="study-card-top"><span class="study-label"><?= icon('cards',16) ?> <?= $revealed ? 'QUESTION & ANSWER' : 'TAKE A MOMENT TO RECALL' ?></span><span class="badge"><?= e(ucfirst(str_replace('_',' ',$card['review_state'] ?? 'new'))) ?></span></div>
<div class="study-question"><h2><?= nl2br(e($card['question'])) ?></h2></div>
<?php if ($revealed): ?>
<div class="study-answer"><span class="study-label">THE ANSWER</span><div class="answer-text"><?= nl2br(e($card['answer'])) ?></div><?php if (!empty($card['explanation'])): ?><div class="study-explanation"><span class="study-label"><?= icon('lightbulb',16) ?> A LITTLE MORE CONTEXT</span><p><?= nl2br(e($card['explanation'])) ?></p></div><?php endif; ?></div>
<?php else: ?>
<div class="study-card-hint"><?= icon('sparkles',18) ?> Think it through before turning the card.</div>
<?php endif; ?>
</article>
<div class="study-controls">
<?php if (!$revealed): ?>
<form method="post" action="<?= e(url('/study/reveal')) ?>"><?= csrf_field() ?><input type="hidden" name="nonce" value="<?= e($session['nonce']) ?>"><button class="btn btn-primary reveal-button" type="submit">Show answer <?= icon('arrow',18) ?></button></form><p>Try saying the answer out loud, or write it down.</p>
<?php else: ?>
<h2>How did that feel?</h2><p>Your honest rating helps shape your next review.</p><form method="post" action="<?= e(url('/study/rate')) ?>"><?= csrf_field() ?><input type="hidden" name="nonce" value="<?= e($session['nonce']) ?>"><div class="rating-grid"><button class="rating-button rating-again" name="rating" value="again" type="submit"><strong>Again</strong><span>I couldn't recall it</span></button><button class="rating-button rating-hard" name="rating" value="hard" type="submit"><strong>Hard</strong><span>It took some effort</span></button><button class="rating-button rating-good" name="rating" value="good" type="submit"><strong>Good</strong><span>I remembered it</span></button><button class="rating-button rating-easy" name="rating" value="easy" type="submit"><strong>Easy</strong><span>It came right to me</span></button></div></form>
<?php endif; ?>
</div>
<p class="page-footnote"><?= $session['mode'] === 'practice' ? 'Practice is recorded in your history. Existing due dates and scheduling stay unchanged.' : 'Your rating is saved immediately. Each card appears once in this session.' ?></p>
</div>
