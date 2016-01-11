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
        $ret = new stdClass();
        $ret->status = '200';
        $ret->messages = array();
        unpackZip($fileName, $ret);
        echo json_encode($ret);
        break;
    case 'selfdestruct':
        unlink(__FILE__);
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

function unpackZip($fileName, &$ret)
{
    $zip = new ZipArchive();
    $zip->open($fileName);
    $base = dirname(__FILE__);

    $internal = array('_wpdph_folders', '_wpdph_delete', '_wpdph_rewrite');

    $folders = array();
    if ($strFolders = $zip->getFromName('_wpdph_folders')) {
        $folders = unserialize($strFolders);
        foreach ($folders as $folder) {
            $localName = ltrim($folder, '/');
            if (!@mkdir($localName, 0777, true)) {
                $ret->messages[] = "folder $localName could not be created";
            }
        }
    }

    if ($strRewriteRules = $zip->getFromName('_wpdph_rewrite')) {
        $rules = unserialize($strRewriteRules);
    } else {
        $rules = array();
    }

    if ($strDelete = $zip->getFromName('_wpdph_delete')) {
        $deletes = unserialize($strDelete);
    } else {
        $deletes = array();
    }

    for ($i = 0; $i<$zip->numFiles; $i++) {
        $file = $zip->getNameIndex($i);
        if (in_array($file, $folders)) {
            continue;
        }
        if (in_array($file, $internal)) {
            continue;
        }
        $success = $zip->extractTo($base, $file);
        if (!$success) {
            $ret->messages[] = "file $file could not be extracted";
        } else {
            foreach ($rules as $rule) {
                if (fnmatch($rule->file, $file)) {
                    $content = file_get_contents($base.$file);
                    $newContent = preg_replace($rule->pattern, $rule->replace, $content, -1, $count);
                    if ($count > 0) {
                        file_put_contents($base.$file, $newContent);
                        $ret->messages[] = "$count replacements made in $file";
                    }
                }
            }
        }
    }
    $zip->close();

    foreach ($deletes as $deletedFile) {
        unlink($base . $deletedFile);
    }

    unlink($fileName);
}
