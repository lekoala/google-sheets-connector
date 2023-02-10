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

use ParagonIE\ConstantTime\Hex;

require './_common.php';

loadEnv();

/**
 * Convert hex to rgb
 * 
 * @param string $hexstring 6 chars hex string
 * 
 * @return array
 */
function hextorgb($hexstring)
{
    if (!is_string($hexstring)) {
        throw new InvalidArgumentException('Hex string must be a string');
    }
    if (!preg_match('/([a-fA-F0-9]{6})/', $hexstring)) {
        throw new InvalidArgumentException('Hex string ' . $hexstring . ' is not valid');
    }
    $int = hexdec("0x" . $hexstring);
    return [
        "red" => 0xFF & ($int >> 0x10),
        "green" => 0xFF & ($int >> 0x8),
        "blue" => 0xFF & $int
    ];
}

$challenge = isset($_GET['challenge']) ? intval($_GET['challenge']) : null;
if (!$challenge) {
    die("Missing challenge");
}

$background_color_values = hextorgb('FFFFFF');
if (isset($_GET['bg'])) {
    $background_color_values = hextorgb($_GET['bg']);
}

$captcha_image_height = 50;
$captcha_image_width = 130;
// $total_characters_on_image = 6;
$captcha_code = convertChallenge($challenge);
//The characters that can be used in the CAPTCHA code.
//avoid all confusing characters and numbers (For example: l, 1 and i)
$possible_captcha_letters = 'bcdfghjkmnpqrstvwxyz23456789';
$captcha_font = '../fonts/monofont.ttf';

$random_captcha_dots = 50;
$random_captcha_lines = 25;
$text_color = hextorgb('142864');
if (isset($_GET['color'])) {
    $text_color = hextorgb($_GET['color']);
}
$captcha_text_color = $text_color;
$captcha_noise_color = $text_color;

$captcha_font_size = $captcha_image_height * 0.65;
$captcha_image = @imagecreate(
    $captcha_image_width,
    $captcha_image_height
);

// setting the background, text and noise colours here
$background_color = imagecolorallocate(
    $captcha_image,
    $background_color_values['red'],
    $background_color_values['green'],
    $background_color_values['blue']
);

$captcha_text_color = imagecolorallocate(
    $captcha_image,
    $captcha_text_color['red'],
    $captcha_text_color['green'],
    $captcha_text_color['blue']
);

$image_noise_color = imagecolorallocate(
    $captcha_image,
    $captcha_noise_color['red'],
    $captcha_noise_color['green'],
    $captcha_noise_color['blue']
);

// Generate random dots in background of the captcha image
for ($count = 0; $count < $random_captcha_dots; $count++) {
    imagefilledellipse(
        $captcha_image,
        random_int(0, $captcha_image_width),
        random_int(0, $captcha_image_height),
        2,
        3,
        $image_noise_color
    );
}

// Generate random lines in background of the captcha image
for ($count = 0; $count < $random_captcha_lines; $count++) {
    imageline(
        $captcha_image,
        random_int(0, $captcha_image_width),
        random_int(0, $captcha_image_height),
        random_int(0, $captcha_image_width),
        random_int(0, $captcha_image_height),
        $image_noise_color
    );
}

// Create a text box and add 6 captcha letters code in it
$text_box = imagettfbbox(
    $captcha_font_size,
    0,
    $captcha_font,
    $captcha_code
);
$x = ($captcha_image_width - $text_box[4]) / 2;
$y = ($captcha_image_height - $text_box[5]) / 2;
imagettftext(
    $captcha_image,
    $captcha_font_size,
    0,
    intval($x),
    intval($y),
    $captcha_text_color,
    $captcha_font,
    $captcha_code
);


// output image in png
header('Content-Type: image/png');
imagepng($captcha_image);
imagedestroy($captcha_image);
