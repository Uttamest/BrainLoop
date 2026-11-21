<?php
declare(strict_types=1);
namespace App\Models;

use App\Services\Scheduler;
use DateInterval;
use DateTimeImmutable;
use DomainException;
use PDO;
use Throwable;

final class Store
{
    public function __construct(private PDO $pdo) {}

    private function all(string $sql, array $params = []): array { $s = $this->pdo->prepare($sql); $s->execute($params); return $s->fetchAll(); }
    private function one(string $sql, array $params = []): ?array { return $this->all($sql, $params)[0] ?? null; }
    private function execute(string $sql, array $params = []): void { $s = $this->pdo->prepare($sql); $s->execute($params); }

    public function user(int $id): ?array { return $this->one('SELECT * FROM users WHERE id=?', [$id]); }
    public function userByEmail(string $email): ?array { return $this->one('SELECT * FROM users WHERE LOWER(email)=LOWER(?)', [$email]); }
    public function createUser(string $name, string $email, string $password): int {
        $this->execute('INSERT INTO users(full_name,email,password_hash,created_at,updated_at) VALUES(?,?,?,?,?)', [$name,$email,password_hash($password,PASSWORD_DEFAULT),utc_now(),utc_now()]);
        return (int) $this->pdo->lastInsertId();
    }
    public function updateProfile(int $id, string $name, string $email): void { $this->execute('UPDATE users SET full_name=?,email=?,updated_at=? WHERE id=?',[$name,$email,utc_now(),$id]); }
    public function updatePassword(int $id, string $password): void { $this->execute('UPDATE users SET password_hash=?,updated_at=? WHERE id=?',[password_hash($password,PASSWORD_DEFAULT),utc_now(),$id]); }

    private function loginKeys(string $email, string $ip): array { return [hash('sha256','email:' . strtolower($email)),hash('sha256','ip:' . $ip)]; }
    public function throttled(string $email, string $ip): bool {
        $this->execute('DELETE FROM login_attempts WHERE attempted_at<?',[time()-900]);
        foreach ($this->loginKeys($email,$ip) as $key) {
            $count = (int) ($this->one('SELECT COUNT(*) AS n FROM login_attempts WHERE identifier=? AND attempted_at>=?',[$key,time()-900])['n'] ?? 0);
            if ($count >= 10) { return true; }
        }
        return false;
    }
    public function loginFailure(string $email, string $ip): void { foreach ($this->loginKeys($email,$ip) as $key) { $this->execute('INSERT INTO login_attempts(identifier,attempted_at) VALUES(?,?)',[$key,time()]); } }
    public function clearLoginFailures(string $email): void { $this->execute('DELETE FROM login_attempts WHERE identifier=?',[hash('sha256','email:' . strtolower($email))]); }

