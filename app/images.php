<?php

declare(strict_types=1);

/**
 * Validate an image URL supplied by the database.
 *
 * HTTPS images and existing local, root-relative image files are supported.
 * Browser-interpreted schemes, traversal, query strings and fragments are
 * rejected. A null return value means that no image element should be shown.
 */
function database_image_url(?string $value, ?callable $fileExists = null): ?string
{
    $url = trim($value ?? '');
    if ($url === '' || str_contains($url, "\0") || str_contains($url, '\\')) {
        return null;
    }

    if (preg_match('~\A[a-z][a-z0-9+.-]*:~i', $url) === 1) {
        if (filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);
        if (!is_array($parts)
            || strtolower((string) ($parts['scheme'] ?? '')) !== 'https'
            || ($parts['host'] ?? '') === ''
            || isset($parts['user'])
            || isset($parts['pass'])) {
            return null;
        }

        return $url;
    }

    if (str_starts_with($url, '//') || str_contains($url, '?') || str_contains($url, '#')) {
        return null;
    }

    $path = ltrim($url, '/');
    $decodedPath = $path;
    for ($iteration = 0; $iteration < 3; $iteration++) {
        $decoded = rawurldecode($decodedPath);
        if ($decoded === $decodedPath) {
            break;
        }
        $decodedPath = $decoded;
    }

    foreach (explode('/', $decodedPath) as $segment) {
        if ($segment === '' || $segment === '.' || $segment === '..') {
            return null;
        }
    }

    if (!preg_match('~\.(?:avif|gif|jpe?g|png|webp)\z~i', $decodedPath)) {
        return null;
    }

    $fileExists ??= static fn (string $file): bool => is_file(NEMA_ROOT . '/' . $file);
    if (!$fileExists($decodedPath)) {
        return null;
    }

    return '/' . $path;
}
