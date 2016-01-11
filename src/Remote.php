<?php

namespace DeployHelper;

/**
 * Class Remote
 * @package DeployHelper
 */
class Remote
{
    private $ftpSettings;
    private $secret;
    private $connId;

    /**
     * Remote constructor.
     * @param $ftpSettings
     */
    public function __construct($ftpSettings)
    {
        $this->ftpSettings = $ftpSettings;
        $this->secret = md5(time() . 'a3dcb4d229de6fde0db5686dee47145d');
    }

    /**
     * @return bool|string
     */
    public function remoteScan()
    {
        $this->connId = ftp_connect($this->ftpSettings->host);
        $login = @ftp_login(
            $this->connId,
            $this->ftpSettings->user,
            $this->ftpSettings->pass
        );
        if ($login) {
            ftp_pasv($this->connId, true);
            $file = dirname(__FILE__) . '/../resources/Agent.php';
            $agent = $this->secret . '.php';
            ftp_chdir($this->connId, $this->ftpSettings->remotePath);
            ftp_put($this->connId, $agent, $file, FTP_ASCII);

            $url = $this->properUrl($this->ftpSettings->httpUrl) . "/$agent?cmd=scan";
            $result = file_get_contents($url);

            return $result;
        } else {
            return false;
        }
    }

    /**
     * Iterates through the $changeSet array and adds the files
     * to a zip archive. The archive is then sent to the target
     * ftp host and unpacked by the local agent.
     *
     * @param array $changeSet
     * @return object
     */
    public function sendChanges($changeSet)
    {

        if (count($changeSet) == 0) {
            return json_decode('{"messages":[]}');
        }

        // Create a zip file
        $zip = new \ZipArchive();
        $zipFile = tempnam(sys_get_temp_dir(), 'DeployHelper');
        $ret = $zip->open($zipFile, \ZipArchive::OVERWRITE);

        // iterate the changeSet
        $localBase = rtrim($this->ftpSettings->localPath, '/');
        $folders = array();
        $deleted = array();
        foreach ($changeSet as $file => $change) {
            if (is_dir($localBase . $file)) {
                $folders[] = $file;
            } else {
                if ($change['state'] == 'LOCALDEL') {
                    $deleted[] = $file;
                } else {
                    $zip->addFile($localBase . $file, $file);
                }
            }
        }

        $zip->addFromString('_wpdph_folders', serialize($folders));
        $zip->addFromString('_wpdph_delete', serialize($deleted));
        if (isset($this->ftpSettings->rewrite)) {
            $zip->addFromString('_wpdph_rewrite', serialize($this->ftpSettings->rewrite));
        } else {
            $zip->addFromString('_wpdph_rewrite', serialize(array()));
        }
        $zip->close();

        // send it to the target
        ftp_chdir($this->connId, $this->ftpSettings->remotePath);
        ftp_put($this->connId, $this->secret. '.zip', $zipFile, FTP_BINARY);
        //unlink($zipFile);

        // ask the agent to unpack
        $agent = $this->secret . '.php';
        $url = $this->properUrl($this->ftpSettings->httpUrl) . "/$agent?cmd=unpack";
        $ret = file_get_contents($url);

        return json_decode($ret);

    }

    public function cleanUp()
    {
        $agent = $this->secret . '.php';
        $url = $this->properUrl($this->ftpSettings->httpUrl) . "/$agent?cmd=selfdestruct";
        file_get_contents($url);
    }

    public function close()
    {
        ftp_close($this->connId);
    }

    /**
     * Ensure the URL has a valid protocol
     *
     * @param string $url
     * @return string
     */
    private function properUrl($url)
    {
        if (substr($url, 0, 4) != 'http') {
            $url = 'http://'. $url;
        }

        return $url;
    }
}
