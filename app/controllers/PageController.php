<?php
declare(strict_types=1);
namespace App\Controllers;

final class PageController
{
    public static function overview(bool $analytics = false): void
    {
        $id = (int) require_user()['id'];
        render($analytics?'analytics':'dashboard',['title'=>$analytics?'Your learning, in focus':'Dashboard','stats'=>store()->stats($id),'decks'=>store()->decks($id),'daily'=>store()->daily($id),'ratings'=>store()->ratings($id),'activity'=>store()->activity($id)]);
    }
    public static function search(): void
    {
        $id = (int) require_user()['id']; $query = DeckController::query();
        render('search',['title'=>'Find your next idea','query'=>$query,'deckResults'=>$query!==''?store()->decks($id,$query):[],'cardResults'=>store()->searchCards($id,$query)]);
    }
}
