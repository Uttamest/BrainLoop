<?php $features = [
 ['cards','Smart Flashcards','Turn complex ideas into simple questions and answers.'],
 ['loop','Spaced Repetition','Review information when it matters most.'],
 ['decks','Organized Decks','Keep every subject and topic in one place.'],
 ['analytics','Progress Tracking','See how your learning develops over time.'],
 ['lightbulb','Active Recall','Strengthen memory by retrieving answers yourself.'],
 ['target','Focused Study Sessions','Spend less time organizing and more time learning.'],
]; ?>
<div class="feature-grid"><?php foreach ($features as $i => $feature): ?><article class="feature-card"><span class="feature-icon tone-<?= $i % 3 ?>"><?= icon($feature[0],25) ?></span><h3><?= e($feature[1]) ?></h3><p><?= e($feature[2]) ?></p></article><?php endforeach; ?></div>
