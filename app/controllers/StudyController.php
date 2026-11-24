<?php
declare(strict_types=1);
namespace App\Controllers;

use DomainException;

final class StudyController
{
    public static function chooser(): void { $id = (int) require_user()['id']; render('study_start',['title'=>'Time to focus','decks'=>store()->decks($id),'stats'=>store()->stats($id)]); }
    public static function start(): void
    {
        $user = require_user(); $mode = input_text('mode');
        if (!in_array($mode,['due','practice'],true)) { abort_request(422,'Choose due review or intentional practice.'); }
        $rawId = input_text('deck_id'); $deckId = $rawId === '' || $rawId === '0' ? null : valid_id($rawId);
        if ($deckId === 0) { abort_request(404,'This deck is unavailable.'); }
        if ($deckId) { (new DeckController())->requireOwnedDeck($deckId); }
        $ids = store()->studyCards((int) $user['id'],$deckId,$mode);
        if (!$ids) { flash($mode==='due'?'You’re all caught up. Come back when more cards are due, or choose practice.':'Add a few flashcards before starting a session.','info'); redirect($deckId?'/decks/' . $deckId:'/study'); }
        $_SESSION['study'] = ['user_id'=>(int) $user['id'],'deck_id'=>$deckId,'mode'=>$mode,'queue'=>$ids,'total'=>count($ids),'completed'=>0,'ratings'=>['again'=>0,'hard'=>0,'good'=>0,'easy'=>0],'revealed'=>false,'nonce'=>bin2hex(random_bytes(24)),'started_at'=>utc_now()];
        unset($_SESSION['study_result']); redirect('/study/session');
    }
    private static function &session(): array
    {
        $user = require_user();
        if (empty($_SESSION['study']) || (int) $_SESSION['study']['user_id'] !== (int) $user['id']) { flash('Choose a deck to start your next session.','info'); redirect('/study'); }
        return $_SESSION['study'];
    }
    public static function show(): void
    {
        $session = &self::session();
        while ($session['queue']) {
            $card = store()->card((int) $session['queue'][0],(int) $session['user_id']);
            if ($card && ($session['mode'] === 'practice' || $card['due_at'] <= utc_now())) { break; }
            array_shift($session['queue']); $session['total']--; $session['revealed'] = false; $session['nonce'] = bin2hex(random_bytes(24));
        }
        if (!$session['queue']) { self::finish(false); }
        $deck = $session['deck_id'] ? (new DeckController())->requireOwnedDeck((int) $session['deck_id']) : ['title'=>'All decks','id'=>null];
        $revealed = (bool) $session['revealed'];
        if (!$revealed) { unset($card['answer'],$card['explanation']); }
        render('study',['title'=>'Focus session','session'=>$session,'card'=>$card,'deck'=>$deck,'revealed'=>$revealed,'position'=>$session['completed']+1,'total'=>$session['total']]);
    }
    private static function validNonce(array $session): bool { $nonce = input_text('nonce'); return $nonce!=='' && hash_equals($session['nonce'],$nonce); }
    public static function reveal(): void
    {
        $session = &self::session();
        if (!self::validNonce($session)) { flash('That card has already moved on. Here is your current card.','info'); redirect('/study/session'); }
        $session['revealed'] = true; redirect('/study/session');
    }
    public static function rate(): void
    {
        $session = &self::session();
        if (!self::validNonce($session)) { flash('That review was already processed.','info'); redirect('/study/session'); }
        if (!$session['revealed']) { abort_request(409,'Reveal the answer before rating your recall.'); }
        $rating = input_text('rating');
        if (!in_array($rating,['again','hard','good','easy'],true)) { abort_request(422,'Choose a valid recall rating.'); }
        $cardId = (int) ($session['queue'][0] ?? 0);
        try { $saved = store()->review((int) $session['user_id'],$cardId,$rating,$session['mode']==='practice',$session['nonce']); }
        catch (DomainException $e) { flash($e->getMessage(),'info'); redirect('/study/session'); }
        if ($saved) { $session['completed']++; $session['ratings'][$rating]++; }
        array_shift($session['queue']); $session['revealed'] = false; $session['nonce'] = bin2hex(random_bytes(24));
        if (!$session['queue']) { self::finish(false); }
        redirect('/study/session');
    }
    public static function exitSession(): void { self::session(); self::finish(true); }
    private static function finish(bool $abandoned): never
    {
        $session = &self::session();
        $_SESSION['study_result'] = array_diff_key($session,array_flip(['queue','nonce','revealed','user_id'])) + ['finished_at'=>utc_now(),'abandoned'=>$abandoned];
        unset($_SESSION['study']); redirect('/study/results');
    }
    public static function results(): void
    {
        require_user();
        if (!isset($_SESSION['study_result'])) { redirect('/study'); }
        render('results',['title'=>'Session complete','result'=>$_SESSION['study_result']]);
    }
}
