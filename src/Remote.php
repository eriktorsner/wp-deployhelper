<?php

namespace DeployHelper;

/**
 * Class Remote
 * @package DeployHelper
 */
class Remote
{
    private $ftpSettings;
    private $localSettings;
    private $secret;
    private $connId;

    /**
     * Remote constructor.
     * @param $localSettings
     * @param $ftpSettings
     */
    public function __construct($localSettings, $ftpSettings)
    {
        $this->ftpSettings = $ftpSettings;
        $this->localSettings = $localSettings;
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

    public function sendChanges($changeSet)
    {

        print_r($changeSet);
        if (count($changeSet) == 0) {
            return;
        }

        // Create a zip file
        $zip = new \ZipArchive();
        $zipFile = tempnam(sys_get_temp_dir(), 'DeployHelper');
        $ret = $zip->open($zipFile, \ZipArchive::OVERWRITE);

        // iterate the changeSet
        $localBase = rtrim($this->ftpSettings->localPath, '/');
        foreach ($changeSet as $file => $change) {
            $zip->addFile($localBase . $file, $file);
        }
        $zip->close();

        // send it to the target
        ftp_chdir($this->connId, $this->ftpSettings->remotePath);
        ftp_put($this->connId, $this->secret. '.zip', $zipFile, FTP_BINARY);
        unlink($zipFile);

        // ask the agent to unpack
        $agent = $this->secret . '.php';
        $url = $this->properUrl($this->ftpSettings->httpUrl) . "/$agent?cmd=unpack";
        file_get_contents($url);

    }

    public function cleanUp()
    {
        ftp_chdir($this->connId, $this->ftpSettings->remotePath);
        $agent = $this->secret . '.php';
        $zip = $this->secret . '.zip';

        //ftp_delete($this->connId, $agent);
        //ftp_delete($this->connId, $zip);
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
