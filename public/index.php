<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use App\Controllers\AuthController;
use App\Controllers\DeckController;
use App\Controllers\PageController;
use App\Controllers\StudyController;
use App\Controllers\InstallController;

try {
    $method = $_SERVER['REQUEST_METHOD'];
    if (!in_array($method,['GET','POST','HEAD'],true)) { header('Allow: GET, POST, HEAD'); abort_request(405,'This request method is not supported.'); }
    if ($method === 'POST' && (!is_string($_POST['csrf_token'] ?? null) || !hash_equals(csrf_token(),$_POST['csrf_token']))) { abort_request(403,'Your form expired or could not be verified. Reload the page and try again.'); }
    $path = request_path();
    $decks = new DeckController();
    if (needs_installation()) {
        if (!App\Services\LocalInstaller::isLocalRequest()) { abort_request(403, 'First-time setup is available only from this computer.'); }
        if ($path !== '/setup') { redirect('/setup'); }
        InstallController::show();
        exit;
    }
    if ($path === '/setup') { abort_request(404, 'Setup is already complete or managed through your environment configuration.'); }
    App\Core\Database::migrate();
    if ($path !== '/' && str_ends_with($path,'/')) { redirect(rtrim($path,'/') . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '')); }
    $public = ['/' => ['home','Learn smarter. Remember longer.'],'/features'=>['features','Designed for the way you learn'],'/how-it-works'=>['how','A small loop. A lasting habit.'],'/about'=>['about','About BrainLoop'],'/privacy'=>['privacy','Privacy policy'],'/terms'=>['terms','Terms of use']];
    if (isset($public[$path])) { if ($method==='POST') { abort_request(405,'This page accepts read requests only.'); } render($public[$path][0],['title'=>$public[$path][1]]); }
    elseif ($path==='/register') { AuthController::register(); }
    elseif ($path==='/login') { AuthController::login(); }
    elseif ($path==='/logout') {
        require_user();
        if ($method==='POST') { AuthController::logout(); }
        render('confirm',['title'=>'Log out','heading'=>'See you next study session.','message'=>'Your decks and learning progress will be here when you return.','action'=>'/logout','cancel'=>'/dashboard']);
    }
    elseif ($path==='/settings') { AuthController::settings(); }
    elseif ($path==='/dashboard') { self_get($method); PageController::overview(); }
    elseif ($path==='/analytics') { self_get($method); PageController::overview(true); }
    elseif ($path==='/search') { self_get($method); PageController::search(); }
    elseif ($path==='/decks') { self_get($method); $decks->listing(); }
    elseif ($path==='/decks/create') { $decks->deckForm(); }
    elseif (preg_match('~^/decks/([1-9][0-9]{0,9})$~D',$path,$m)) { self_get($method); $decks->detail((int) $m[1]); }
    elseif (preg_match('~^/decks/([1-9][0-9]{0,9})/(edit|delete|export|import)$~D',$path,$m)) {
        match ($m[2]) { 'edit'=>$decks->deckForm((int) $m[1]), 'delete'=>$decks->deleteDeck((int) $m[1]), 'export'=>export_deck($decks,$method,(int) $m[1]), 'import'=>$decks->import((int) $m[1]) };
    }
    elseif (preg_match('~^/decks/([1-9][0-9]{0,9})/cards/create$~D',$path,$m)) { $decks->cardForm((int) $m[1]); }
    elseif (preg_match('~^/cards/([1-9][0-9]{0,9})/(edit|delete)$~D',$path,$m)) { $m[2]==='edit' ? $decks->cardForm((int) $m[1],true) : $decks->deleteCard((int) $m[1]); }
    elseif ($path==='/study') { self_get($method); StudyController::chooser(); }
    elseif ($path==='/study/session') { self_get($method); StudyController::show(); }
    elseif ($path==='/study/results') { self_get($method); StudyController::results(); }
    elseif (in_array($path,['/study/start','/study/reveal','/study/rate','/study/exit'],true)) {
        if ($method!=='POST') { header('Allow: POST'); abort_request(405,'Use the study controls to perform this action.'); }
        match ($path) { '/study/start'=>StudyController::start(),'/study/reveal'=>StudyController::reveal(),'/study/rate'=>StudyController::rate(),'/study/exit'=>StudyController::exitSession() };
    }
    else { abort_request(); }
} catch (Throwable $error) {
    error_log('BrainLoop: ' . $error);
    if (ob_get_level()) { ob_end_clean(); }
    render('error',['title'=>'Something needs attention','status'=>500,'user'=>null,'message'=>'BrainLoop could not complete this request. Check that MySQL is running in XAMPP, then try again. Connection settings are stored in app/config.local.php.'],500);
}

function self_get(string $method): void { if ($method==='POST') { header('Allow: GET, HEAD'); abort_request(405,'This page accepts read requests only.'); } }
function export_deck(DeckController $decks, string $method, int $id): never { self_get($method); $decks->export($id); }
