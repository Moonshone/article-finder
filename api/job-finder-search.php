<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';

try {
    job_finder_require_request('application/json');
    $config = job_finder_require_config(['search_url', 'secret']);
    job_finder_rate_limit('search', 10, 3600);
    if (!isset($_SESSION['job_finder_profile_id'], $_SESSION['job_finder_profile_at'])
        || !is_string($_SESSION['job_finder_profile_id']) || time() - (int) $_SESSION['job_finder_profile_at'] > 7200) {
        job_finder_json(400, ['success' => false, 'error' => 'Bitte lade zuerst einen Lebenslauf hoch.']);
    }
    $raw = file_get_contents('php://input', false, null, 0, JOB_FINDER_MAX_SEARCH_BODY_SIZE + 1);
    if (!is_string($raw) || strlen($raw) > JOB_FINDER_MAX_SEARCH_BODY_SIZE) throw new InvalidArgumentException('body');
    try {
        $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('json');
    }
    $payload = job_finder_search_payload($input, $_SESSION['job_finder_profile_id']);
    $response = job_finder_call($config['search_url'], $config['secret'], [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json',
            'X-Job-Finder-Secret: ' . $config['secret']],
    ], 180);
    job_finder_json(200, ['success' => true, 'output' => job_finder_search_output($response)]);
} catch (InvalidArgumentException) {
    job_finder_json(422, ['success' => false, 'error' => 'Bitte prüfe deine Suchkriterien.']);
} catch (JobFinderTimeoutException) {
    job_finder_json(504, ['success' => false, 'error' => 'Die Jobsuche dauert momentan zu lange. Bitte versuche es erneut.']);
} catch (Throwable $exception) {
    error_log('Job-Finder search failed: ' . get_class($exception));
    job_finder_json(502, ['success' => false, 'error' => 'Die Jobsuche konnte momentan nicht abgeschlossen werden.']);
}
