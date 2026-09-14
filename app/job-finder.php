<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const JOB_FINDER_MAX_FILE_SIZE = 5 * 1024 * 1024;

function job_finder_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_trans_sid', '0');
    session_name('NEMA_JOB_FINDER');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();
}

function job_finder_json(int $status, array $payload): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function job_finder_require_request(string $contentType = ''): void
{
    security_headers();
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        header('Allow: POST');
        job_finder_json(405, ['success' => false, 'error' => 'Methode nicht erlaubt.']);
    }
    if ($contentType !== '' && !str_starts_with(strtolower($_SERVER['CONTENT_TYPE'] ?? ''), $contentType)) {
        job_finder_json(415, ['success' => false, 'error' => 'Ungültiges Anfrageformat.']);
    }
    job_finder_session();
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!is_string($token) || !isset($_SESSION['job_finder_csrf']) || !hash_equals($_SESSION['job_finder_csrf'], $token)) {
        job_finder_json(403, ['success' => false, 'error' => 'Die Anfrage konnte nicht verifiziert werden.']);
    }
}

function job_finder_rate_limit(string $action, int $limit, int $window): void
{
    $directory = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . '/nema-job-finder-rate';
    if (!is_dir($directory) && !@mkdir($directory, 0700, true) && !is_dir($directory)) {
        error_log('Job-Finder rate limiter unavailable.');
        job_finder_json(503, ['success' => false, 'error' => 'Der Dienst ist momentan nicht verfügbar.']);
    }
    $identity = ($_SERVER['REMOTE_ADDR'] ?? 'unknown') . '|' . session_id() . '|' . $action;
    $path = $directory . '/' . hash('sha256', $identity) . '.json';
    $handle = @fopen($path, 'c+');
    if ($handle === false || !flock($handle, LOCK_EX)) {
        if (is_resource($handle)) fclose($handle);
        job_finder_json(503, ['success' => false, 'error' => 'Der Dienst ist momentan nicht verfügbar.']);
    }
    $data = json_decode(stream_get_contents($handle) ?: '[]', true);
    $now = time();
    $hits = array_values(array_filter(is_array($data) ? $data : [], static fn($hit): bool => is_int($hit) && $hit > $now - $window));
    if (count($hits) >= $limit) {
        flock($handle, LOCK_UN); fclose($handle);
        job_finder_json(429, ['success' => false, 'error' => 'Zu viele Anfragen. Bitte warte einen Moment und versuche es erneut.']);
    }
    $hits[] = $now;
    ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($hits)); fflush($handle);
    flock($handle, LOCK_UN); fclose($handle);
}

function job_finder_config(string $name): string
{
    $value = getenv($name);
    if (!is_string($value) || trim($value) === '') {
        static $local;
        if ($local === null) {
            $path = NEMA_ROOT . '/../job-finder.local.php';
            $local = is_file($path) ? require $path : [];
            if (!is_array($local)) {
                throw new RuntimeException('Invalid private Job-Finder configuration.');
            }
        }
        $value = $local[$name] ?? null;
    }
    if (!is_string($value) || trim($value) === '') {
        throw new RuntimeException('Job-Finder configuration missing: ' . $name);
    }
    return trim($value);
}

function job_finder_call(string $urlName, array $options, int $timeout = 120): array
{
    $url = job_finder_config($urlName);
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('Invalid Job-Finder endpoint configuration.');
    }
    $secret = job_finder_config('N8N_JOB_FINDER_SECRET');
    $curl = curl_init($url);
    curl_setopt_array($curl, $options + [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'X-Job-Finder-Secret: ' . $secret],
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
    ]);
    $body = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $error = curl_error($curl);
    $errno = curl_errno($curl);
    curl_close($curl);
    if ($errno === CURLE_OPERATION_TIMEDOUT) {
        throw new JobFinderTimeoutException('Upstream timeout.');
    }
    if (!is_string($body) || $error !== '' || $status < 200 || $status >= 300 || strlen($body) > 2 * 1024 * 1024) {
        throw new RuntimeException('Job-Finder upstream request failed with status ' . $status . '.');
    }
    $decoded = json_decode($body, true, 64, JSON_THROW_ON_ERROR);
    if (!is_array($decoded)) throw new RuntimeException('Invalid Job-Finder upstream response.');
    return $decoded;
}

final class JobFinderTimeoutException extends RuntimeException {}

function job_finder_string_array(mixed $value, int $maxItems, int $maxLength): ?array
{
    if (!is_array($value) || count($value) > $maxItems || ($value !== [] && !array_is_list($value))) return null;
    $result = [];
    foreach ($value as $item) {
        $clean = job_finder_text($item, $maxLength, true);
        if ($clean === null || in_array($clean, $result, true)) return null;
        $result[] = $clean;
    }
    return $result;
}

function job_finder_text(mixed $value, int $max, bool $required = false): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) return null;
    return $value;
}
