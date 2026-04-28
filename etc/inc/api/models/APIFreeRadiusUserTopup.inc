<?php
//   Copyright 2021 Jared Hendrickson
//
//   Licensed under the Apache License, Version 2.0 (the "License");
//   you may not use this file except in compliance with the License.
//   You may obtain a copy of the License at
//
//       http://www.apache.org/licenses/LICENSE-2.0
//
//   Unless required by applicable law or agreed to in writing, software
//   distributed under the License is distributed on an "AS IS" BASIS,
//   WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
//   See the License for the specific language governing permissions and
//   limitations under the License.

require_once("api/framework/APIModel.inc");
require_once("api/framework/APIResponse.inc");

class TopUp extends APIModel {
    # Create our method constructor
    public function __construct() {
        parent::__construct();
        $this->privileges = ["page-all", "page-services-freeradius"];
    }
    public function action()
    {
        if (isset($this->initial_data['freeradius_username'])) {
            # Loop through each configured user and check if this user exists
            foreach ($this->config["installedpackages"]["freeradius"]["config"] as $item => $userentry) {
                if ($this->initial_data["freeradius_username"] === $userentry['varusersusername']) {
                    $this->validated_data["freeradius_username"] = $userentry['varusersusername'];
                    $this->performTopup($item);
                    $this->config["installedpackages"]["freeradius"]["config"][$item]["varusersmodified"]="update";
                    $this->write_config("User topped up");
                }
            }

        }
    }

    public function performTopup($index)
    {

        if (isset($this->initial_data['freeradius_lastbasedata'])) {
            $userVolume = (float)str_replace(',', '', (string)$this->getuservolume(
                $this->initial_data['freeradius_username'],
                $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange']
            ));

            $lastBaseData = (float)str_replace(',', '', (string)$this->initial_data['freeradius_lastbasedata']);

            $currentToppedupData = $userVolume + $lastBaseData*1048576;
        }
        $timerange = $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctetstimerange'];
        $username = $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersusername'];
        $filepathall = "/var/log/radacct/datacounter/$timerange/used-octets-$username";
        if (($use_file = fopen($filepathall, "w")) !== false) {
            fwrite($use_file, (string)(int)$currentToppedupData);
            fclose($use_file);
        }

        if(isset($this->initial_data['freeradius_maxtotaloctets'])){
            $this->config["installedpackages"]["freeradius"]["config"][$index]['varusersmaxtotaloctets']+=$this->initial_data['freeradius_maxtotaloctets'];
        }
    }

    private function getuservolume($safeUser, $safeRange): float
    {
        $dir = "/var/log/radacct/datacounter/{$safeRange}";
        $baseFile = "{$dir}/used-octets-{$safeUser}";
        $sessionPattern = "{$dir}/used-octets-{$safeUser}-*";
        $usagenum = 0;
        // 1) BASE exact file
        if (is_file($baseFile) && is_readable($baseFile)) {
            $line = trim((string)@file_get_contents($baseFile));

            if (preg_match('/^\d+$/', $line)) {
                $usagenum += (int)$line;
            }
        }
        // 2) SESSION files exact-prefix only
        $files = glob($sessionPattern, GLOB_NOSORT);
        if (is_array($files)) {
            foreach ($files as $filepath) {
                if (!is_file($filepath) || !is_readable($filepath)) {
                    continue;
                }
                $line = trim((string)@file_get_contents($filepath));
                if (preg_match('/^(\d+)/', $line, $m)) {
                    $usagenum += (int)$m[1];
                }
            }
        }
        return number_format($usagenum, 0, '.', '');
    }
}
