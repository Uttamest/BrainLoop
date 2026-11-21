<div class="steps-grid">
<?php foreach ([['decks','Create a Deck','Give your subject a home. Organize the topics you want to remember.'],['cards','Add Flashcards','Write a question, add your answer, and keep one idea on each card.'],['lightbulb','Practice Active Recall','Think it through before revealing the answer. Rate how well you remembered.'],['loop','Review and Improve','Come back to your due cards and watch your learning take shape.']] as $i => $step): ?>
  <article class="step"><span class="step-number">0<?= $i + 1 ?></span><div class="step-symbol"><?= icon($step[0],27) ?></div><h3><?= e($step[1]) ?></h3><p><?= e($step[2]) ?></p></article>
<?php endforeach; ?>
</div>
