<?php

// This is the agent file that we send to the remote
// site and use for quickly scanning the content and
// use for uploading new files.


error_reporting(-1);

define('ABSPATH', dirname(__FILE__));
$ignore = array(
    'wp-snapshots',              // plugin: duplicator
    'wp-content/cache',          // General cache folder,
    '*~',                        // temp files
);

$cmd = $_GET['cmd'];
switch ($cmd) {
    case 'scan':
        $fileName = str_replace('.php', '.tmp', __FILE__);
        $ignore[] = '/'.basename(__FILE__);
        $ignore[] = '/'.basename($fileName);

        $fileHandle = fopen($fileName, 'w');
        recScandir(ABSPATH, $fileHandle);
        fclose($fileHandle);

        echo file_get_contents($fileName);
        unlink($fileName);
        break;
    case 'unpack':
        $fileName = str_replace('.php', '.zip', __FILE__);
        unpackZip($fileName);


        break;
}


function recScandir($dir, $f)
{
    global $ignore;
    $dir = rtrim($dir, '/');
    $root = scandir($dir);
    foreach ($root as $value) {
        if ($value === '.' || $value === '..') {
            continue;
        }
        if (fnInArray("$dir/$value", $ignore)) {
            continue;
        }
        if (is_file("$dir/$value")) {
            fileInfo2File($f, "$dir/$value");
            continue;
        }
        fileInfo2File($f, "$dir/$value");
        recScandir("$dir/$value", $f);
    }
}

function fileInfo2File($f, $file)
{
    $stat = stat($file);
    $sum = sha1($stat['size'] . $stat['mtime']);
    $relfile = substr($file, strlen(ABSPATH));
    $row =  array(
        $relfile,
        is_dir($file) ? 0 : $stat['mtime'],
        is_dir($file) ? 0 : $stat['size'],
        is_dir($file) ? 0 : $sum,
        (int) is_dir($file),
        (int) is_file($file),
        (int) is_link($file),
    );
    fwrite($f, join("\t", $row) . "\n");
}

function fnInArray($needle, $haystack)
{
    # this function allows wildcards in the array to be searched
    $needle = substr($needle, strlen(ABSPATH));#
    foreach ($haystack as $value) {
        if (true === fnmatch($value, $needle)) {
            return true;
        }
    }

    return false;
}

function unpackZip($fileName)
{
    $zip = new ZipArchive();
    $zip->open($fileName);
    $base = dirname(__FILE__);

    for ($i = 0; $i<$zip->numFiles; $i++) {
        $file = $zip->getNameIndex($i);
        if (!is_dir($file) && $file != '_deployhelpermanifest') {
            $zip->extractTo($base, $file);
        } else {
            continue;
        }
    }
    $zip->close();
}
