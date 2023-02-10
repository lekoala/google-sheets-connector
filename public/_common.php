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
 */

define('CAPTCHA_CHARS', 6);

/**
 * Convert a challenge to a string
 *
 * @param string $challenge The challenge
 * 
 * @return string
 */
function convertChallenge($challenge)
{
    // More or less a 30 min window of validity
    $min = intval(date('i'));
    if ($min > 30) {
        $salt = date('YmdH', strtotime('+1 hour'));
    } else {
        $salt = date('YmdH');
    }

    // Set this to a random string
    if (isset($_ENV['SECRET'])) {
        $salt .= $_ENV['SECRET'];
    }

    $hash = md5($challenge . $salt);
    $numericHash = preg_replace('/[^0-9.]+/', '', $hash);

    $possible_captcha_letters = 'bcdfghjkmnpqrstvwxyz23456789';

    $captcha_code = '';
    $count = 0;
    $i = 0;
    $idx = 0;
    $max = strlen($possible_captcha_letters) - 1;
    $maxIdx = strlen($numericHash) - 1;
    while ($count < CAPTCHA_CHARS) {
        if ($idx > $maxIdx) {
            $idx = 0;
        }
        $i += $numericHash[$idx];
        if ($i > $max) {
            $i -= $max;
        }
        $captcha_code .= $possible_captcha_letters[$i];
        $idx++;
        $count++;
    }
    return $captcha_code;
}

/**
 * Load .env into $_ENV
 *
 * @return void
 */
function loadEnv()
{
    if (!is_file("../.env")) {
        return;
    }
    $result = parse_ini_file("../.env");
    if (!$result) {
        throw new RuntimeException("Failed to parse .env file");
    }
    foreach ($result as $k => $v) {
        // Make sure that we are not overwriting variables
        if (isset($_ENV[$k])) {
            throw new RuntimeException("Could not redefine $k in ENV");
        }
        // Convert to proper types
        if ($v === 'true') {
            $v = true;
        } elseif ($v === 'false') {
            $v = false;
        } elseif ($v === 'null') {
            $v = null;
        }
        $_ENV[$k] = $v;
    }
}
