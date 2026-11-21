<?php
declare(strict_types=1);
namespace App\Controllers;

final class DeckController
{
    public static function listing(): void
    {
        $user = require_user(); $query = self::query();
        $subject = is_string($_GET['subject'] ?? null) ? substr($_GET['subject'],0,100) : '';
        render('decks',['title'=>'My decks','decks'=>store()->decks((int) $user['id'],$query,$subject),'subjects'=>store()->subjects((int) $user['id']),'query'=>$query,'subject'=>$subject]);
    }
    public static function ownedDeck(int $id): array { return store()->deck($id,(int) require_user()['id']) ?? abort_request(404,'This deck is unavailable.'); }
    public static function ownedCard(int $id): array { return store()->card($id,(int) require_user()['id']) ?? abort_request(404,'This flashcard is unavailable.'); }
    public static function detail(int $id): void { $deck = self::ownedDeck($id); $query = self::query(); render('deck_detail',compact('deck','query')+['title'=>$deck['title'],'cards'=>store()->cards($id,(int) require_user()['id'],$query)]); }
    public static function deckForm(?int $id = null): void
    {
        $user = require_user(); $deck = $id ? self::ownedDeck($id) : null; $errors = []; $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['title','description','subject','color'] as $key) { $old[$key] = input_text($key); }
            if (!valid_text($old['title'],1,120)) { $errors['title'] = 'Give your deck a title of 1–120 characters.'; }
            if (!valid_text($old['description'],0,1500)) { $errors['description'] = 'Keep descriptions under 1,500 characters.'; }
            if (!valid_text($old['subject'],0,80)) { $errors['subject'] = 'Keep subjects under 80 characters.'; }
            if (!isset(config()['colors'][$old['color']])) { $errors['color'] = 'Choose one of the available colors.'; }
            if (!$errors) {
                if ($id) { store()->updateDeck($id,(int) $user['id'],$old); } else { $id = store()->createDeck((int) $user['id'],$old); }
                flash($deck ? 'Deck updated.' : 'A fresh space for new knowledge. Your deck is ready.'); redirect('/decks/' . $id);
            }
        }
        render('deck_form',compact('deck','old','errors')+['title'=>$deck?'Edit deck':'Create a deck','colors'=>config()['colors']]);
    }
    public static function deleteDeck(int $id): void
    {
        $deck = self::ownedDeck($id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { self::confirmed(); store()->deleteDeck($id,(int) require_user()['id']); flash('Deck and its cards and review records deleted.'); redirect('/decks'); }
        render('confirm',['title'=>'Delete deck','heading'=>'Delete “' . $deck['title'] . '”?','message'=>'This permanently removes this deck, all its flashcards, and their review history. Your analytics will be recalculated. This cannot be undone.','action'=>'/decks/' . $id . '/delete','cancel'=>'/decks/' . $id]);
    }
    public static function cardForm(int $id, bool $editing = false): void
    {
        $card = $editing ? self::ownedCard($id) : null;
        $deck = self::ownedDeck($card ? (int) $card['deck_id'] : $id); $errors = []; $old = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            foreach (['question','answer','explanation'] as $key) { $old[$key] = input_text($key); }
            $errors = self::cardErrors($old);
            if (!$errors) {
                if ($editing) { store()->updateCard($id,(int) require_user()['id'],$old); }
                else { store()->createCard((int) $deck['id'],(int) require_user()['id'],$old); }
                flash($editing?'Flashcard updated.':'Flashcard added. One more idea, ready to stick.'); redirect('/decks/' . $deck['id']);
            }
        }
        render('card_form',compact('card','deck','errors','old')+['title'=>$editing?'Edit flashcard':'Add a flashcard']);
    }
    private static function cardErrors(array $data): array
    {
        $errors = [];
        foreach (['question'=>2000,'answer'=>5000,'explanation'=>5000] as $field=>$max) {
            if (!valid_text($data[$field],$field==='explanation'?0:1,$max)) { $errors[$field] = ucfirst($field) . ' must be ' . ($field==='explanation'?'0':'1') . '–' . number_format($max) . ' valid UTF-8 characters.'; }
        }
        return $errors;
    }
    public static function deleteCard(int $id): void
    {
        $card = self::ownedCard($id);
        if ($_SERVER['REQUEST_METHOD'] === 'POST') { self::confirmed(); store()->deleteCard($id,(int) require_user()['id']); flash('Flashcard and its review records deleted.'); redirect('/decks/' . $card['deck_id']); }
        render('confirm',['title'=>'Delete flashcard','heading'=>'Delete this flashcard?','message'=>excerpt($card['question'],180) . ' — Its review history will also be removed. This cannot be undone.','action'=>'/cards/' . $id . '/delete','cancel'=>'/decks/' . $card['deck_id']]);
    }
    private static function confirmed(): void { if (input_text('confirm') !== 'yes') { abort_request(422,'Please confirm this action using the confirmation form.'); } }
    public static function query(): string { $q = $_GET['q'] ?? ''; return is_string($q) && valid_text(trim($q),0,200) ? trim($q) : ''; }
    public static function export(int $id): never
    {
        self::ownedDeck($id);
        header('Content-Type: text/csv; charset=UTF-8'); header('Content-Disposition: attachment; filename="brainloop-deck-' . $id . '.csv"');
        $out = fopen('php://output','wb'); fwrite($out,"\xEF\xBB\xBF"); fputcsv($out,['question','answer','explanation'],',','"','');
        foreach (store()->cards($id,(int) require_user()['id']) as $card) {
            $row = [];
            foreach (['question','answer','explanation'] as $field) { $value = $card[$field]; $row[] = preg_match('/^[\s\x{FEFF}]*[=+\-@\t\r]/u',$value) ? "'" . $value : $value; }
            fputcsv($out,$row,',','"','');
        }
        fclose($out); exit;
    }
    public static function import(int $id): void
    {
        $deck = self::ownedDeck($id); $errors = [];
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $file = $_FILES['csv'] ?? null;
            if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_string($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) { $errors['csv'] = 'Choose a valid CSV file to upload (maximum 1 MB).'; }
            elseif (filesize($file['tmp_name']) > 1024*1024 || filesize($file['tmp_name']) === 0) { $errors['csv'] = 'Use a nonempty CSV file of at most 1 MB.'; }
            else {
                $handle = fopen($file['tmp_name'],'rb'); $header = fgetcsv($handle,0,',','"','');
                if (is_array($header)) { $header[0] = preg_replace('/^\xEF\xBB\xBF/','',(string) $header[0]); $header = array_map(static fn($v)=>strtolower(trim((string) $v)),$header); }
                if ($header !== ['question','answer'] && $header !== ['question','answer','explanation']) { $errors['csv'] = 'The header must be question,answer or question,answer,explanation.'; }
                $rows = []; $line = 1;
                while (!$errors && ($row = fgetcsv($handle,0,',','"','')) !== false) {
                    $line++;
                    if ($row === [null] || $row === ['']) { continue; }
                    if (count($row) !== count($header)) { $errors['csv'] = 'CSV record ' . $line . ' has an unexpected number of columns.'; break; }
                    $data = ['question'=>trim((string) $row[0]),'answer'=>trim((string) $row[1]),'explanation'=>trim((string) ($row[2] ?? ''))];
                    $rowErrors = self::cardErrors($data);
                    if ($rowErrors) { $errors['csv'] = 'CSV record ' . $line . ': ' . reset($rowErrors); break; }
                    $rows[] = $data;
                    if (count($rows)>500) { $errors['csv'] = 'Import at most 500 flashcards per file.'; }
                }
                fclose($handle);
                if (!$rows && !$errors) { $errors['csv'] = 'This CSV contains no flashcards.'; }
                if (!$errors) { $count = store()->importCards($id,(int) require_user()['id'],$rows); flash($count . ' flashcards imported successfully.'); redirect('/decks/' . $id); }
            }
        }
        render('import',compact('deck','errors')+['title'=>'Import flashcards']);
    }
}
