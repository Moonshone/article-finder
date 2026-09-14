<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/job-finder.php';

try {
    job_finder_require_request('multipart/form-data');
    $config = job_finder_require_config(['upload_url', 'secret']);
    job_finder_rate_limit('upload', 5, 3600);
    $declaredLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    if ($declaredLength > JOB_FINDER_MAX_FILE_SIZE + 262144) throw new InvalidArgumentException('oversized');
    if (!isset($_FILES['lebenslauf']) || !is_array($_FILES['lebenslauf']) || count($_FILES) !== 1) throw new InvalidArgumentException('missing');
    $file = $_FILES['lebenslauf'];
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'] ?? '')) throw new InvalidArgumentException('upload');
    if (!is_int($file['size']) || $file['size'] < 5 || $file['size'] > JOB_FINDER_MAX_FILE_SIZE) throw new InvalidArgumentException('size');
    $name = $file['name'] ?? '';
    if (!is_string($name) || strtolower(pathinfo($name, PATHINFO_EXTENSION)) !== 'pdf') throw new InvalidArgumentException('extension');
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $signature = file_get_contents($file['tmp_name'], false, null, 0, 5);
    if ($mime !== 'application/pdf' || $signature !== '%PDF-') throw new InvalidArgumentException('type');

    $response = job_finder_call($config['upload_url'], $config['secret'], [CURLOPT_POST => true, CURLOPT_POSTFIELDS => [
        'lebenslauf' => new CURLFile($file['tmp_name'], 'application/pdf', 'lebenslauf.pdf'),
    ]]);
    $profileId = $response['profile_id'] ?? null;
    if (($response['success'] ?? false) !== true || job_finder_profile_id($profileId) === null) {
        throw new RuntimeException('Invalid upload response.');
    }
    session_regenerate_id(true);
    $_SESSION['job_finder_profile_id'] = $profileId;
    $_SESSION['job_finder_profile_at'] = time();
    job_finder_json(200, ['success' => true, 'profile_id' => $profileId]);
} catch (InvalidArgumentException) {
    job_finder_json(422, ['success' => false, 'error' => 'Bitte wähle eine gültige PDF mit maximal 5 MB aus.']);
} catch (Throwable $exception) {
    error_log('Job-Finder upload failed: ' . get_class($exception));
    job_finder_json(502, ['success' => false, 'error' => 'Der Lebenslauf konnte nicht verarbeitet werden.']);
}
