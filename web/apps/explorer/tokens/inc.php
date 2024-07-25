<?php

if(!defined("PAGE")) exit;

function stringToHex($string) {
    // Initialize an empty string to hold the hexadecimal result
    $hex = '';

    // Iterate over each character in the input string
    for ($i = 0; $i < strlen($string); $i++) {
        // Convert each character to its ASCII value, then to hexadecimal
        $hex .= dechex(ord($string[$i]));
    }

    return $hex;
}

/**
 * Convert a hex color to its RGB components.
 *
 * @param string $hex The hex color code (e.g., "#ffcc00" or "ffcc00").
 * @return array An array with RGB components.
 */
function hexToRgb($hex) {
    // Remove the hash at the start if it's there
    $hex = ltrim($hex, '#');

    // If shorthand notation (e.g., #fc0), expand it
    if (strlen($hex) == 3) {
        $r = hexdec(str_repeat(substr($hex, 0, 1), 2));
        $g = hexdec(str_repeat(substr($hex, 1, 1), 2));
        $b = hexdec(str_repeat(substr($hex, 2, 1), 2));
    } else {
        $r = hexdec(substr($hex, 0, 2));
        $g = hexdec(substr($hex, 2, 2));
        $b = hexdec(substr($hex, 4, 2));
    }

    return [$r, $g, $b];
}

/**
 * Calculate the luminance of an RGB color.
 *
 * @param array $rgb An array containing RGB components.
 * @return float The calculated luminance.
 */
function calculateLuminance($rgb) {
    // Normalize RGB values to the range 0-1
    $r = $rgb[0] / 255;
    $g = $rgb[1] / 255;
    $b = $rgb[2] / 255;

    // Apply the sRGB luminance formula
    $luminance = 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;

    return $luminance;
}

/**
 * Determine the best text color (black or white) for a given background.
 *
 * @param string $backgroundColor The hex color code of the background.
 * @return string "black" or "white" depending on which contrasts better.
 */
function getContrastingTextColor($backgroundColor) {
    // Convert background color to RGB
    $rgb = hexToRgb($backgroundColor);

    // Calculate luminance
    $luminance = calculateLuminance($rgb);

    // Return white for dark backgrounds and black for light backgrounds
    return $luminance > 0.5 ? '#000000' : '#FFFFFF'; // Return in hex format
}
