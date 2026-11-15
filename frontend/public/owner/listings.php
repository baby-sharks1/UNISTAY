<?php
// Legacy UI route redirects to the real TiDB-powered Owner module.
declare(strict_types=1);
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
    http_response_code(405);
    header('Allow: GET');
    exit('Use the real /owner/ page to submit changes.');
}
$queryString = (string) ($_SERVER['QUERY_STRING'] ?? '');
$url = '/owner/listings.php' . ($queryString !== '' ? '?' . $queryString : '');
header('Cache-Control: no-store');
header('Location: ' . $url, true, 302);
exit;
