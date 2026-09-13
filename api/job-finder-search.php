<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';

try {
    job_finder_require_request('application/json');
    job_finder_rate_limit('search', 10, 3600);
    if (!isset($_SESSION['job_finder_profile'], $_SESSION['job_finder_profile_at']) || time() - (int) $_SESSION['job_finder_profile_at'] > 7200) {
        job_finder_json(409, ['success' => false, 'message' => 'Bitte verarbeite zuerst deinen Lebenslauf erneut.']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($raw) || strlen($raw) > 16384) throw new InvalidArgumentException('body');
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    $allowedKeys = ['job', 'ort', 'radius', 'remote', 'beschaeftigungsart', 'webseiten', 'ausschluesse'];
    if (!is_array($input) || array_diff(array_keys($input), $allowedKeys)) throw new InvalidArgumentException('keys');
    $job = job_finder_text($input['job'] ?? null, 120, true);
    $ort = job_finder_text($input['ort'] ?? null, 100, true);
    $radii = [10, 25, 50, 100, 'egal'];
    $radius = $input['radius'] ?? null;
    $remote = $input['remote'] ?? null;
    $employment = $input['beschaeftigungsart'] ?? null;
    $sources = $input['webseiten'] ?? null;
    $exclusions = $input['ausschluesse'] ?? null;
    if ($job === null || $ort === null || !in_array($radius, $radii, true)
        || !in_array($remote, ['egal', 'remote', 'hybrid', 'vor-ort'], true)
        || !in_array($employment, ['Egal', 'Vollzeit', 'Teilzeit', 'Freelancer'], true)
        || !is_array($sources) || count($sources) > 5 || !is_array($exclusions) || count($exclusions) > 20) throw new InvalidArgumentException('fields');
    $allowedSources = ['unternehmensseiten', 'stepstone.de', 'arbeitsagentur.de', 'indeed.com', 'linkedin.com'];
    if ($sources !== array_values(array_unique($sources)) || array_diff($sources, $allowedSources)) throw new InvalidArgumentException('sources');
    $cleanExclusions = [];
    foreach ($exclusions as $value) {
        $clean = job_finder_text($value, 80, true);
        if ($clean === null) throw new InvalidArgumentException('exclusions');
        $cleanExclusions[] = $clean;
    }
    $payload = ['profile_id' => $_SESSION['job_finder_profile'], 'job' => $job, 'ort' => $ort, 'radius' => $radius,
        'remote' => $remote, 'beschaeftigungsart' => $employment, 'webseiten' => $sources, 'ausschluesse' => $cleanExclusions];
    $response = job_finder_call('N8N_JOB_FINDER_SEARCH_URL', [CURLOPT_POST => true, CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json', 'Authorization: Bearer ' . job_finder_config('N8N_JOB_FINDER_WEBHOOK_SECRET')]]);
    $jobs = $response['jobs'] ?? $response['results'] ?? null;
    if (($response['success'] ?? true) !== true || !is_array($jobs) || count($jobs) > 100) throw new RuntimeException('Invalid search response.');
    job_finder_json(200, ['success' => true, 'jobs' => $jobs]);
} catch (JsonException|InvalidArgumentException) {
    job_finder_json(422, ['success' => false, 'message' => 'Bitte prüfe deine Suchkriterien.']);
} catch (Throwable $exception) {
    error_log('Job-Finder search failed: ' . get_class($exception));
    job_finder_json(502, ['success' => false, 'message' => 'Die Job-Suche konnte momentan nicht abgeschlossen werden.']);
}
