<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';
$config = job_finder_require_config(['search_url', 'secret']);

try {
    job_finder_require_request('application/json');
    job_finder_rate_limit('search', 10, 3600);
    if (!isset($_SESSION['job_finder_profile_id'], $_SESSION['job_finder_profile_at'])
        || !is_string($_SESSION['job_finder_profile_id']) || time() - (int) $_SESSION['job_finder_profile_at'] > 7200) {
        job_finder_json(400, ['success' => false, 'error' => 'Bitte lade zuerst einen Lebenslauf hoch.']);
    }
    $raw = file_get_contents('php://input', false, null, 0, 16385);
    if (!is_string($raw) || strlen($raw) > 16384) throw new InvalidArgumentException('body');
    $input = json_decode($raw, true, 16, JSON_THROW_ON_ERROR);
    $allowedKeys = ['suchmodus', 'job', 'berufsbereiche', 'ort', 'radius', 'remote', 'beschaeftigungsart', 'webseiten', 'ausschluesse'];
    if (!is_array($input) || array_diff(array_keys($input), $allowedKeys)) throw new InvalidArgumentException('keys');

    $mode = $input['suchmodus'] ?? null;
    $job = job_finder_text($input['job'] ?? null, 120) ?? throw new InvalidArgumentException('job');
    $areas = job_finder_occupations($input['berufsbereiche'] ?? null);
    $ort = job_finder_text($input['ort'] ?? null, 100, true);
    $radius = $input['radius'] ?? null;
    $remote = $input['remote'] ?? null;
    $employment = $input['beschaeftigungsart'] ?? null;
    $sources = job_finder_string_array($input['webseiten'] ?? null, 5, 80);
    $exclusions = job_finder_string_array($input['ausschluesse'] ?? null, 20, 80);
    if (!in_array($mode, ['mehrere_berufsbereiche', 'bestimmter_job'], true) || $areas === null || $ort === null
        || !in_array($radius, [10, 25, 50, 100, 'egal', ''], true)
        || !in_array($remote, ['egal', 'remote', 'hybrid', 'vor_ort'], true)
        || !in_array($employment, ['egal', 'Vollzeit', 'Teilzeit', 'Freelancer'], true)
        || $sources === null || $exclusions === null) throw new InvalidArgumentException('fields');
    if ($mode === 'mehrere_berufsbereiche') {
        if ($areas === []) throw new InvalidArgumentException('mode');
        $job = '';
    } elseif ($mode === 'bestimmter_job') {
        if ($job === '') throw new InvalidArgumentException('mode');
        $areas = [];
    }
    $allowedSources = ['unternehmensseiten', 'stepstone.de', 'arbeitsagentur.de', 'indeed.com', 'linkedin.com'];
    if (array_diff($sources, $allowedSources)) throw new InvalidArgumentException('sources');

    $payload = compact('job', 'ort', 'radius', 'remote');
    $payload += ['profile_id' => $_SESSION['job_finder_profile_id'], 'suchmodus' => $mode, 'berufsbereiche' => $areas,
        'beschaeftigungsart' => $employment, 'webseiten' => $sources, 'ausschluesse' => $exclusions];
    $response = job_finder_call($config['search_url'], $config['secret'], [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_THROW_ON_ERROR),
        CURLOPT_HTTPHEADER => ['Accept: application/json', 'Content-Type: application/json',
            'X-Job-Finder-Secret: ' . $config['secret']],
    ], 180);
    $jobs = $response['ergebnisse'] ?? null;
    $searched = $response['gesuchte_berufe'] ?? [];
    if (($response['success'] ?? false) !== true || !is_array($jobs) || count($jobs) > 100
        || job_finder_string_array($searched, 20, 120) === null) throw new RuntimeException('Invalid search response.');
    job_finder_json(200, ['success' => true, 'gesuchte_berufe' => $searched, 'ergebnisse' => $jobs]);
} catch (JsonException|InvalidArgumentException) {
    job_finder_json(422, ['success' => false, 'error' => 'Bitte prüfe deine Suchkriterien.']);
} catch (JobFinderTimeoutException) {
    job_finder_json(504, ['success' => false, 'error' => 'Die Jobsuche dauert momentan zu lange. Bitte versuche es erneut.']);
} catch (Throwable $exception) {
    error_log('Job-Finder search failed: ' . get_class($exception));
    job_finder_json(502, ['success' => false, 'error' => 'Die Jobsuche konnte momentan nicht abgeschlossen werden.']);
}
