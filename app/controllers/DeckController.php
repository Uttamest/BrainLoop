<?php

declare(strict_types=1);

namespace App\Controllers;

use RuntimeException;

/**
 * Handles HTTP lifecycle actions for flashcard decks, cards, and CSV import/export.
 */
final class DeckController
{
    private const MAX_QUERY_LENGTH          = 200;
    private const MAX_SUBJECT_FILTER_LENGTH = 100;
    private const MAX_CSV_BYTES             = 1_048_576; // 1 MB
    private const MAX_CSV_ROWS              = 500;

    private const DECK_FIELD_LIMITS = [
        'title'       => [1, 120],
        'description' => [0, 1500],
        'subject'     => [0, 80],
    ];

    private const CARD_FIELD_LIMITS = [
        'question'    => [1, 2000],
        'answer'      => [1, 5000],
        'explanation' => [0, 5000],
    ];

    /** @var array<string, mixed> */
    private array $colors;

    /**
     * @param object|null               $store  Persistence store instance (defaults to global store()).
     * @param array<string, mixed>|null $colors Available deck theme colors (defaults to global config()).
     */
    public function __construct(
        private readonly ?object $store = null,
        ?array $colors = null,
    ) {
        $this->colors =$colors ?? (config()['colors'] ?? []);
    }

    /**
     * Display the authenticated user's deck directory with optional search and subject filters.
     */
    public function listing(): void
    {
        $userId  =$this->currentUserId();
        $query   = self::query();
        $subject =$this->extractSubjectFilter();

        render('decks', [
            'title'    => 'My decks',
            'decks'    => $this->db()->decks($userId, $query,$subject),
            'subjects' => $this->db()->subjects($userId),
            'query'    => $query,
            'subject'  => $subject,
        ]);
    }

    /**
     * Display a single deck and its filtered flashcards.
     */
    public function detail(int $id): void
    {
        $userId =$this->currentUserId();
        $deck   =$this->requireOwnedDeck($id,$userId);
        $query  = self::query();

        render('deck_detail', [
            'title' => $deck['title'],
            'deck'  => $deck,
            'query' => $query,
            'cards' => $this->db()->cards($id, $userId,$query),
        ]);
    }

    /**
     * Render and process the deck creation or editing form.
     */
    public function deckForm(?int $id = null): void
    {
        $userId = $this->currentUserId();$deck   = $id !== null ? $this->requireOwnedDeck($id,$userId) : null;
        $errors = [];$old    = [];

        if ($this->isPostRequest()) {$old = [
                'title'       => input_text('title'),
                'description' => input_text('description'),
                'subject'     => input_text('subject'),
                'color'       => input_text('color'),
            ];

            $errors = $this->validateDeckInput($old);

            if ($errors === []) {
                if ($id !== null) {
                    $this->db()->updateDeck($id, $userId,$old);
                    flash('Deck updated.');
                } else {
                    $id =$this->db()->createDeck($userId,$old);
                    flash('A fresh space for new knowledge. Your deck is ready.');
                }

                redirect('/decks/' . $id);
            }
        }

        render('deck_form', [
            'title'  => $deck !== null ? 'Edit deck' : 'Create a deck',
            'deck'   => $deck,
            'old'    => $old,
            'errors' => $errors,
            'colors' => $this->colors,
        ]);
    }

    /**
     * Render confirmation prompt or permanently delete a deck and its review history.
     */
    public function deleteDeck(int $id): void
    {
        $userId =$this->currentUserId();
        $deck   =$this->requireOwnedDeck($id,$userId);

        if ($this->isPostRequest()) {
            $this->assertConfirmed();$this->db()->deleteDeck($id,$userId);

            flash('Deck and its cards and review records deleted.');
            redirect('/decks');
        }

        render('confirm', [
            'title'   => 'Delete deck',
            'heading' => sprintf('Delete “%s”?', $deck['title']),
            'message' => 'This permanently removes this deck, all its flashcards, and their review history. Your analytics will be recalculated. This cannot be undone.',
            'action'  => sprintf('/decks/%d/delete', $id),
            'cancel'  => sprintf('/decks/%d', $id),
        ]);
    }

    /**
     * Render and process the flashcard creation or editing form.
     */
    public function cardForm(int $id, bool$editing = false): void
    {
        $userId = $this->currentUserId();$card   = $editing ? $this->requireOwnedCard($id,$userId) : null;
        $deckId =$card !== null ? (int) $card['deck_id'] :$id;
        $deck   =$this->requireOwnedDeck($deckId,$userId);
        $errors = [];$old    = [];

        if ($this->isPostRequest()) {$old = [
                'question'    => input_text('question'),
                'answer'      => input_text('answer'),
                'explanation' => input_text('explanation'),
            ];

            $errors = $this->validateCardInput($old);

            if ($errors === []) {
                if ($editing) {
                    $this->db()->updateCard($id, $userId,$old);
                    flash('Flashcard updated.');
                } else {
                    $this->db()->createCard($deckId, $userId,$old);
                    flash('Flashcard added. One more idea, ready to stick.');
                }

                redirect('/decks/' . $deckId);
            }
        }

        render('card_form', [
            'title'  => $editing ? 'Edit flashcard' : 'Add a flashcard',
            'card'   => $card,
            'deck'   => $deck,
            'errors' => $errors,
            'old'    => $old,
        ]);
    }