    public function decks(int $userId, string $query = '', string $subject = ''): array {
        $sql = "SELECT d.*, COUNT(f.id) card_count, COALESCE(SUM(CASE WHEN p.due_at<=? THEN 1 ELSE 0 END),0) due_count,
         COALESCE(SUM(CASE WHEN p.review_state='review' AND p.interval_days>=21 THEN 1 ELSE 0 END),0) mastered_count
         FROM decks d LEFT JOIN flashcards f ON f.deck_id=d.id LEFT JOIN card_progress p ON p.flashcard_id=f.id AND p.user_id=d.user_id WHERE d.user_id=?";
        $params = [utc_now(),$userId];
        if ($query !== '') { $sql .= " AND (d.title LIKE ? ESCAPE '!' OR d.subject LIKE ? ESCAPE '!')"; $params[] = like_term($query); $params[] = like_term($query); }
        if ($subject !== '') { $sql .= ' AND d.subject=?'; $params[] = $subject; }
        $sql .= ' GROUP BY d.id,d.user_id,d.title,d.description,d.subject,d.color,d.created_at,d.updated_at ORDER BY d.updated_at DESC,d.id DESC';
        return array_map(static function ($d) { $d['progress'] = (int) $d['card_count'] ? (int) round(100 * (int) $d['mastered_count'] / (int) $d['card_count']) : 0; return $d; },$this->all($sql,$params));
    }
    public function subjects(int $userId): array { return array_column($this->all("SELECT DISTINCT subject FROM decks WHERE user_id=? AND subject<>'' ORDER BY subject",[$userId]),'subject'); }
    public function deck(int $id, int $userId): ?array {
        $d = $this->one("SELECT d.*,COUNT(f.id) card_count,COALESCE(SUM(CASE WHEN p.due_at<=? THEN 1 ELSE 0 END),0) due_count,COALESCE(SUM(CASE WHEN p.review_state='review' AND p.interval_days>=21 THEN 1 ELSE 0 END),0) mastered_count FROM decks d LEFT JOIN flashcards f ON f.deck_id=d.id LEFT JOIN card_progress p ON p.flashcard_id=f.id AND p.user_id=d.user_id WHERE d.id=? AND d.user_id=? GROUP BY d.id,d.user_id,d.title,d.description,d.subject,d.color,d.created_at,d.updated_at",[utc_now(),$id,$userId]);
        if ($d) { $d['progress'] = $d['card_count'] ? (int) round(100*$d['mastered_count']/$d['card_count']) : 0; }
        return $d;
    }
    public function createDeck(int $userId, array $data): int {
        $this->execute('INSERT INTO decks(user_id,title,description,subject,color,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',[$userId,$data['title'],$data['description'],$data['subject'],$data['color'],utc_now(),utc_now()]);
        return (int) $this->pdo->lastInsertId();
    }
    public function updateDeck(int $id, int $userId, array $data): void { $this->execute('UPDATE decks SET title=?,description=?,subject=?,color=?,updated_at=? WHERE id=? AND user_id=?',[$data['title'],$data['description'],$data['subject'],$data['color'],utc_now(),$id,$userId]); }
    public function deleteDeck(int $id, int $userId): void { $this->execute('DELETE FROM decks WHERE id=? AND user_id=?',[$id,$userId]); }
    public function cards(int $deckId, int $userId, string $query = ''): array {
        $sql = 'SELECT f.*,p.review_state,p.due_at,p.total_reviews,p.interval_days FROM flashcards f JOIN decks d ON d.id=f.deck_id JOIN card_progress p ON p.flashcard_id=f.id AND p.user_id=d.user_id WHERE f.deck_id=? AND d.user_id=?';
        $params = [$deckId,$userId];
        if ($query !== '') { $sql .= " AND (f.question LIKE ? ESCAPE '!' OR f.answer LIKE ? ESCAPE '!')"; $params[] = like_term($query); $params[] = like_term($query); }
        return $this->all($sql . ' ORDER BY f.id DESC',$params);
    }
    public function card(int $id, int $userId): ?array { return $this->one('SELECT f.*,d.title deck_title,p.review_state,p.due_at,p.interval_days,p.ease_factor,p.repetition_count,p.total_reviews,p.last_rating,p.last_reviewed_at FROM flashcards f JOIN decks d ON d.id=f.deck_id JOIN card_progress p ON p.flashcard_id=f.id AND p.user_id=d.user_id WHERE f.id=? AND d.user_id=?',[$id,$userId]); }
    public function createCard(int $deckId, int $userId, array $data): int {
        if (!$this->deck($deckId,$userId)) { throw new DomainException('Deck unavailable.'); }
        $ownTransaction = !$this->pdo->inTransaction();
        if ($ownTransaction) { $this->pdo->beginTransaction(); }
        try {
            $this->execute('INSERT INTO flashcards(deck_id,question,answer,explanation,created_at,updated_at) VALUES(?,?,?,?,?,?)',[$deckId,$data['question'],$data['answer'],$data['explanation'],utc_now(),utc_now()]);
            $id = (int) $this->pdo->lastInsertId();
            $this->execute('INSERT INTO card_progress(user_id,flashcard_id,due_at) VALUES(?,?,?)',[$userId,$id,utc_now()]);
            $this->execute('UPDATE decks SET updated_at=? WHERE id=? AND user_id=?',[utc_now(),$deckId,$userId]);
            if ($ownTransaction) { $this->pdo->commit(); }
            return $id;
        } catch (Throwable $e) { if ($ownTransaction && $this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
    public function updateCard(int $id, int $userId, array $data): void {
        $card = $this->card($id,$userId);
        if (!$card) { return; }
        $this->pdo->beginTransaction();
        try {
            $this->execute('UPDATE flashcards SET question=?,answer=?,explanation=?,updated_at=? WHERE id=? AND deck_id IN(SELECT id FROM decks WHERE user_id=?)',[$data['question'],$data['answer'],$data['explanation'],utc_now(),$id,$userId]);
            $this->execute('UPDATE decks SET updated_at=? WHERE id=? AND user_id=?',[utc_now(),$card['deck_id'],$userId]);
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
    public function deleteCard(int $id, int $userId): void {
        $card = $this->card($id,$userId);
        if (!$card) { return; }
        $this->pdo->beginTransaction();
        try {
            $this->execute('DELETE FROM flashcards WHERE id=? AND deck_id IN(SELECT id FROM decks WHERE user_id=?)',[$id,$userId]);
            $this->execute('UPDATE decks SET updated_at=? WHERE id=? AND user_id=?',[utc_now(),$card['deck_id'],$userId]);
            $this->pdo->commit();
        } catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }
    public function importCards(int $deckId, int $userId, array $rows): int {
        $this->pdo->beginTransaction();
        try { foreach ($rows as $row) { $this->createCard($deckId,$userId,$row); } $this->pdo->commit(); return count($rows); }
        catch (Throwable $e) { $this->pdo->rollBack(); throw $e; }
    }

    public function studyCards(int $userId, ?int $deckId, string $mode): array {
        $sql = 'SELECT f.id FROM flashcards f JOIN decks d ON d.id=f.deck_id JOIN card_progress p ON p.flashcard_id=f.id AND p.user_id=d.user_id WHERE d.user_id=?'; $params = [$userId];
        if ($deckId) { $sql .= ' AND d.id=?'; $params[] = $deckId; }
        if ($mode === 'due') { $sql .= ' AND p.due_at<=?'; $params[] = utc_now(); }
        return array_map('intval', array_column($this->all($sql . ' ORDER BY p.due_at,f.id LIMIT 200',$params),'id'));
    }
    public function review(int $userId, int $cardId, string $rating, bool $practice, string $submissionKey): bool {
        $this->pdo->beginTransaction();
        try {
            if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'mysql') {
                // Lock before the first consistent read so another session cannot schedule this
                // card from stale progress, including under InnoDB's REPEATABLE READ isolation.
                $this->one('SELECT flashcard_id FROM card_progress WHERE flashcard_id=? AND user_id=? FOR UPDATE',[$cardId,$userId]);
            }
            if ($this->one('SELECT id FROM review_history WHERE submission_key=?',[$submissionKey])) { $this->pdo->commit(); return false; }
            $card = $this->card($cardId,$userId);
            if (!$card) { throw new DomainException('This card is no longer available.'); }
            if (!$practice && $card['due_at'] > utc_now()) { throw new DomainException('This card is already scheduled for later. Start a new session.'); }
            $next = $practice ? $card : (new Scheduler())->schedule($card,$rating);
            if (!$practice) {
                $this->execute('UPDATE card_progress SET review_state=?,due_at=?,interval_days=?,ease_factor=?,repetition_count=?,total_reviews=?,last_rating=?,last_reviewed_at=? WHERE flashcard_id=? AND user_id=?',[$next['review_state'],$next['due_at'],$next['interval_days'],$next['ease_factor'],$next['repetition_count'],$next['total_reviews'],$next['last_rating'],$next['last_reviewed_at'],$cardId,$userId]);
            } elseif (!in_array($rating,['again','hard','good','easy'],true)) { throw new DomainException('Choose a valid review rating.'); }
            $this->execute('INSERT INTO review_history(user_id,flashcard_id,rating,interval_before,interval_after,reviewed_at,is_practice,submission_key) VALUES(?,?,?,?,?,?,?,?)',[$userId,$cardId,$rating,$card['interval_days'],$next['interval_days'],utc_now(),(int) $practice,$submissionKey]);
            $this->pdo->commit(); return true;
        } catch (Throwable $e) { if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); } throw $e; }
    }
    public function stats(int $userId): array {
        $decks = $this->decks($userId);
        $summary = $this->one('SELECT COUNT(*) reviews,COUNT(DISTINCT CASE WHEN substr(reviewed_at,1,10)=? THEN flashcard_id END) today_cards FROM review_history WHERE user_id=?',[gmdate('Y-m-d'),$userId]);
        $todayScheduled = (int) ($this->one('SELECT COUNT(*) n FROM card_progress WHERE user_id=? AND substr(last_reviewed_at,1,10)=? AND due_at>?',[$userId,gmdate('Y-m-d'),utc_now()])['n'] ?? 0);
        $days = array_column($this->all('SELECT DISTINCT substr(reviewed_at,1,10) review_date FROM review_history WHERE user_id=? ORDER BY review_date',[$userId]),'review_date');
        $longest = 0; $run = 0; $previous = null;
        foreach ($days as $day) { $run = $previous !== null && (new DateTimeImmutable($previous))->modify('+1 day')->format('Y-m-d') === $day ? $run+1 : 1; $longest = max($longest,$run); $previous = $day; }
        $current = 0; $cursor = new DateTimeImmutable('today'); $set = array_fill_keys($days,true);
        if (!isset($set[$cursor->format('Y-m-d')])) { $cursor = $cursor->modify('-1 day'); }
        while (isset($set[$cursor->format('Y-m-d')])) { $current++; $cursor = $cursor->modify('-1 day'); }
        return ['decks'=>count($decks),'cards'=>array_sum(array_column($decks,'card_count')),'due'=>array_sum(array_column($decks,'due_count')),'reviews'=>(int) $summary['reviews'],'today_cards'=>(int) $summary['today_cards'],'today_scheduled'=>$todayScheduled,'current_streak'=>$current,'longest_streak'=>$longest];
    }
    public function daily(int $userId): array {
        $start = (new DateTimeImmutable('today'))->modify('-13 days');
        $rows = $this->all('SELECT substr(reviewed_at,1,10) review_date,COUNT(*) n FROM review_history WHERE user_id=? AND reviewed_at>=? GROUP BY substr(reviewed_at,1,10)',[$userId,$start->format('Y-m-d H:i:s')]);
        $map = array_column($rows,'n','review_date'); $daily = [];
        for ($i=0;$i<14;$i++) { $d = $start->add(new DateInterval('P' . $i . 'D')); $daily[] = ['date'=>$d->format('Y-m-d'),'label'=>$d->format('D'),'count'=>(int) ($map[$d->format('Y-m-d')] ?? 0)]; }
        return $daily;
    }
    public function ratings(int $userId): array { return array_replace(['again'=>0,'hard'=>0,'good'=>0,'easy'=>0],array_map('intval',array_column($this->all('SELECT rating,COUNT(*) n FROM review_history WHERE user_id=? GROUP BY rating',[$userId]),'n','rating'))); }
    public function activity(int $userId): array { return $this->all('SELECT h.rating,h.reviewed_at,h.is_practice,f.question,d.title deck_title FROM review_history h JOIN flashcards f ON f.id=h.flashcard_id JOIN decks d ON d.id=f.deck_id WHERE h.user_id=? AND d.user_id=? ORDER BY h.reviewed_at DESC,h.id DESC LIMIT 12',[$userId,$userId]); }
    public function searchCards(int $userId, string $query): array { return $query === '' ? [] : $this->all("SELECT f.*,d.title deck_title FROM flashcards f JOIN decks d ON d.id=f.deck_id WHERE d.user_id=? AND (f.question LIKE ? ESCAPE '!' OR f.answer LIKE ? ESCAPE '!') ORDER BY d.title,f.id LIMIT 100",[$userId,like_term($query),like_term($query)]); }
}
