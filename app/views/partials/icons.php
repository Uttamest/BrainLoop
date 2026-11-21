<?php
declare(strict_types=1);

function icon(string $name, int $size = 20): string
{
    $paths = [
        'arrow' => '<path d="M5 12h14m-5-5 5 5-5 5"/>',
        'arrow-left' => '<path d="M19 12H5m5-5-5 5 5 5"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'dashboard' => '<rect x="3" y="3" width="7" height="7" rx="2"/><rect x="14" y="3" width="7" height="7" rx="2"/><rect x="3" y="14" width="7" height="7" rx="2"/><rect x="14" y="14" width="7" height="7" rx="2"/>',
        'decks' => '<path d="m6 4 13 2-2 15-13-2z"/><path d="m7 3 1-1 13 2-1 14M8 9l6 1m-7 3 6 1"/>',
        'cards' => '<rect x="4" y="7" width="16" height="14" rx="3"/><path d="M7 3h10M8 12h8m-8 4h5"/>',
        'study' => '<path d="m12 3 2.4 5.8L21 10l-5 4.2 1.5 6.3L12 17l-5.5 3.5L8 14.2 3 10l6.6-1.2z"/>',
        'sparkles' => '<path d="m12 3 2.3 6.7L21 12l-6.7 2.3L12 21l-2.3-6.7L3 12l6.7-2.3zM20 2v4m-2-2h4"/>',
        'analytics' => '<path d="M4 20V10m8 10V4m8 16v-7M2 21h20"/>',
        'search' => '<circle cx="10.5" cy="10.5" r="6.5"/><path d="m16 16 5 5"/>',
        'settings' => '<path d="m9 3-.7 2.5-2.4 1L3.5 6l-2 3.5L3.3 11v2l-1.8 1.5 2 3.5 2.4-.5 2.4 1L9 21h4l.7-2.5 2.4-1 2.4.5 2-3.5-1.8-1.5v-2l1.8-1.5-2-3.5-2.4.5-2.4-1L13 3z"/><circle cx="11" cy="12" r="3"/>',
        'logout' => '<path d="M10 4H5v16h5m4-12 4 4-4 4m-5-4h13"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
        'flame' => '<path d="M13 2c2 5-2 6-2 9 2-1 3-3 3-3 4 3 6 6 4 10a7 7 0 0 1-12 0C2 12 8 9 8 5c1 0 2 3 2 3s3-2 3-6Z"/>',
        'loop' => '<path d="M4 8a8 8 0 0 1 13-3l3 3M20 3v5h-5M20 16a8 8 0 0 1-13 3l-3-3M4 21v-5h5"/>',
        'book' => '<path d="M12 6v15M3 3c4 0 7 1 9 3 2-2 5-3 9-3v15c-4 0-7 1-9 3-2-2-5-3-9-3z"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'shield' => '<path d="m12 2 8 3v6c0 6-8 11-8 11S4 17 4 11V5z"/><path d="m8 11 3 3 5-6"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21v-2a8 8 0 0 1 16 0v2"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="3"/><path d="m3 7 9 6 9-6"/>',
        'lock' => '<rect x="4" y="10" width="16" height="11" rx="3"/><path d="M8 10V7a4 4 0 0 1 8 0v3m-4 5v2"/>',
        'edit' => '<path d="m14 5 5 5M4 20l5-1L21 7l-5-5L4 14z"/>',
        'trash' => '<path d="M3 6h18M9 6V3h6v3M5 6l1 15h12l1-15M10 10v7m4-7v7"/>',
        'download' => '<path d="M12 3v12m-5-5 5 5 5-5M4 16v5h16v-5"/>',
        'upload' => '<path d="M12 16V4m-5 5 5-5 5 5M4 16v5h16v-5"/>',
        'info' => '<circle cx="12" cy="12" r="9"/><path d="M12 11v6m0-10v.1"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="3"/><path d="M7 3v4m10-4v4M3 11h18m-14 4h2m4 0h2"/>',
        'heart' => '<path d="M12 21S2 15 2 8a5 5 0 0 1 10-1A5 5 0 0 1 22 8c0 7-10 13-10 13Z"/>',
        'lightbulb' => '<path d="M9 18h6m-6 3h6M8 15a7 7 0 1 1 8 0l-1 3H9z"/>',
    ];
    $shape = $paths[$name] ?? $paths['cards'];
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $shape . '</svg>';
}

function brand_mark(int $size = 36): string
{
    return '<svg class="brand-mark" width="' . $size . '" height="' . $size . '" viewBox="0 0 40 40" fill="none" aria-hidden="true"><rect width="40" height="40" rx="12" fill="#6C5CE7"/><path d="M20 11c-3-5-11-2-9 4-5 2-4 10 2 10-1 6 7 7 7 2V11Zm0 0c3-5 11-2 9 4 5 2 4 10-2 10 1 6-7 7-7 2V11Z" stroke="white" stroke-width="2" stroke-linejoin="round"/><path d="M14 16c3 0 3 5 0 5m12-5c-3 0-3 5 0 5" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>';
}

function avatar_initial(string $name): string
{
    preg_match('/^./us', trim($name), $match);
    return strtoupper($match[0] ?? '?');
}