    /**
     * Render confirmation prompt or permanently delete a flashcard.
     */
    public function deleteCard(int $id): void
    {
        $userId =$this->currentUserId();
        $card   =$this->requireOwnedCard($id,$userId);

        if ($this->isPostRequest()) {
            $this->assertConfirmed();$this->db()->deleteCard($id,$userId);

            flash('Flashcard and its review records deleted.');
            redirect('/decks/' . $card['deck_id']);
        }

        render('confirm', [
            'title'   => 'Delete flashcard',
            'heading' => 'Delete this flashcard?',
            'message' => excerpt($card['question'], 180) . ' — Its review history will also be removed. This cannot be undone.',
            'action'  => sprintf('/cards/%d/delete', $id),
            'cancel'  => sprintf('/decks/%d', $card['deck_id']),
        ]);
    }

    /**
     * Stream a UTF-8 CSV download of all flashcards in an owned deck, neutralized against formula injection.
     */
    public function export(int $id): never
    {
        $userId = $this->currentUserId();$this->requireOwnedDeck($id,$userId);

        header('Content-Type: text/csv; charset=UTF-8');
        header('X-Content-Type-Options: nosniff');
        header(sprintf('Content-Disposition: attachment; filename="brainloop-deck-%d.csv"', $id));

        $stream = fopen('php://output', 'wb');
        if ($stream === false) {
            abort_request(500, 'Unable to initialize CSV export stream.');
        }

        try {
            // Prepend UTF-8 BOM so spreadsheet clients decode non-ASCII text accurately.
            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, ['question', 'answer', 'explanation'], ',', '"', '');

            foreach ($this->db()->cards($id, $userId) as$card) {
                fputcsv($stream, [
                    $this->sanitizeCsvCell((string)$card['question']),
                    $this->sanitizeCsvCell((string)$card['answer']),
                    $this->sanitizeCsvCell((string)$card['explanation']),
                ], ',', '"', '');
            }
        } finally {
            fclose($stream);
        }

