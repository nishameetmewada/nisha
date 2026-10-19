<?php
/** Give local CSS and JavaScript a new URL whenever their contents change. */
function nisha_asset_url(string $url): string
{
    $fragmentAt = strpos($url, '#');
    $fragment = $fragmentAt === false ? '' : substr($url, $fragmentAt);
    $withoutFragment = $fragmentAt === false ? $url : substr($url, 0, $fragmentAt);
    $queryAt = strpos($withoutFragment, '?');
    $path = $queryAt === false ? $withoutFragment : substr($withoutFragment, 0, $queryAt);
    $query = $queryAt === false ? '' : substr($withoutFragment, $queryAt + 1);

    // Only resolve the application's own assets, never URLs or arbitrary files.
    if (!preg_match('~\Aassets/[A-Za-z0-9_./-]+\.(?:css|js)\z~D', $path)
        || in_array('..', explode('/', $path), true)) {
        return $url;
    }
    $file = dirname(__DIR__) . '/' . $path;
    if (!is_file($file) || !is_readable($file)) {
        return $url;
    }
    $hash = @hash_file('sha256', $file);
    if ($hash === false) {
        return $url;
    }

    // Keep other parameters intact and replace an existing version if present.
    $parameters = array_filter(explode('&', $query), static function (string $parameter): bool {
        return $parameter !== '' && rawurldecode(explode('=', $parameter, 2)[0]) !== 'v';
    });
    $parameters[] = 'v=' . substr($hash, 0, 16);
    return $path . '?' . implode('&', $parameters) . $fragment;
}

/** Small, dependency-free icon set used throughout the interface. */
function icon(string $name, string $class = '', int $size = 20): string
{
    $paths = [
        'arrow' => '<path d="M4 12h16M14 6l6 6-6 6"/>',
        'arrow-up' => '<path d="M6 18 18 6M6 6h12v12"/>',
        'chevron' => '<path d="m9 5 7 7-7 7"/>',
        'check' => '<path d="m5 12 4 4L19 6"/>',
        'play' => '<path d="m9 5 11 7-11 7z"/>',
        'headphones' => '<path d="M4 14v-3a8 8 0 0 1 16 0v3M4 12H3v7h4v-7zm16 0h1v7h-4v-7z"/>',
        'book' => '<path d="M12 5v15M3 4c4-1 6 0 9 2 3-2 5-3 9-2v15c-4-1-6 0-9 2-3-2-5-3-9-2z"/>',
        'pen' => '<path d="m14 5 5 5M4 20l5-1L21 7a2 2 0 0 0-5-5L4 14z"/>',
        'mic' => '<rect x="9" y="2" width="6" height="13" rx="3"/><path d="M5 10v2a7 7 0 0 0 14 0v-2M12 19v3m-4 0h8"/>',
        'spark' => '<path d="m12 2 2.8 7.2L22 12l-7.2 2.8L12 22l-2.8-7.2L2 12l7.2-2.8z"/>',
        'globe' => '<circle cx="12" cy="12" r="9"/><ellipse cx="12" cy="12" rx="4" ry="9"/><path d="M3 12h18"/>',
        'target' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1"/>',
        'chart' => '<path d="M4 3v17h17M8 15l4-5 4 2 5-7"/>',
        'clock' => '<circle cx="12" cy="12" r="9"/><path d="M12 6v6l4 2"/>',
        'heart' => '<path d="M20 4a5 5 0 0 0-8 2 5 5 0 0 0-8-2c-5 5 2 11 8 16 6-5 13-11 8-16z"/>',
        'menu' => '<path d="M4 6h16M4 12h16M4 18h16"/>',
        'close' => '<path d="m6 6 12 12M6 18 18 6"/>',
        'shield' => '<path d="m12 2 8 4v6c0 5-8 10-8 10S4 17 4 12V6zM8 11l3 3 5-5"/>',
    ];
    return '<svg width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.65" stroke-linecap="round" stroke-linejoin="round" class="'.htmlspecialchars($class, ENT_QUOTES, 'UTF-8').'" aria-hidden="true">'.($paths[$name] ?? $paths['spark']).'</svg>';
}
