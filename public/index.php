<?php

/**
 * Google sheets api connector
 * 
 * PHP Version 8
 * 
 * @category Project
 * @package  SheetsConnectorApi
 * @author   LeKoala <thomas@lekoala.be>
 * @license  https://opensource.org/licenses/MIT MIT
 * @link     https://www.lekoala.be
 * @link     https://developers.google.com/sheets/api/reference/rest/v4/spreadsheets/sheets
 */

use Google\Client;
use Google\Service\Sheets;
use Composer\CaBundle\CaBundle;
use Google\Service\Exception as GoogleException;
use Google\Service\Sheets\ValueRange;
use GuzzleHttp\Client as GuzzleHttpClient;

require '../vendor/autoload.php';
require './_common.php';

/**
 * Get an api client scoped for google sheets
 *
 * @return Client
 */
function getApiClient()
{
    // Configure the Google Client
    $client = new Client();
    $client->setApplicationName('Google Sheets API');
    $client->setScopes([Sheets::SPREADSHEETS]);
    $client->setAccessType('offline');
    // Key file we downloaded while setting up our Google Sheets API
    $path = '../data/' .  $_ENV['CREDENTIALS'];
    $client->setAuthConfig($path);

    $guzzle = new GuzzleHttpClient(
        [
            'base_url' => 'https://www.googleapis.com',
            'verify' => CaBundle::getBundledCaBundlePath()
        ]
    );
    $client->setHttpClient($guzzle);
    return $client;
}

/**
 * Process post payload
 *
 * @return array
 */
function getPayload()
{
    $postData = $_POST;
    $data = [];
    foreach ($postData as $k => $v) {
        // ignore fields starting with _
        if (strpos($k, '_') === 0) {
            continue;
        }
        $data[] = $v;
    }
    return $data;
}

/**
 * Handle request
 *
 * @return array
 */
function handleRequest()
{
    $client = getApiClient();

    // configure the Sheets Service
    $service = new Sheets($client);

    // the spreadsheet id can be found in the url 
    // https://docs.google.com/spreadsheets/d/THE_ID_HERE/edit
    // the spreadsheet must be shared with the service email
    $spreadsheetId = $_GET['id'] ?? null;

    if (!$spreadsheetId) {
        throw new Exception("Missing id parameter");
    }

    $spreadsheet = $service->spreadsheets->get($spreadsheetId);
    $sheets = $spreadsheet->getSheets();
    $firstSheet = $sheets[0];
    $sheetType = $firstSheet->getProperties()->getSheetType();
    $sheetName = $firstSheet->getProperties()->getTitle();

    if ($sheetType !== 'GRID') {
        throw new Exception("Invalid sheet type: $sheetType");
    }

    // Sheets can be prefixed with POST_ and GET_ to only allow read or write
    $isPostSheet = strpos($sheetName, 'POST_') === 0;
    $isGetSheet = strpos($sheetName, 'GET_') === 0;

    $requestMethod = $_SERVER['REQUEST_METHOD'];
    if ($requestMethod === 'GET') {
        if ($isPostSheet) {
            throw new Exception("GET is not allowed for this sheet");
        }
        // get all the rows of a sheet
        $range = $sheetName;
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues();

        $headers = array_shift($values);
        $data = [
            'headers' => $headers,
            'rows' => $values,
        ];
    } elseif ($requestMethod === 'POST') {
        if ($isGetSheet) {
            throw new Exception("GET is not allowed for this sheet");
        }
        $payload = getPayload();
        if (empty($payload)) {
            throw new Exception("Empty payload");
        }
        // Insert one row
        $rows = [
            getPayload()
        ];
        $valueRange = new ValueRange();
        $valueRange->setValues($rows);
        $range = $sheetName;

        // Avoid funny stuff
        $options = [
            // 'valueInputOption' => 'USER_ENTERED'
            'valueInputOption' => 'RAW'
        ];
        $response = $service->spreadsheets_values->append(
            $spreadsheetId,
            $range,
            $valueRange,
            $options
        );

        $updates = $response->getUpdates();
        $data = [
            'updated_rows' => $updates->getUpdatedRows(),
        ];
    } else {
        throw new Exception("Unsupported method $requestMethod");
    }

    return $data;
}

loadEnv();

$referer = $_SERVER['HTTP_REFERER'] ?? '';
$parsedReferer = parse_url($referer);
$host = $parsedReferer['host'] ?? '';
$scheme = $parsedReferer['scheme'] ?? 'http';
$port = $parsedReferer['port'] ?? 80;

try {
    // Check by host if enabled in env
    $allowedHosts = isset($_ENV['HOSTS']) ? json_decode($_ENV['HOSTS'], JSON_OBJECT_AS_ARRAY) : [];
    if (!empty($allowedHosts) && !in_array($host, $allowedHosts)) {
        throw new Exception("Host is not allowed: $host");
    }

    // Check captcha (can be disabled in env)
    $challenge = $_REQUEST['_challenge'] ?? null;
    $captcha = $_REQUEST['_captcha'] ?? null;
    $disableCaptcha = $_ENV['DISABLE_CAPTCHA'] ?? false;
    if (!$disableCaptcha) {
        if (!$challenge || !$captcha || $captcha != convertChallenge($challenge)) {
            throw new Exception("Invalid captcha");
        }
    }

    $result = handleRequest();
    $data = [
        'success' => true,
        'data' => $result,
    ];
} catch (GoogleException $e) {
    $data = [
        'errors' => $e->getErrors()
    ];
} catch (Exception $e) {
    $data = [
        'errors' => [
            [
                'message' => $e->getMessage(),
            ]
        ]
    ];
}

$success = isset($data['success']) ? true : false;

// No cache
header('Cache-Control: no-store, no-cache, must-revalidate, post-check=0, pre-check=0');
header('Expires: Sat, 26 Jul 1997 05:00:00 GMT');

// Redirect instead of JSON response
$redirectUrl = $_REQUEST['_redirect'] ?? null;
if ($redirectUrl && $referer) {
    $baseUrl = $scheme . '://' . $host;
    if ($port != 80 || $port != 443) {
        $baseUrl .= ":" . $port;
    }

    if ($redirectUrl === 'back') {
        $finalRedirection = strtok($referer, '?');
    } else {
        $finalRedirection = $baseUrl . '/' . ltrim($redirectUrl);
    }

    if ($success) {
        $queryData['success'] = 1;
    } else {
        $queryData['error'] = 1;
        $queryData['message'] = $data['errors'][0]['message'];
    }
    $finalRedirection .= '?' . http_build_query($queryData);
    header('Location: ' . $finalRedirection);
    exit();
}

// Default JSON response
$responseCode = 200;
if (!$success) {
    $responseCode = 500;
}
http_response_code(200);
header('Content-Type: application/json; charset=utf-8');
echo json_encode($data);
