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
    private $localSettings;

    /**
     * @var Settings
     */
    private $ftpSettings;

    public function deploy()
    {
        $this->localSettings = new Settings('local');
        $this->ftpSettings = new Settings('ftp');

        $this->utils = new Utils();
        $this->remote = new Remote($this->localSettings, $this->ftpSettings);


        $localChanges = $this->getModifiedLocalFiles();
        $remoteChanges = $this->getModifiedRemoteFiles();

        print_r($localChanges);
        print_r($remoteChanges);

        // Merge the changes together
        foreach ($remoteChanges as $file => $change) {
            if (!isset($localChanges[$file])) {
                $localChanges[$file] = $change;
            }
        }

        // Send the changes to the remote server:
        $this->remote->sendChanges($localChanges);
        $this->remote->cleanUp();
        $this->currentRemoteState = $this->remote->remoteScan();

        // if all went well, we're still here... so save state
        $this->saveState();
        $this->remote->close();
    }

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
        $this->currentLocalState = $this->utils->localScan('/vagrant/test');
        $currentArray = $this->utils->tabSepStringToArray($this->currentLocalState, 0, 3);
        $saved = $this->utils->readSavedState(BASEPATH . '/deployhelper/localstate');
        $savedArray = $this->utils->tabSepStringToArray($saved, 0, 3);

        $changeSet= array();

        foreach ($savedArray as $file => $sum) {
            if (isset($currentArray[$file])) {
                if ($currentArray[$file] != $sum) {
                    $changeSet[$file] = array('state' => 'MOD', 'file' => $file);
                }
            } else {
                $changeSet[$file] = array('state' => 'DEL', 'file' => $file);
            }
        }

        foreach ($currentArray as $file => $sum) {
            if (!isset($savedArray[$file])) {
                $changeSet[$file] = array('state' => 'NEW', 'file' => $file);
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
                    $changeSet[$file] = array('state' => 'MOD', 'file' => $file);
                }
            } else {
                $changeSet[$file] = array('state' => 'DEL', 'file' => $file);
            }
        }

        return $changeSet;
    }
}