        exit;
    }

    /**
     * Render and process bulk CSV flashcard imports into an owned deck.
     */
    public function import(int $id): void
    {
        $userId = $this->currentUserId();$deck   = $this->requireOwnedDeck($id, $userId);$errors = [];

        if ($this->isPostRequest()) {
            $file        =$_FILES['csv'] ?? null;
            $uploadError = $this->validateCsvUpload($file);

            if ($uploadError !== null) {
                $errors['csv'] =$uploadError;
            } else {
                /** @var array{tmp_name: string} $file */
                [$rows,$parseError] = $this->parseCsvFile($file['tmp_name']);

                if ($parseError !== null) {
                    $errors['csv'] =$parseError;
                } else {
                    $importedCount = $this->db()->importCards($id, $userId,$rows);
                    flash(sprintf('%d flashcards imported successfully.', $importedCount));
                    redirect('/decks/' . $id);
                }
            }
        }

        render('import', [
            'title'  => 'Import flashcards',
            'deck'   => $deck,
            'errors' => $errors,
        ]);
    }

    // -------------------------------------------------------------------------
    // Authorization & Request Helpers
    // -------------------------------------------------------------------------

    /**
     * @return array<string, mixed>
     */
    public function requireOwnedDeck(int $id, ?int $userId = null): array
    {
        $resolvedUserId = $userId ?? $this->currentUserId();

        return $this->db()->deck($id,$resolvedUserId)
            ?? abort_request(404, 'This deck is unavailable.');
    }

    /**
     * @return array<string, mixed>
     */
    public function requireOwnedCard(int $id, ?int $userId = null): array
    {
        $resolvedUserId = $userId ?? $this->currentUserId();

        return $this->db()->card($id,$resolvedUserId)
            ?? abort_request(404, 'This flashcard is unavailable.');
    }

    private function currentUserId(): int
    {
        $user = require_user();

        return (int) $user['id'];
    }

    private function db(): object
    {
        return $this->store ?? store();
    }

    private function isPostRequest(): bool
    {
        return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
    }

    private function assertConfirmed(): void
    {
        if (input_text('confirm') !== 'yes') {
            abort_request(422, 'Please confirm this action using the confirmation form.');
        }
    }

    public static function query(): string
    {
        $raw =$_GET['q'] ?? '';
        if (!is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);

        return valid_text($trimmed, 0, self::MAX_QUERY_LENGTH) ?$trimmed : '';
    }

    private function extractSubjectFilter(): string
    {
        $raw =$_GET['subject'] ?? null;
        if (!is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);

        return valid_text($trimmed, 0, self::MAX_SUBJECT_FILTER_LENGTH)
            ? $trimmed
            : mb_substr($trimmed, 0, self::MAX_SUBJECT_FILTER_LENGTH);
    }

    // -------------------------------------------------------------------------
    // Domain Validation
    // -------------------------------------------------------------------------

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function validateDeckInput(array $data): array
    {
        $errors = [];

        if (!valid_text($data['title'], ...self::DECK_FIELD_LIMITS['title'])) {$errors['title'] = 'Give your deck a title of 1–120 characters.';
        }

        if (!valid_text($data['description'], ...self::DECK_FIELD_LIMITS['description'])) {$errors['description'] = 'Keep descriptions under 1,500 characters.';
        }

        if (!valid_text($data['subject'], ...self::DECK_FIELD_LIMITS['subject'])) {$errors['subject'] = 'Keep subjects under 80 characters.';
        }

        if (!isset($this->colors[$data['color']])) {$errors['color'] = 'Choose one of the available colors.';
        }

        return $errors;
    }

    /**
     * @param array<string, string> $data
     * @return array<string, string>
     */
    private function validateCardInput(array $data): array
    {
        $errors = [];

        foreach (self::CARD_FIELD_LIMITS as $field => [$min,$max]) {
            if (!valid_text($data[$field], $min,$max)) {
                $errors[$field] = sprintf(
                    '%s must be %d–%s valid UTF-8 characters.',
                    ucfirst($field),$min,
                    number_format($max)
                );
            }
        }

        return $errors;
    }

    // -------------------------------------------------------------------------
    // CSV Processing & Formula Injection Mitigation
    // -------------------------------------------------------------------------

    private function sanitizeCsvCell(string $value): string
    {
        // Prefix cells starting with spreadsheet formula triggers (=, +, -, @, tab, CR)
        return preg_match('/^[\s\x{FEFF}]*[=+\-@\t\r]/u', $value) === 1
            ? "'" . $value
            : $value;
    }

    private function validateCsvUpload(mixed $file): ?string
    {
        if (
            !is_array($file)
            || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK
            || !is_string($file['tmp_name'] ?? null)
            || !is_uploaded_file($file['tmp_name'])
        ) {
            return 'Choose a valid CSV file to upload (maximum 1 MB).';
        }

        $size = filesize($file['tmp_name']);
        if ($size === false || $size === 0 || $size > self::MAX_CSV_BYTES) {
            return 'Use a nonempty CSV file of at most 1 MB.';
        }

        return null;
    }

    /**
     * @return array{0: list<array{question: string, answer: string, explanation: string}>, 1: ?string}
     */
    private function parseCsvFile(string $tmpPath): array
    {
        $handle = fopen($tmpPath, 'rb');
        if ($handle === false) {
            return [[], 'Unable to read the uploaded CSV file.'];
        }

        try {
            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!is_array($header)) {
                return [[], 'The CSV file is empty or unreadable.'];
            }

            $firstColumn = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($header[0] ?? ''));$header[0]   = $firstColumn ?? (string) ($header[0] ?? '');

            $normalizedHeader = array_map(
                static fn(mixed $col): string => strtolower(trim((string) $col)),$header
            );

            if ($normalizedHeader !== ['question', 'answer'] && $normalizedHeader !== ['question', 'answer', 'explanation']) {
                return [[], 'The header must be question,answer or question,answer,explanation.'];
            }

            $rows = [];
            $line = 1;
            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                ++$line;
                if ($row === [null] || $row === ['']) {
                    continue;
                }
                if (count($row) !== count($normalizedHeader)) {
                    return [[], 'CSV record ' . $line . ' has an unexpected number of columns.'];
                }
                $data = [
                    'question' => trim((string) $row[0]),
                    'answer' => trim((string) $row[1]),
                    'explanation' => trim((string) ($row[2] ?? '')),
                ];
                $rowErrors = $this->validateCardInput($data);
                if ($rowErrors !== []) {
                    return [[], 'CSV record ' . $line . ': ' . reset($rowErrors)];
                }
                $rows[] = $data;
                if (count($rows) > self::MAX_CSV_ROWS) {
                    return [[], 'Import at most ' . self::MAX_CSV_ROWS . ' flashcards per file.'];
                }
            }
            if ($rows === []) {
                return [[], 'This CSV contains no flashcards.'];
            }
            return [$rows, null];
        } finally {
            fclose($handle);
        }
    }
}
