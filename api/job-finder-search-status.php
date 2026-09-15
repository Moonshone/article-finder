<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';

try {
    job_finder_require_request('application/json');
    $config = job_finder_require_config(['search_url', 'secret']);
    job_finder_rate_limit('search-status', 1000, 3600);
    $raw = file_get_contents('php://input', false, null, 0, 1025);
    if (!is_string($raw) || strlen($raw) > 1024) throw new InvalidArgumentException('body');
    try {
        $input = json_decode($raw, true, 4, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        throw new InvalidArgumentException('json');
    }
    if (!is_array($input) || array_keys($input) !== ['search_id']) throw new InvalidArgumentException('keys');
    $searchId = job_finder_search_id($input['search_id']);
    if ($searchId === null) throw new InvalidArgumentException('search_id');

    $response = job_finder_call(job_finder_search_status_url($config['search_url']), $config['secret'], [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode(['search_id' => $searchId], JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json',
            'X-Job-Finder-Secret: ' . $config['secret']],
    ], 15);
    job_finder_json(200, $response);
} catch (InvalidArgumentException) {
    job_finder_json(422, ['status' => 'fehler', 'success' => false, 'error' => 'Die Such-ID ist ungültig.']);
} catch (JobFinderTimeoutException) {
    job_finder_json(504, ['status' => 'fehler', 'success' => false, 'error' => 'Die Statusabfrage hat zu lange gedauert. Bitte versuche es erneut.']);
} catch (Throwable $exception) {
    error_log('Job-Finder search status failed: ' . get_class($exception));
    job_finder_json(502, ['status' => 'fehler', 'success' => false, 'error' => 'Der Status der Jobsuche konnte momentan nicht abgerufen werden.']);
}
