<?php

declare(strict_types=1);

namespace App\Controllers;

final class DeckController
{
    private const MAX_QUERY_LENGTH = 200;
    private const MAX_SUBJECT_FILTER_LENGTH = 100;

    private const DECK_LIMITS = [
        'title'       => [1, 120],
        'description' => [0, 1500],
        'subject'     => [0, 80],
    ];

    private const CARD_LIMITS = [
        'question'    => [1, 2000],
        'answer'      => [1, 5000],
        'explanation' => [0, 5000],
    ];

    private const MAX_CSV_BYTES = 1048576; // 1 MB
    private const MAX_CSV_ROWS  = 500;

    public static function listing(): void
    {
        $user    = require_user();
        $userId  = (int) $user['id'];
        $query   = self::query();
        $subject = self::subjectFilter();

        render('decks', [
            'title'    => 'My decks',
            'decks'    => store()->decks($userId, $query, $subject),
            'subjects' => store()->subjects($userId),
            'query'    => $query,
            'subject'  => $subject,
        ]);
    }

    public static function detail(int $id): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $deck   = self::ownedDeck($id, $userId);
        $query  = self::query();

        render('deck_detail', [
            'title' => $deck['title'],
            'deck'  => $deck,
            'query' => $query,
            'cards' => store()->cards($id, $userId, $query),
        ]);
    }

    public static function deckForm(?int $id = null): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $deck   = $id !== null ? self::ownedDeck($id, $userId) : null;
        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = [
                'title'       => input_text('title'),
                'description' => input_text('description'),
                'subject'     => input_text('subject'),
                'color'       => input_text('color'),
            ];

            $errors = self::validateDeck($old);

            if ($errors === []) {
                if ($id !== null) {
                    store()->updateDeck($id, $userId, $old);
                    flash('Deck updated.');
                } else {
                    $id = store()->createDeck($userId, $old);
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
            'colors' => config()['colors'],
        ]);
    }

    public static function deleteDeck(int $id): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $deck   = self::ownedDeck($id, $userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::requireConfirmation();
            store()->deleteDeck($id, $userId);

            flash('Deck and its cards and review records deleted.');
            redirect('/decks');
        }

        render('confirm', [
            'title'   => 'Delete deck',
            'heading' => 'Delete “' . $deck['title'] . '”?',
            'message' => 'This permanently removes this deck, all its flashcards, and their review history. Your analytics will be recalculated. This cannot be undone.',
            'action'  => '/decks/' . $id . '/delete',
            'cancel'  => '/decks/' . $id,
        ]);
    }

    public static function cardForm(int $id, bool $editing = false): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $card   = $editing ? self::ownedCard($id, $userId) : null;
        $deckId = $card !== null ? (int) $card['deck_id'] : $id;
        $deck   = self::ownedDeck($deckId, $userId);
        $errors = [];
        $old    = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $old = [
                'question'    => input_text('question'),
                'answer'      => input_text('answer'),
                'explanation' => input_text('explanation'),
            ];

            $errors = self::validateCard($old);

            if ($errors === []) {
                if ($editing) {
                    store()->updateCard($id, $userId, $old);
                    flash('Flashcard updated.');
                } else {
                    store()->createCard($deckId, $userId, $old);
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

    public static function deleteCard(int $id): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $card   = self::ownedCard($id, $userId);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            self::requireConfirmation();
            store()->deleteCard($id, $userId);

            flash('Flashcard and its review records deleted.');
            redirect('/decks/' . $card['deck_id']);
        }

        render('confirm', [
            'title'   => 'Delete flashcard',
            'heading' => 'Delete this flashcard?',
            'message' => excerpt($card['question'], 180) . ' — Its review history will also be removed. This cannot be undone.',
            'action'  => '/cards/' . $id . '/delete',
            'cancel'  => '/decks/' . $card['deck_id'],
        ]);
    }

    public static function export(int $id): never
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        self::ownedDeck($id, $userId);

        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="brainloop-deck-' . $id . '.csv"');

        $out = fopen('php://output', 'wb');
        if ($out === false) {
            abort_request(500, 'Unable to generate CSV export stream.');
        }

        // Write UTF-8 BOM for spreadsheet compatibility
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['question', 'answer', 'explanation'], ',', '"', '');

        foreach (store()->cards($id, $userId) as $card) {
            $row = [];
            foreach (['question', 'answer', 'explanation'] as $field) {
                $row[] = self::escapeCsvFormula((string) $card[$field]);
            }
            fputcsv($out, $row, ',', '"', '');
        }

        fclose($out);
        exit;
    }

    public static function import(int $id): void
    {
        $user   = require_user();
        $userId = (int) $user['id'];
        $deck   = self::ownedDeck($id, $userId);
        $errors = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file = $_FILES['csv'] ?? null;
            $uploadError = self::validateCsvUpload($file);

            if ($uploadError !== null) {
                $errors['csv'] = $uploadError;
            } else {
                [$rows, $parseError] = self::parseCsvFile($file['tmp_name']);

                if ($parseError !== null) {
                    $errors['csv'] = $parseError;
                } else {
                    $count = store()->importCards($id, $userId, $rows);
                    flash($count . ' flashcards imported successfully.');
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

    public static function ownedDeck(int $id, ?int $userId = null): array
    {
        $userId ??= (int) require_user()['id'];

        return store()->deck($id, $userId)
            ?? abort_request(404, 'This deck is unavailable.');
    }

    public static function ownedCard(int $id, ?int $userId = null): array
    {
        $userId ??= (int) require_user()['id'];

        return store()->card($id, $userId)
            ?? abort_request(404, 'This flashcard is unavailable.');
    }

    public static function query(): string
    {
        $raw = $_GET['q'] ?? '';
        if (!is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);

        return valid_text($trimmed, 0, self::MAX_QUERY_LENGTH) ? $trimmed : '';
    }

    private static function subjectFilter(): string
    {
        $raw = $_GET['subject'] ?? null;
        if (!is_string($raw)) {
            return '';
        }

        return mb_substr($raw, 0, self::MAX_SUBJECT_FILTER_LENGTH);
    }

    private static function validateDeck(array $data): array
    {
        $errors = [];

        if (!valid_text($data['title'], ...self::DECK_LIMITS['title'])) {
            $errors['title'] = 'Give your deck a title of 1–120 characters.';
        }

        if (!valid_text($data['description'], ...self::DECK_LIMITS['description'])) {
            $errors['description'] = 'Keep descriptions under 1,500 characters.';
        }

        if (!valid_text($data['subject'], ...self::DECK_LIMITS['subject'])) {
            $errors['subject'] = 'Keep subjects under 80 characters.';
        }

        if (!isset(config()['colors'][$data['color']])) {
            $errors['color'] = 'Choose one of the available colors.';
        }

        return $errors;
    }

    private static function validateCard(array $data): array
    {
        $errors = [];

        foreach (self::CARD_LIMITS as $field => [$min, $max]) {
            if (!valid_text($data[$field], $min, $max)) {
                $errors[$field] = sprintf(
                    '%s must be %d–%s valid UTF-8 characters.',
                    ucfirst($field),
                    $min,
                    number_format($max)
                );
            }
        }

        return $errors;
    }

    private static function requireConfirmation(): void
    {
        if (input_text('confirm') !== 'yes') {
            abort_request(422, 'Please confirm this action using the confirmation form.');
        }
    }

    private static function escapeCsvFormula(string $value): string
    {
        return preg_match('/^[\s\x{FEFF}]*[=+\-@\t\r]/u', $value) === 1
            ? "'" . $value
            : $value;
    }

    private static function validateCsvUpload(mixed $file): ?string
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
     * @return array{0: array<int, array<string, string>>, 1: ?string}
     */
    private static function parseCsvFile(string $tmpPath): array
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

            $header[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $header[0]);
            $header    = array_map(static fn($v): string => strtolower(trim((string) $v)), $header);

            if ($header !== ['question', 'answer'] && $header !== ['question', 'answer', 'explanation']) {
                return [[], 'The header must be question,answer or question,answer,explanation.'];
            }

            $rows = [];
            $line = 1;

            while (($row = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                $line++;

                if ($row === [null] || $row === ['']) {
                    continue;
                }

                if (count($row) !== count($header)) {
                    return [[], 'CSV record ' . $line . ' has an unexpected number of columns.'];
                }

                $data = [
                    'question'    => trim((string) $row[0]),
                    'answer'      => trim((string) $row[1]),
                    'explanation' => trim((string) ($row[2] ?? '')),
                ];

                $rowErrors = self::validateCard($data);
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
