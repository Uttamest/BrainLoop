<?php

declare(strict_types=1);

namespace App\Controllers;

/**
 * Handles top-level authenticated views: dashboard, learning analytics, and global search.
 */
final class PageController
{
    private const MAX_SEARCH_QUERY_LENGTH = 200;

    private const VIEW_DASHBOARD = 'dashboard';
    private const VIEW_ANALYTICS = 'analytics';
    private const VIEW_SEARCH    = 'search';

    public function __construct(
        private readonly ?object $store = null,
    ) {
    }

    /**
     * Backward-compatible entry point for dashboard and analytics routes.
     */
    public static function overview(bool $analytics = false): void
    {
        $controller = new self();

        if ($analytics) {
            $controller->analytics();
            return;
        }

        $controller->dashboard();
    }

    /**
     * Render the primary user dashboard.
     */
    public function dashboard(): void
    {
        $userId = $this->currentUserId();

        render(self::VIEW_DASHBOARD, $this->buildOverviewPayload(
            userId: $userId,
            title: 'Dashboard',
        ));
    }

    /**
     * Render the detailed learning analytics page.
     */
    public function analytics(): void
    {
        $userId = $this->currentUserId();

        render(self::VIEW_ANALYTICS, $this->buildOverviewPayload(
            userId: $userId,
            title: 'Your learning, in focus',
        ));
    }

    /**
     * Render global search results across owned decks and flashcards.
     */
    public function search(): void
    {
        $userId = $this->currentUserId();
        $query  = $this->extractSearchQuery();
        $db     = $this->db();

        $hasQuery = $query !== '';

        render(self::VIEW_SEARCH, [
            'title'       => 'Find your next idea',
            'query'       => $query,
            'deckResults' => $hasQuery ? $db->decks($userId, $query) : [],
            'cardResults' => $hasQuery ? $db->searchCards($userId, $query) : [],
        ]);
    }

    /**
     * Assemble shared overview metrics for dashboard and analytics views.
     *
     * @return array<string, mixed>
     */
    private function buildOverviewPayload(int $userId, string $title): array
    {
        $db = $this->db();

        return [
            'title'    => $title,
            'stats'    => $db->stats($userId),
            'decks'    => $db->decks($userId),
            'daily'    => $db->daily($userId),
            'ratings'  => $db->ratings($userId),
            'activity' => $db->activity($userId),
        ];
    }

    /**
     * Extract and validate the search query parameter without coupling to other controllers.
     */
    private function extractSearchQuery(): string
    {
        $raw = $_GET['q'] ?? '';
        if (!is_string($raw)) {
            return '';
        }

        $trimmed = trim($raw);

        return valid_text($trimmed, 0, self::MAX_SEARCH_QUERY_LENGTH) ? $trimmed : '';
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
}
