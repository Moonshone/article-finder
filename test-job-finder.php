#!/usr/bin/env php
<?php

declare(strict_types=1);

require_once __DIR__ . '/app/job-finder.php';

function assert_true(bool $condition, string $message): void
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

function assert_invalid(array $input, string $profileId, string $message): void
{
    try {
        job_finder_search_payload($input, $profileId);
        assert_true(false, $message);
    } catch (InvalidArgumentException) {
        // Expected.
    }
}

$profileId = '550e8400-e29b-41d4-a716-446655440000';
$valid = [
    'profile_id' => $profileId,
    'jobs' => ['Scrum Master', 'Projektmanager'],
    'ort' => 'Hannover',
    'radius' => 100,
    'remote' => 'remote',
    'beschaeftigungsart' => 'Vollzeit',
    'webseiten' => ['unternehmensseiten', 'stepstone.de', 'arbeitsagentur.de', 'indeed.com', 'linkedin.com'],
    'ausschluesse' => 'Senior, Führungskraft, Personalverantwortung',
];
$payload = job_finder_search_payload($valid, $profileId);
$expected = "Gesuchter Beruf:\nScrum Master, Projektmanager\n\nOrt:\nHannover\n\nUmkreis:\n100 km"
    . "\n\nArbeitsform / Remote-Wunsch:\nRemote\n\nBeschäftigungsart:\nVollzeit"
    . "\n\nGewünschte Webseiten:\nUnternehmensseiten, StepStone, Bundesagentur für Arbeit, Indeed, LinkedIn"
    . "\n\nAusschlusskriterien:\nSenior, Führungskraft, Personalverantwortung";
assert_true(array_keys($payload) === ['profile_id', 'sessionId', 'chatInput'], 'n8n request has exactly three top-level fields');
assert_true($payload['profile_id'] === $profileId && $payload['chatInput'] === $expected, 'profile and exact chatInput');
assert_true((bool) preg_match('/\A[a-f0-9]{32}\z/', $payload['sessionId']), 'cryptographically generated session ID shape');
assert_true($payload['sessionId'] !== job_finder_search_payload($valid, $profileId)['sessionId'], 'new session ID per search');

$cases = [
    'missing profile' => array_diff_key($valid, ['profile_id' => true]),
    'manipulated profile' => array_replace($valid, ['profile_id' => '../secret']),
    'different profile' => array_replace($valid, ['profile_id' => '550e8400-e29b-41d4-a716-446655440001']),
    'missing job' => array_replace($valid, ['jobs' => []]),
    'invalid radius' => array_replace($valid, ['radius' => '100 OR 1=1']),
    'negative radius' => array_replace($valid, ['radius' => -10]),
    'huge radius' => array_replace($valid, ['radius' => 999999]),
    'invalid remote' => array_replace($valid, ['remote' => '<script>alert(1)</script>']),
    'invalid employment' => array_replace($valid, ['beschaeftigungsart' => 'CEO']),
    'unknown source' => array_replace($valid, ['webseiten' => ['javascript:alert(1)']]),
    'oversized job' => array_replace($valid, ['jobs' => [str_repeat('A', 81)]]),
    'oversized place' => array_replace($valid, ['ort' => str_repeat('A', 101)]),
    'oversized exclusions' => array_replace($valid, ['ausschluesse' => str_repeat('A', 501)]),
    'unexpected field' => $valid + ['url' => 'https://attacker.invalid'],
];
foreach ($cases as $message => $input) assert_invalid($input, $profileId, $message);

$xss = array_replace($valid, ['ausschluesse' => '<script>alert(1)</script>, <img src=x onerror=alert(1)>']);
assert_true(str_contains(job_finder_search_payload($xss, $profileId)['chatInput'], '<script>'), 'HTML-like search input remains inert text data');
assert_true(job_finder_search_output(['success' => true, 'output' => "Testausgabe\nhttps://example.test/job"]) === "Testausgabe\nhttps://example.test/job", 'complete mock output is preserved');
foreach ([[], ['success' => false, 'output' => 'x'], ['success' => true], ['success' => true, 'output' => '']] as $response) {
    try {
        job_finder_search_output($response);
        assert_true(false, 'empty, unsuccessful, or missing output rejected');
    } catch (RuntimeException) {
        // Expected.
    }
}

$javascript = file_get_contents(__DIR__ . '/src/job-finder.js');
assert_true(is_string($javascript) && str_contains($javascript, 'result.textContent = output'), 'n8n output uses textContent');
assert_true(!str_contains($javascript, 'innerHTML'), 'frontend does not use innerHTML');

echo "All Job-Finder tests passed.\n";
