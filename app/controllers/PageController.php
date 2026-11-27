<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Handles top-level authenticated user views including the dashboard, analytics, and global search.
 */
final class PageController
{
    /**
     * Render the primary dashboard or detailed learning analytics view.
     */
    public static function overview(bool $analytics = false): void
    {
        $userId = self::currentUserId();
        $db     = store();

        $view  = $analytics ? 'analytics' : 'dashboard';
        $title = $analytics ? 'Your learning, in focus' : 'Dashboard';

        render($view, [
            'title'    => $title,
            'stats'    => $db->stats($userId),
            'decks'    => $db->decks($userId),
            'daily'    => $db->daily($userId),
            'ratings'  => $db->ratings($userId),
            'activity' => $db->activity($userId),
        ]);
    }

    /**
     * Render the global search page across owned decks and flashcards.
     */
    public static function search(): void
    {
        $userId = self::currentUserId();
        $query  = DeckController::query();
        $hasQuery = $query !== '';

        $db = store();

        render('search', [
            'title'       => 'Find your next idea',
            'query'       => $query,
            'deckResults' => $hasQuery ? $db->decks($userId, $query) : [],
            'cardResults' => $hasQuery ? $db->searchCards($userId, $query) : [],
        ]);
    }

    private static function currentUserId(): int
    {
        $user = require_user();

        return (int) $user['id'];
    }
}
