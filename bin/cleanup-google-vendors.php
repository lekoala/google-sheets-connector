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

$services = ['Sheets'];
$servicesDir = './vendor/google/apiclient-services/src/*';

/**
 * Delete a dir/file
 *
 * @param string $dir The dir to delete
 * 
 * @return bool
 */
function del($dir)
{
    if (is_file($dir)) {
        return unlink($dir);
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );

    foreach ($files as $fileinfo) {
        $path = $fileinfo->getRealPath();
        if ($fileinfo->isDir()) {
            rmdir($path);
        } else {
            unlink($path);
        }
    }

    return rmdir($dir);
}

$files = glob($servicesDir);
foreach ($files as $file) {
    $basename = basename($file);
    $basename = str_replace('.php', '', $basename);
    if (!in_array($basename, $services)) {
        del($file);
    }
}
