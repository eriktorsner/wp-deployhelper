<?php

namespace DeployHelper;

/**
 * Class Deploy
 * @package DeployHelper
 */
class Deploy
{
    /**
     * @var string
     */
    private $currentLocalState = '';

    /**
     * @var string
     */
    private $currentRemoteState = '';

    /**
     * @var Utils
     */
    private $utils;

    /**
     * @var Remote
     */
    private $remote;

    /**
     * @var Settings
     */
    private $ftpSettings;

    public function deploy()
    {
        $this->ftpSettings = new Settings('ftp');

        $this->utils = new Utils();
        $this->remote = new Remote($this->ftpSettings);

        $localChanges = $this->getModifiedLocalFiles();
        $remoteChanges = $this->getModifiedRemoteFiles();
        $missingRemote = $this->getMissingRemoteFiles();

        // Merge the changes together
        foreach ($remoteChanges as $file => $change) {
            if (!isset($localChanges[$file])) {
                $localChanges[$file] = $change;
            }
        }
        foreach ($missingRemote as $file => $change) {
            if (!isset($localChanges[$file])) {
                $localChanges[$file] = $change;
            }
        }

        // Send the changes to the remote server:
        $status = $this->remote->sendChanges($localChanges);
        foreach ($status->messages as $message) {
            echo "Remote: $message\n";
        }

        $this->remote->cleanUp();
        // ...grab another remote snapshot
        $this->currentRemoteState = $this->remote->remoteScan();

        // if all went well (how do we know?), we're still here... so save state
        $this->remote->cleanUp();
        $this->remote->close();
        $this->saveState();
    }

    /**
     * Save local and remote state
     */
    private function saveState()
    {
        $folder = BASEPATH . '/deployhelper';
        if (!file_exists($folder)) {
            mkdir($folder, 0700, true);
        }
        file_put_contents($folder . '/localstate', $this->currentLocalState);
        file_put_contents($folder . '/remotestate', $this->currentRemoteState);
    }

    /**
     * Get the current state of all files and compare it
     * to a saved state. Return a changeset.
     *
     * @return array
     */
    private function getModifiedLocalFiles()
    {
        // 1. Scan local folders, use md5 sum locally
        $this->currentLocalState = $this->utils->localScan($this->ftpSettings->localPath);
        $currentArray = $this->utils->tabSepStringToArray($this->currentLocalState, 0, 3);
        $saved = $this->utils->readSavedState(BASEPATH . '/deployhelper/localstate');
        $savedArray = $this->utils->tabSepStringToArray($saved, 0, 3);

        $changeSet= array();

        foreach ($savedArray as $file => $sum) {
            if (isset($currentArray[$file])) {
                if ($currentArray[$file] != $sum) {
                    $changeSet[$file] = array('state' => 'LOCALMOD', 'file' => $file);
                }
            } else {
                $changeSet[$file] = array('state' => 'LOCALDEL', 'file' => $file);
            }
        }

        foreach ($currentArray as $file => $sum) {
            if (!isset($savedArray[$file])) {
                $changeSet[$file] = array('state' => 'LOCALNEW', 'file' => $file);
            }
        }

        return $changeSet;
    }

    /**
     * @return array
     */
    private function getModifiedRemoteFiles()
    {
        $this->currentRemoteState = $this->remote->remoteScan();
        $currentArray = $this->utils->tabSepStringToArray($this->currentRemoteState, 0, 3);
        $saved = $this->utils->readSavedState(BASEPATH . '/deployhelper/remotestate');
        $savedArray = $this->utils->tabSepStringToArray($saved, 0, 3);

        $changeSet= array();

        foreach ($savedArray as $file => $sum) {
            if (isset($currentArray[$file])) {
                if ($currentArray[$file] != $sum) {
                    $changeSet[$file] = array('state' => 'REMOTEMOD', 'file' => $file);
                }
            } else {
                $changeSet[$file] = array('state' => 'REMOTEDEL', 'file' => $file);
            }
        }

        return $changeSet;
    }

    /**
     * Identify files that exist locally but not remotely
     * A safety net to make sure that remote files get
     * transferred even if the remote change detection fails
     *
     * @return array
     */
    private function getMissingRemoteFiles()
    {
        // get array with file path/name as key and size as value
        $currentLocal  = $this->utils->tabSepStringToArray($this->currentLocalState, 0, 2);
        $currentRemote =   $this->utils->tabSepStringToArray($this->currentRemoteState, 0, 2);

        $changeSet= array();
        foreach ($currentLocal as $file => $size) {
            if (!isset($currentRemote[$file])) {
                $changeSet[$file] = array('state' => 'missing', 'file' => $file);
            }
        }

        return $changeSet;
    }
}
