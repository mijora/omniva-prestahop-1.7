<?php

/**
 * Omniva patcher
 */
class OmnivaPatcher
{
    const VERSION = '0.1.0';

    const API_URL = 'https://www.omnivasiunta.lt/api/v1/updaters';

    // Job types
    const TYPE_ADD = 1;
    const TYPE_ADD_BEFORE = 2;
    const TYPE_REPLACE = 3;
    const TYPE_CREATE_FILE = 4;

    const TEMP_PATCH_DIR = 'temp_patch/';

    private $current_dir = '';
    private $patch_dir = 'patches/';
    private $patch_file = 'test.patch';
    private $version_file = 'omnivaltshipping.php';
    private $temp_patch_dir;
    private $patch_log_file = 'omnivapatcher.log';

    private $update_server_pk = '
        LS0tLS1CRUdJTiBQVUJMSUMgS0VZLS0tLS0KTUlJQklqQU5CZ2txaGtpRzl3MEJBUUVGQUFPQ0FR
        OEFNSUlCQ2dLQ0FRRUF3YUlIOEcyVEIvVFczWFZmNVhjTwo4Sjk4Rm9KYUY1djJaRHNXTy8xdm1n
        UVUza2wwSSthNFRmUStaNGZjSmcwTkpXbCt1SmtNTXFrOUdLYmt1M2ZVClpGVnRWanlrb1NGU055
        cllDUmg4SEtzWi9hT3cxbGZZVVBTQUFZSFphdmJYc210TVkyaGNsclBXL0U5N0xYcFcKVjU5eFdq
        aDJlbDRPOFA5c3RRWUt3ZlpzbWtNbjRvNm95V3NueVpmSmRKSlhxZ2Y4UzdNN2NlRUN4RGhsSWdX
        TQozajFzTXNjMjk4SWlTaEtxanhrVUpPdHVpSDZKVjdiT0RManFPcGZrNU1YRit6K255NGFpQjd0
        SmhMelJrOTNNClVLTkVSQjZRQ1BoejlTZXZWRHJ1eVpaK0VPTDZKdytSVVRqWW9Pd1VXRmlsaXM3
        RXhSaXhRWUhrMDhCTUsweTkKU1FJREFRQUIKLS0tLS1FTkQgUFVCTElDIEtFWS0tLS0t
    ';

    public function __construct()
    {
        $this->current_dir = __DIR__ . '/';
        $this->patch_dir = $this->current_dir . 'patches/';
        $this->logs_path = $this->current_dir;

        $this->checkForPatchDir();
        $this->updateTempPatchDir();
    }

    /**
     * @return string version of OmnivaPatcher
     */
    public function getVersion()
    {
        return self::VERSION;
    }

