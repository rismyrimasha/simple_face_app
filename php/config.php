<?php

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'faceapp');
define('DB_USER', 'root');
define('DB_PASS', '');

// Python API configuration
define('PYTHON_API', 'http://localhost:5001');

// Upload directory (absolute path) and base URL for images
define('UPLOAD_DIR', dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR);

if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0777, true);
}

/**
 * Returns a singleton PDO connection.
 *
 * @return PDO
 */
function db(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ];

        $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    }

    return $pdo;
}

/**
 * Simple helper to call the Python API with CURL.
 *
 * @param string $endpoint
 * @param array  $postFields
 * @param array  $files
 * @return array [decoded_json, error_string|null]
 */
function call_python_api(string $endpoint, array $postFields = [], array $files = []): array
{
    $ch = curl_init();
    $url = rtrim(PYTHON_API, '/') . '/' . ltrim($endpoint, '/');

    $multipart = [];
    foreach ($postFields as $key => $value) {
        $multipart[$key] = $value;
    }
    foreach ($files as $key => $file) {
        $multipart[$key] = $file;
    }

    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => $multipart,
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    if ($response === false) {
        $error = curl_error($ch);
        curl_close($ch);
        return [null, 'Failed to contact Python API: ' . $error];
    }

    $statusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $data = json_decode($response, true);
    if ($data === null) {
        return [null, 'Invalid JSON from Python API (HTTP ' . $statusCode . ')'];
    }

    if ($statusCode < 200 || $statusCode >= 300) {
        return [null, isset($data['error']) ? (string)$data['error'] : 'Python API error (HTTP ' . $statusCode . ')'];
    }

    return [$data, null];
}

