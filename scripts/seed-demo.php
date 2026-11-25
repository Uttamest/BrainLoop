<?php
declare(strict_types=1);

define('BRAINLOOP_CLI', true);
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
$email = trim($argv[1] ?? '');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Usage: php scripts/seed-demo.php your-existing-account@example.com\nRegister an account first. This command never creates a default account.\n");
    exit(1);
}
require dirname(__DIR__) . '/app/bootstrap.php';
App\Core\Database::migrate();
$pdo = db();
$find = $pdo->prepare('SELECT id FROM users WHERE LOWER(email) = LOWER(:email)');
$find->execute(['email' => $email]);
$userId = $find->fetchColumn();
if (!$userId) {
    fwrite(STDERR, "No account found for that email. Register through the website first.\n");
    exit(1);
}
$content = [
    ['Computer Science', '#6C5CE7', [
        ['What does HTML stand for?', 'HyperText Markup Language.', 'HTML provides the structure and meaning of web documents.'],
        ['What is the primary purpose of CSS?', 'To control the visual presentation of web content.', 'Presentation includes layout, typography, colors, and responsive behavior.'],
        ['What does PHP commonly do in a web application?', 'Execute server-side logic and generate responses.', 'PHP can validate forms, manage sessions, query a database, and render HTML.'],
    ]],
    ['Mathematics', '#24B8A6', [
        ['What is the derivative of x squared with respect to x?', '2x.', 'The power rule gives d(x^n)/dx = n x^(n-1).'],
        ['What is the area formula for a circle?', 'A = pi × r², where r is the radius.', 'The radius is the distance from the center to the edge.'],
        ['What is a prime number?', 'An integer greater than 1 with exactly two positive divisors: 1 and itself.', '2 is the only even prime.'],
    ]],
    ['Biology', '#5685CF', [
        ['What is the basic unit of life?', 'The cell.', 'Organisms consist of one or more cells.'],
        ['What molecule carries genetic information in cellular organisms?', 'DNA (deoxyribonucleic acid).', 'Some viruses use RNA as their genetic material.'],
        ['What is photosynthesis?', 'The process by which plants, algae, and some bacteria use light energy to build organic compounds.', 'Oxygenic photosynthesis uses carbon dioxide and water and releases oxygen.'],
    ]],
    ['English Vocabulary', '#D76880', [
        ['What does “concise” mean?', 'Expressing information clearly using few words.', 'A concise explanation keeps essential meaning without unnecessary detail.'],
        ['What does “resilient” mean?', 'Able to recover or adapt after difficulty.', 'For example: a resilient community.'],
        ['What is a synonym?', 'A word with the same or a similar meaning as another word.', '“Begin” and “start” are synonyms.'],
    ]],
    ['General Knowledge', '#D98C39', [
        ['Which planet is closest to the Sun?', 'Mercury.', 'Mercury is the smallest planet in the Solar System.'],
        ['How many sides does a hexagon have?', 'Six.', 'A regular hexagon has six equal sides and six equal interior angles.'],
        ['What is the chemical formula of water?', 'H₂O.', 'Each water molecule contains two hydrogen atoms and one oxygen atom.'],
    ]],
];
$now = gmdate('Y-m-d H:i:s');
$decks = 0;
$cards = 0;
$pdo->beginTransaction();
try {
    $exists = $pdo->prepare('SELECT id FROM decks WHERE user_id = ? AND title = ?');
    $deckInsert = $pdo->prepare('INSERT INTO decks (user_id,title,description,subject,color,created_at,updated_at) VALUES (?,?,?,?,?,?,?)');
    $cardInsert = $pdo->prepare('INSERT INTO flashcards (deck_id,question,answer,explanation,created_at,updated_at) VALUES (?,?,?,?,?,?)');
    $progressInsert = $pdo->prepare("INSERT INTO card_progress (user_id,flashcard_id,review_state,due_at,interval_days,ease_factor,repetition_count,total_reviews) VALUES (?,?,'new',?,0,2.5,0,0)");
    foreach ($content as [$subject, $color, $items]) {
        $title = 'Sample · ' . $subject;
        $exists->execute([$userId, $title]);
        if ($exists->fetchColumn()) {
            continue;
        }
        $deckInsert->execute([$userId, $title, 'Optional sample flashcards for exploring BrainLoop. Edit or delete these at any time.', $subject, $color, $now, $now]);
        $deckId = (int) $pdo->lastInsertId();
        ++$decks;
        foreach ($items as [$question, $answer, $explanation]) {
            $cardInsert->execute([$deckId, $question, $answer, $explanation, $now, $now]);
            $progressInsert->execute([$userId, (int) $pdo->lastInsertId(), $now]);
            ++$cards;
        }
    }
    $pdo->commit();
    echo "Added {$decks} sample decks and {$cards} cards. Existing sample decks were skipped. No review history was fabricated.\n";
} catch (Throwable $error) {
    $pdo->rollBack();
    fwrite(STDERR, 'Sample import failed: ' . $error->getMessage() . "\n");
    exit(1);
}
