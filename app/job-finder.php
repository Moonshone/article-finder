<?php

declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

const JOB_FINDER_MAX_FILE_SIZE = 5 * 1024 * 1024;
const JOB_FINDER_MAX_SEARCH_BODY_SIZE = 16384;

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

function job_finder_csrf_token(): string
{
    if (!isset($_SESSION['job_finder_csrf']) || !is_string($_SESSION['job_finder_csrf'])
        || !preg_match('/\A[a-f0-9]{64}\z/', $_SESSION['job_finder_csrf'])) {
        $_SESSION['job_finder_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['job_finder_csrf'];
}

function job_finder_csrf_failure(mixed $submittedToken): ?string
{
    if (!is_string($submittedToken) || $submittedToken === '') {
        return 'missing_header';
    }
    if (!isset($_SESSION['job_finder_csrf']) || !is_string($_SESSION['job_finder_csrf'])) {
        return 'missing_session_token';
    }
    if (!hash_equals($_SESSION['job_finder_csrf'], $submittedToken)) {
        return 'token_mismatch';
    }
    return null;
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
    $failure = job_finder_csrf_failure($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null);
    if ($failure !== null) {
        error_log('Job-Finder CSRF verification failed: ' . $failure);
        $expired = $failure !== 'missing_header';
        job_finder_json(403, [
            'success' => false,
            'error' => $expired
                ? 'Deine Sitzung ist nicht mehr aktuell. Bitte lade diese Seite neu und versuche es erneut.'
                : 'Die Anfrage konnte nicht verifiziert werden.',
            'reason' => $expired ? 'session_expired' : 'csrf_failed',
        ]);
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

function job_finder_configuration(): array
{
    static $configuration;
    if (is_array($configuration)) {
        return $configuration;
    }

    $configPath = dirname(__DIR__, 2) . '/config/job-finder.local.php';
    if (!is_file($configPath) || !is_readable($configPath)) {
        job_finder_json(500, ['success' => false, 'error' => 'Serverkonfiguration nicht verfügbar.']);
    }

    try {
        $loaded = @include $configPath;
    } catch (Throwable) {
        $loaded = null;
    }
    if (!is_array($loaded)) {
        job_finder_json(500, ['success' => false, 'error' => 'Serverkonfiguration nicht verfügbar.']);
    }

    return $configuration = $loaded;
}

function job_finder_require_config(array $requiredKeys): array
{
    $configuration = job_finder_configuration();
    foreach ($requiredKeys as $key) {
        if (!is_string($key) || !isset($configuration[$key]) || !is_string($configuration[$key])
            || trim($configuration[$key]) === '') {
            job_finder_json(500, ['success' => false, 'error' => 'Serverkonfiguration nicht verfügbar.']);
        }
        $configuration[$key] = trim($configuration[$key]);
    }
    return $configuration;
}

function job_finder_call(string $url, string $secret, array $options, int $timeout = 120): array
{
    $parts = parse_url($url);
    if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
        throw new RuntimeException('Invalid Job-Finder endpoint configuration.');
    }
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

function job_finder_occupations(mixed $value): ?array
{
    if (!is_array($value) || count($value) > 8 || ($value !== [] && !array_is_list($value))) return null;
    $result = [];
    $seen = [];
    foreach ($value as $item) {
        $clean = job_finder_text($item, 80);
        if ($clean === null) return null;
        if ($clean === '') continue;
        $key = mb_strtolower($clean, 'UTF-8');
        if (isset($seen[$key])) continue;
        $seen[$key] = true;
        $result[] = $clean;
    }
    return $result;
}

function job_finder_profile_id(mixed $value): ?string
{
    if (!is_string($value) || strlen($value) > 128
        || !preg_match('/\A[A-Za-z0-9_-]{16,128}\z/', $value)) {
        return null;
    }
    return $value;
}

/** Validate the browser payload and create the only three fields accepted by n8n. */
function job_finder_search_payload(mixed $input, string $sessionProfileId): array
{
    $allowedKeys = ['profile_id', 'jobs', 'ort', 'radius', 'remote', 'beschaeftigungsart', 'webseiten', 'ausschluesse'];
    if (!is_array($input) || array_diff(array_keys($input), $allowedKeys)) {
        throw new InvalidArgumentException('keys');
    }

    $profileId = job_finder_profile_id($input['profile_id'] ?? null);
    $storedProfileId = job_finder_profile_id($sessionProfileId);
    $jobs = job_finder_occupations($input['jobs'] ?? null);
    $ort = job_finder_text($input['ort'] ?? null, 100, true);
    $radius = $input['radius'] ?? null;
    $remote = $input['remote'] ?? null;
    $employment = $input['beschaeftigungsart'] ?? null;
    $sources = job_finder_string_array($input['webseiten'] ?? null, 5, 80);
    $exclusions = job_finder_text($input['ausschluesse'] ?? null, 500);

    $allowedRadii = [10, 25, 50, 100, 'egal'];
    $remoteLabels = ['egal' => 'Egal', 'remote' => 'Remote', 'hybrid' => 'Hybrid', 'vor_ort' => 'Vor Ort'];
    $employmentLabels = ['egal' => 'Egal', 'Vollzeit' => 'Vollzeit', 'Teilzeit' => 'Teilzeit', 'Freelancer' => 'Freelancer'];
    $sourceLabels = [
        'unternehmensseiten' => 'Unternehmensseiten',
        'stepstone.de' => 'StepStone',
        'arbeitsagentur.de' => 'Bundesagentur für Arbeit',
        'indeed.com' => 'Indeed',
        'linkedin.com' => 'LinkedIn',
    ];
    if ($profileId === null || $storedProfileId === null || !hash_equals($storedProfileId, $profileId)
        || $jobs === null || $jobs === [] || $ort === null
        || !in_array($radius, $allowedRadii, true)
        || !is_string($remote) || !isset($remoteLabels[$remote])
        || !is_string($employment) || !isset($employmentLabels[$employment])
        || $sources === null || $sources === [] || $exclusions === null
        || array_diff($sources, array_keys($sourceLabels))) {
        throw new InvalidArgumentException('fields');
    }

    $radiusText = $radius === 'egal' ? 'Egal' : $radius . ' km';
    $sourceText = implode(', ', array_map(static fn(string $source): string => $sourceLabels[$source], $sources));
    $chatInput = "Gesuchter Beruf:\n" . implode(', ', $jobs)
        . "\n\nOrt:\n{$ort}"
        . "\n\nUmkreis:\n{$radiusText}"
        . "\n\nArbeitsform / Remote-Wunsch:\n{$remoteLabels[$remote]}"
        . "\n\nBeschäftigungsart:\n{$employmentLabels[$employment]}"
        . "\n\nGewünschte Webseiten:\n{$sourceText}"
        . "\n\nAusschlusskriterien:\n{$exclusions}";

    return [
        'profile_id' => $profileId,
        'sessionId' => bin2hex(random_bytes(16)),
        'chatInput' => $chatInput,
    ];
}

function job_finder_search_output(array $response): string
{
    $output = $response['output'] ?? null;
    if (($response['success'] ?? false) !== true || !is_string($output) || $output === ''
        || strlen($output) > 2 * 1024 * 1024) {
        throw new RuntimeException('Invalid search response.');
    }
    return $output;
}

function job_finder_text(mixed $value, int $max, bool $required = false): ?string
{
    if (!is_string($value)) return null;
    $value = trim($value);
    if (($required && $value === '') || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', $value)) return null;
    return $value;
}