    /**
     * Starts updating proccess
     * 
     * @param string $api_user Omniva API user
     * @param string $email shop email
     */
    public function startUpdate($api_user = 'test', $email = 'test@test.com')
    {
        $this->patchLog('----- Started Update -----');
        try {
            $this->update($api_user, $email);
        } catch (\Throwable $e) {
            $this->patchLog('Unexpected error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        } catch (\Exception $e) {
            $this->patchLog('Unexpected error: ' . $e->getMessage() . "\n" . $e->getTraceAsString());
        }
        $this->patchLog('----- Finished Update -----');
    }

    /**
     * Runs update process step by step
     * 
     * @param mixed $api_user Omniva API user
     * @param mixed $email shop email
     * 
     * @return bool returns false if there is no patch, downloaded patch fails verification or cant be unzipped
     */
    private function update($api_user, $email)
    {
        $response = $this->getAvailablePatches($api_user, $email);
        if (!$response) {
            $this->patchLog('Nothing to update');

            return false;
        }

        $this->patchLog('Verifying patch');
        if (!$this->verifyPatch($response)) {
            // Do nothing as response seems to be tmapered
            $this->patchLog('Patch failed verification');

            return false;
        }

        $this->patchLog('Extracting patch');
        if (!$this->extractZip(base64_decode($response['file']), $this->temp_patch_dir)) {
            // Something wrong with unzipping
            $this->patchLog('Patch failed to extract');
            $this->patchLog('Cleaning up temporary patch directory before terminating update proccess');
            $this->cleanup();

            return false;
        }

        $this->patchLog('Collecting jobs from patch');
        $jobs_list = $this->getJobsFromPatch();

        $jobs_list = $this->sortJobsByVersion($jobs_list);

        $this->patchLog('Starting Jobs');
        foreach ($jobs_list as $jobFile) {
            $this->patchLog('Begin: ' . $jobFile);

            $job = $this->loadJobFromFile($this->temp_patch_dir . $jobFile . '/' . $jobFile . '.json');

            if (!$job) {
                $this->patchLog('No job instructions found');
                $this->patchLog('Finished: ' . $jobFile);
                continue;
            }

            if (!isset($job['patch_version']) || !isset($job['jobs'])) {
                $this->patchLog('Job file missing patch_version or jobs instructions');
                $this->patchLog('Finished: ' . $jobFile);
                continue;
            }

            $this->setPatchVersion($job['patch_version']);

            $this->work($job['jobs']);

            $this->patchLog('Finished: ' . $jobFile);
        }

        $this->patchLog('Cleaning up temporary patch directory');
        $this->cleanup();

        return true;
    }

    /**
     * Sorts supplied jobs array by version number (removes `patch-` prefix before sorting and adds it back afterwards)
     * 
     * @param array $jobs string array of jobs folder names (version numbers).
     * 
     * @return array sorted $jobs array by version number
     */
    private function sortJobsByVersion($jobs)
    {
        // exctract only version numbers
        $jobs = array_map(
            function ($dir) {
                return str_replace('patch-', '', $dir);
            },
            $jobs
        );

        // sort by version numbers
        usort($jobs, 'version_compare');

        // attach 'patch-' prefix
        $jobs = array_map(
            function ($version) {
                return 'patch-' . $version;
            },
            $jobs
        );

        return $jobs;
    }

    /**
     * Loads job information from json file and decodes it to associated array
     * 
     * @param string $jobFile path to job json file (eg. patch-1.0.0.json)
     * 
     * @return false|array associated array with job information or false on error.
     */
    private function loadJobFromFile($jobFile)
    {
        try {
            $job = @file_get_contents($jobFile);
            if (!$job) {
                $this->patchLog('Failed to load job file');

                return false;
            }
            $job = json_decode($job, true);
        } catch (\Throwable $e) {
            $this->patchLog('Error while loading job file ' . $job . PHP_EOL .
                $e->getMessage() . "\n" .
                $e->getTraceAsString());

            return false;
        } catch (\Exception $e) {
            $this->patchLog('Error while loading job file ' . $job . PHP_EOL .
                $e->getMessage() . "\n" .
                $e->getTraceAsString());

            return false;
        }

        return $job;
    }

    /**
     * Collects and returns jobs list from downloaded patch file
     * 
     * @return array Job list
     */
    private function getJobsFromPatch()
    {
        $jobs = array();

        $jobs = glob($this->temp_patch_dir . 'patch-*', GLOB_MARK);

        // extract only folder names
        $jobs = array_map(
            function ($dir) {
                $path = explode('/', trim($dir, '/'));
                return $path[count($path) - 1];
            },
            $jobs
        );

        return $jobs;
    }

    /**
     * Removes temporary patch directory
     * 
     * @return bool true if cleanup successful
     */
    private function cleanup()
    {
        if ($this->removeDirectory($this->temp_patch_dir)) {
            $this->patchLog('Temporary patch directory removed');

            return true;
        }

        $this->patchLog('Failed to remove temporary patch directory');

        return false;
    }

    /**
     * Removes directory and all its content
     * 
     * @param string $dir directory path to remove
     * 
     * @return bool true on success
     */
    private function removeDirectory($dir)
    {
        $files = array_diff(scandir($dir), array('.', '..'));

        foreach ($files as $file) {
            (is_dir("$dir/$file") && !is_link("$dir/$file")) ? $this->removeDirectory("$dir/$file") : unlink("$dir/$file");
        }

        return rmdir($dir);
    }

    /**
     * Return installed patches version numbers
     * 
     * @return array sorted installed patch version numbers 
     */
    public function getInstalledPatches()
    {
        $installed_patches = array_values(array_diff(scandir($this->patch_dir), array('..', '.', '1.1.5-patcher.patch')));

        $installed_patches = array_map(function ($filename) {
            return basename($filename, '.patch');
        }, $installed_patches);
        usort($installed_patches, 'version_compare');

        return $installed_patches;
    }

    /**
     * Calls server and downloads patch information if there is any
     * 
     * @param string $api_user Omniva API user
     * @param string $email email from shop information
     * 
     * @return false|array array with zipfile and signature, false on failure
     */
    public function getAvailablePatches($api_user = '', $email = '')
    {
        $installed_patches = $this->getInstalledPatches();

        $ps_version = defined('_PS_VERSION_') ? _PS_VERSION_ : '';

        $current_version = $this->getCurrentVersion();

        $post_data = array(
            'api_user' => $api_user,
            'email' => $email,
            'ps_version' => $ps_version,
            'current_version' => $current_version,
            'installed_patches' => $installed_patches
        );

        $this->patchLog(json_encode($post_data));

        $headers = array(
            'Content-Type: application/json',
            "Cache-Control: no-cache",
            "Pragma: no-cache",
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, self::API_URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        $response = curl_exec($ch);

        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($http_code == 200) {
            try {
                $json = json_decode($response, true);
            } catch (\Throwable $e) {
                return false;
            } catch (\Exception $e) {
                return false;
            }

            return $json;
        }

        // 404 should mean - no patches

        return false;
    }

    /**
     * Verifys that response came from trusted source (using public key for signature validation).
     * 
     * @param array $response response sent by patch server
     * 
     * @return bool true if valid, false otherwise
     */
    public function verifyPatch($response)
    {
        if (!isset($response['file']) || !isset($response['signature'])) {
            return false;
        }

        $signature = base64_decode($response['signature']);

        $status = openssl_verify($response['file'], $signature, base64_decode($this->update_server_pk), 'sha256WithRSAEncryption');

        return (bool) $status;
    }

    /**
     * Saves and extracts zip file data
     * 
     * @param mixed $zip_data ZIP file data
     * @param mixed $to_dir where to save and extract zip file
     * 
     * @return bool true on success
     */
    private function extractZip($zip_data, $to_dir)
    {
        if (!file_exists($to_dir)) {
            mkdir($to_dir);
        }

        $zip_file = $to_dir . 'patch.zip';
        file_put_contents($zip_file, $zip_data);

        $zip = new ZipArchive();
        if ($zip->open($zip_file) === true && $zip->extractTo($to_dir) && $zip->close()) {
            return true;
        }

        return false;
    }

    /**
     * Extracts current module version from main module file
     * 
     * @return string module version
     */
    private function getCurrentVersion()
    {
        $file_iterator = new SplFileObject($this->current_dir . $this->version_file);

        foreach ($file_iterator as $line) {
            preg_match('/\$this->version ?= ?[\'"]([0-9]*.[0-9]*.[0-9]*)[\'"]/', $line, $result);
            if ($result) {
                $file_iterator = null;
                return $result[1];
            }
        }

        $file_iterator = null;
        return '0.0.0';
    }

    /**
     * Sets patch version
     * 
     * @param string $version patch version
     */
    public function setPatchVersion($version)
    {
        $this->patch_file = $version . '.patch';
    }

    /**
     * Sets main directory to base patching from
     * 
     * @param string $path usualy path to main module directory
     */
    public function setCurrentDirectory($path)
    {
        $this->current_dir = rtrim($path, '/') . '/';

        $this->checkForPatchDir();
        $this->updateTempPatchDir();
    }

    /**
     * Updates where temporary patch directory will be created (based on current directory).
     */
    private function updateTempPatchDir()
    {
        $this->temp_patch_dir = $this->current_dir . self::TEMP_PATCH_DIR;
    }

    /**
     * Runs job instructions
     * 
     * @param array $jobs instruction sets
     */
    public function work($jobs)
    {
        foreach ($jobs as $job) {
            switch ($job['type']) {
                case self::TYPE_ADD:
                    $this->patchLog('Edit file instruction - ADD');
                    $this->editFile($job);
                    break;

                case self::TYPE_ADD_BEFORE:
                    $this->patchLog('Edit file instruction - ADD_BEFORE');
                    $this->editFile($job);
                    break;

                case self::TYPE_REPLACE:
                    $this->patchLog('Edit file instruction - REPLACE');
                    $this->editFile($job);
                    break;

                case self::TYPE_CREATE_FILE:
                    $this->patchLog('Create file instruction - CREATE_FILE');
                    $this->createFile($job);
                    break;
            }
        }
    }

    /**
     * Checks and creates `patches` directory if it does not exist
     */
    private function checkForPatchDir()
    {
        $this->patch_dir = $this->current_dir . 'patches/';

        if (!file_exists($this->patch_dir)) {
            mkdir($this->patch_dir);
        }
    }

    /**
     * Logs patch job instructions
     * 
     * @param string $msg instruction info
     */
    private function log($msg)
    {
        $msg = PHP_EOL . '========[ ' . date('Y-m-d H:i:s') . ' ]========' . PHP_EOL . $msg . PHP_EOL . str_repeat('=', 39) . PHP_EOL;
        file_put_contents($this->patch_dir . $this->patch_file, $msg, FILE_APPEND);
    }

    /**
     * Checks if string to be added allready exist
     * 
     * @param string $string string to search in
     * @param string $update what string to search for
     * 
     * @return bool true if found, false otherwise
     */
    private function hasFix($string, $update)
    {
        return !(strpos($string, trim($update)) === false);
    }

    /**
     * Checks if parameter needs to be base64 decoded and return it. Determined by existing param_name_base64 and if its set to true (eg. uptade_base64)
     * 
     * @param array $job instruction information
     * @param string $param parameter name in instruction information array
     * 
     * @return string base64 decoded string or untouched string
     */
    private function decodedParam($job, $param)
    {
        if (isset($job[$param . '_base64']) && $job[$param . '_base64']) {
            return base64_decode($job[$param]);
        }

        return $job[$param];
    }

    /**
     * Edit file according to job instruction information. After applying changes file is first saved as .tmp and renamed to original.
     * 
     * @param array $job instructions
     */
    private function editFile($job)
    {
        $target = $this->current_dir . $job['target'];
        $search = $this->decodedParam($job, 'search');
        $update = $this->decodedParam($job, 'update');

        $blob = file_get_contents($target);

        if ($this->hasFix($blob, $update)) {
            $this->log('[ HAS FIX ] ' . $target . PHP_EOL . $update);
            return true;
        }

        if ($job['type'] == self::TYPE_ADD) {
            $blob = $this->insertAfterString($blob, $search,  $update);
            $this->log('[ ADD ] ' . $target . PHP_EOL . $update);
        }

        if ($job['type'] == self::TYPE_ADD_BEFORE) {
            $blob = $this->insertBeforeString($blob, $search,  $update);
            $this->log('[ ADD_BEFORE ] ' . $target . PHP_EOL . $update);
        }

        if ($job['type'] == self::TYPE_REPLACE) {
            $blob = $this->replaceString($blob, $search,  $update);
            $this->log('[ REPLACE ] ' . $target . PHP_EOL . $update);
        }

        file_put_contents($target . '.tmp', $blob);

        if (!rename($target . '.tmp', $target)) {
            $this->log('[ FAILED ] Failed to replace original file with edited - ' . $target);
        }
    }

    /**
     * Insert string after searched string
     * 
     * @param string $initial_string string to search in
     * @param string $search_string what string to search for
     * @param string $add_string string to be inserted
     * 
     * @return string result string
     */
    private function insertAfterString($initial_string, $search_string, $add_string)
    {
        $position = strpos($initial_string, $search_string);
        if ($position !== false) {
            return substr_replace($initial_string, $add_string, $position + strlen($search_string), 0);
        }
        $this->log('[ FAILED ] Failed to locate search string');
        return $initial_string;
    }

    /**
     * Insert string before searched string
     * 
     * @param string $initial_string string to search in
     * @param string $search_string what string to search for
     * @param string $add_string string to be inserted
     * 
     * @return string result string
     */
    private function insertBeforeString($initial_string, $search_string, $add_string)
    {
        $position = strpos($initial_string, $search_string);
        if ($position !== false) {
            return substr_replace($initial_string, $add_string, $position, 0);
        }
        $this->log('[ FAILED ] Failed to locate search string');
        return $initial_string;
    }

    /**
     * Replace searched string
     * 
     * @param string $initial_string string to search in
     * @param string $search_string what string to search for
     * @param string $add_string string to be replaced with
     * 
     * @return string result string
     */
    private function replaceString($initial_string, $search_string, $add_string)
    {
        return str_replace($search_string, $add_string, $initial_string);
    }

    /**
     * Creates file according to supplied instructions
     * 
     * @param array $job instructions array
     * 
     * @return bool true on success, false otherwise
     */
    private function createFile($job)
    {
        $target = $this->current_dir . $job['target'];
        $content = base64_decode($job['content']);

        if (file_exists($target)) {
            $this->log('[ EXISTS ] ' . $target);
            return true;
        }

        try {
            if (file_put_contents($target, $content)) {
                $this->log('[ CREATED ] ' . $target);
                return true;
            }
        } catch (\Throwable $e) {
            $this->log('[ FAILED ] Failed to write into ' . $target . ' | Error: ' .
                $e->getMessage() . "\n" .
                $e->getTraceAsString());
        } catch (\Exception $e) {
            $this->log('[ FAILED ] Failed to write into ' . $target . ' | Error: ' .
                $e->getMessage() . "\n" .
                $e->getTraceAsString());
        }

        $this->log('[ FAILED ] Failed to write into ' . $target . ' due to unknown reason.');
        return false;
    }

    /**
     * Installs patcher code into main module file
     */
    public function install()
    {
        $this->patchLog('----- Installing OmnivaPatcher -----');
        $this->setPatchVersion('1.1.5-patcher');
        $this->work([
            [
                'type'      => OmnivaPatcher::TYPE_ADD_BEFORE,
                'target'    => 'omnivaltshipping.php',
                'search'    => '\'OrderInfo\' => \'classes/OrderInfo.php\'',
                'update'    => '
                    ICAgICdPbW5pdmFQYXRjaGVyJyA9PiAnb21uaXZhcGF0Y2hlci5waHAnLAogICAg
                ',
                'update_base64'    => true,
            ],
            [
                'type'      => OmnivaPatcher::TYPE_ADD_BEFORE,
                'target'    => 'omnivaltshipping.php',
                'search'    => '$output = null;',
                'update'    => '
                    CiAgICBpZiAoVG9vbHM6OmlzU3VibWl0KCdwYXRjaCcgLiAkdGhpcy0+bmFtZSkpIHsKICAgICAg
                    c2VsZjo6Y2hlY2tGb3JDbGFzcygnT21uaXZhUGF0Y2hlcicpOwoKICAgICAgJHBhdGNoZXIgPSBu
                    ZXcgT21uaXZhUGF0Y2hlcigpOwogICAgICAkdGhpcy0+cnVuUGF0Y2hlcigkcGF0Y2hlcik7CiAg
                    ICB9CgogICAg
                ',
                'update_base64'    => true,
            ],
            [
                'type'      => OmnivaPatcher::TYPE_ADD_BEFORE,
                'target'    => 'omnivaltshipping.php',
                'search'    => '$helper = new HelperForm();',
                'update'    => '
                    ICAgIHNlbGY6OmNoZWNrRm9yQ2xhc3MoJ09tbml2YVBhdGNoZXInKTsKCiAgICAkcGF0Y2hlciA9
                    IG5ldyBPbW5pdmFQYXRjaGVyKCk7CgogICAgJGluc3RhbGxlZF9wYXRjaGVzID0gJHBhdGNoZXIt
                    PmdldEluc3RhbGxlZFBhdGNoZXMoKTsKICAgICRsYXRlc3RfcGF0Y2ggPSAnT21uaXZhUGF0Y2hl
                    ciBJbnN0YWxsZWQnOwogICAgaWYgKCRpbnN0YWxsZWRfcGF0Y2hlcykgewogICAgICAkbGF0ZXN0
                    X3BhdGNoID0gJGluc3RhbGxlZF9wYXRjaGVzW2NvdW50KCRpbnN0YWxsZWRfcGF0Y2hlcykgLSAx
                    XTsKICAgIH0KCiAgICAkcGF0Y2hfbGluayA9IEFkbWluQ29udHJvbGxlcjo6JGN1cnJlbnRJbmRl
                    eCAuICcmY29uZmlndXJlPScgLiAkdGhpcy0+bmFtZSAuICcmcGF0Y2gnIC4gJHRoaXMtPm5hbWUg
                    LiAnJnRva2VuPScgLiBUb29sczo6Z2V0QWRtaW5Ub2tlbkxpdGUoJ0FkbWluTW9kdWxlcycpOwoK
                    ICAgICRmaWVsZHNfZm9ybVswXVsnZm9ybSddWydpbnB1dCddW10gPSBhcnJheSgKICAgICAgJ3R5
                    cGUnID0+ICdodG1sJywKICAgICAgJ2xhYmVsJyA9PiAnUGF0Y2g6JywKICAgICAgJ25hbWUnID0+
                    ICdwYXRjaGVyX2luZm8nLAogICAgICAnaHRtbF9jb250ZW50JyA9PiAnPGxhYmVsIGNsYXNzPSJj
                    b250cm9sLWxhYmVsIj48Yj4nIC4gJGxhdGVzdF9wYXRjaCAuICc8L2I+PC9sYWJlbD48YnI+PGEg
                    Y2xhc3M9ImJ0biBidG4tZGVmYXVsdCIgaHJlZj0iJyAuICRwYXRjaF9saW5rIC4gJyI+Q2hlY2sg
                    JiBJbnN0YWxsIFBhdGNoZXM8L2E+JywKICAgICk7CgogICAg
                ',
                'update_base64'    => true,
            ],
            [
                'type'      => OmnivaPatcher::TYPE_ADD_BEFORE,
                'target'    => 'omnivaltshipping.php',
                'search'    => 'private function getTerminalsOptions',
                'update'    => '
                    CiAgcHJpdmF0ZSBmdW5jdGlvbiBydW5QYXRjaGVyKE9tbml2YVBhdGNoZXIgJHBhdGNoZXJJbnN0
                    YW5jZSkKICB7CiAgICAkbGFzdF9jaGVjayA9IENvbmZpZ3VyYXRpb246OmdldCgnb21uaXZhbHRf
                    cGF0Y2hlcl91cGRhdGUnKTsKCiAgICAkcGF0Y2hlckluc3RhbmNlLT5zdGFydFVwZGF0ZShDb25m
                    aWd1cmF0aW9uOjpnZXQoJ29tbml2YWx0X2FwaV91c2VyJyksIENvbmZpZ3VyYXRpb246OmdldCgn
                    UFNfU0hPUF9FTUFJTCcpKTsKCiAgICBDb25maWd1cmF0aW9uOjp1cGRhdGVWYWx1ZSgnb21uaXZh
                    bHRfcGF0Y2hlcl91cGRhdGUnLCB0aW1lKCkpOwogIH0KCiAg
                ',
                'update_base64'    => true,
            ],
        ]);
        file_put_contents($this->current_dir . 'patcher_installed', date('Y-m-d H:i:s'));
        $this->patchLog('----- Finished installing OmnivaPatcher -----');
    }

    /**
     * Set where patcher stores its log file
     * 
     * @param string $path where to store log file
     */
    public function setLogsPath($path)
    {
        $this->logs_path = rtrim($path, '/') . '/';
    }

    /**
     * Writes patcher log
     * 
     * @param string $msg message to write
     */
    public function patchLog($msg)
    {
        file_put_contents($this->logs_path . $this->patch_log_file, date('Y-m-h H:i:s') . " - " . $msg . PHP_EOL, FILE_APPEND);
    }
}

// Direct access for a first time install
if (!defined('_PS_VERSION_') && !file_exists('patcher_installed')) {
    $patcher = new OmnivaPatcher();
    $patcher->install();
    die('OmnivaPatcher installed. Please open module settings and press \'Check & Install Patches\' button. Have a nice day!');
}

if (!defined('_PS_VERSION_')) {
    http_response_code(403);
    die('Forbidden');
}
