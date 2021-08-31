<?php
  namespace ChiaMgmt\System_Update;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\System\System_Api;

  class System_Update_Api{
    private $db_api, $ini, $ciphering, $iv_length, $options, $encryption_iv, $websocket_api;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
      $this->server = NULL;
      $this->preverror = false;
    }

    public function processUpdate(array $data, array $loginData = NULL, $server = NULL){
      $this->server = $server;
      $now = new \DateTime();
      $now = $now->format("Y-m-d H:i:s");

      $this->sendStatus(0, 0, 2, "Starting update");
      $this->createBackupdirs("Creating backup directories", $now);
      if(!$this->preverror) $this->backupSystemData("Backing up system data", $now);
      if(!$this->preverror) $this->backupSystemDatabase("Backing up databasedata", $now);
      if(!$this->preverror) $this->downloadUpdateData("Downloading and installing update");

      if($this->preverror){
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Update process failed.");
      }else{
        return array("status" => 0, "message" => "Update process success.");
      }
    }

    private function createBackupdirs(string $message, string $timestamp){
      $this->sendStatus(0, 1, 2, $message);
      $backupdir = "../../../..{$this->ini["backup_root"]}";

      if(!file_exists($backupdir)) {
        if(!mkdir($backupdir, 0777, true)){
          $this->sendStatus(0, 1, 1, "Error creating dir {$backupdir}.<br>Please create dir manually and set apache as owner.");
          $this->preverror = true;
          return;
        }
      }

      $thisbackupdir = "{$backupdir}/{$timestamp}";
      if(!file_exists($thisbackupdir)) {
        if(!mkdir($thisbackupdir, 0777, true)){
          $this->sendStatus(0, 1, 1, "Error creating dir {$thisbackupdir}");
          $this->preverror = true;
          return;
        }
      }

      $filesdir = "{$thisbackupdir}/files";
      if(!file_exists($filesdir)) {
        if(!mkdir($filesdir, 0777, true)){
          $this->sendStatus(0, 1, 1, "Error creating dir {$filesdir}");
          $this->preverror = true;
          return;
        }
      }

      $mysqldir = "{$thisbackupdir}/db";
      if(!file_exists($mysqldir)) {
        if(!mkdir($mysqldir, 0777, true)){
          $this->sendStatus(0, 1, 1, "Error creating dir {$mysqldir}");
          $this->preverror = true;
          return;
        }
      }

      $this->sendStatus(0, 1, 0, $message);
    }

    private function backupSystemData(string $message, string $timestamp){
      $this->sendStatus(0, 2, 2, $message);
      $source = "../../../..{$this->ini["system_root"]}";
      $dest = "../../../..{$this->ini["backup_root"]}/{$timestamp}/files/";
      $this->zipBackup($source, $dest, $timestamp);
      $this->sendStatus(0, 2, 0, $message);
    }

    private function backupSystemDatabase(string $message, string $timestamp){
      $this->sendStatus(0, 3, 2, $message);
      $targetdir = "../../../..{$this->ini["backup_root"]}/{$timestamp}/db/mysq_backup.sql";
      print_r($targetdir);

      exec("mysqldump --user={$this->ini["db_user"]} --password={$this->ini["db_password"]} --host={$this->ini["db_host"]} {$this->ini["db_name"]} --result-file='{$targetdir}' 2>&1", $output, $exitCode);
      print_r($output);
      echo "Exit Code: $exitCode\n";
      if($exitCode == 0){
        $this->sendStatus(0, 3, 0, $message);
      }else{
        $this->preverror = true;
        $this->sendStatus(0, 3, 1, "{$message}. Exit Code: {$exitCode}");
      }
    }

    private function zipBackup($source, $dest, $timestamp){
      $zip = new \ZipArchive();
      $zip->open("{$dest}/backup-{$timestamp}.zip", \ZipArchive::CREATE | \ZipArchive::OVERWRITE);

      /** @var \SplFileInfo[] $files */
      $files = new \RecursiveIteratorIterator(
          new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
          \RecursiveIteratorIterator::LEAVES_ONLY
      );

      foreach ($files as $name => $file){
          if(!$file->isDir() && !str_contains($file->getRealPath(), "backup")){
            $filePath = $file->getRealPath();
            $relativePath = substr($filePath, strlen($source) + 1);
            $zip->addFile($filePath, $relativePath);
          }
      }
      $zip->close();
    }

    private function downloadUpdateData($message){
      $this->sendStatus(0, 4, 2, $message);
      $updatepackagepath = "https://files.chiamgmt.edtmair.at/server/";
      $version_file_json = file_get_contents("{$updatepackagepath}/versions.json");
      $version_file_data = json_decode($version_file_json, true);

      $system_api = new System_Api();
      $updatechannel = $system_api->getSpecificSystemSetting("updatechannel");
      if(array_key_exists("updatechannel", $updatechannel["data"])){
        $updatechannel = $updatechannel["data"]["updatechannel"]["branch"]["value"];
      }else{ $updatechannel = "main"; }

      if(!is_null($version_file_data) && array_key_exists($updatechannel, $version_file_data)){
        print_r($version_file_data);

        $updateurl = $version_file_data[$updatechannel][0]["link"];
        $tmpdir = "/tmp";
        if(is_dir($tmpdir)){
          $packagepath = "{$updatepackagepath}{$updateurl}";
          $tmpfiledir = "{$tmpdir}/chiamgmt_update.zip";

          file_put_contents($tmpfiledir,file_get_contents($packagepath));

          $zip = new \ZipArchive;
          $res = $zip->open($tmpfiledir);
          if ($res === TRUE) {
            $zip->extractTo($tmpdir);
            $zip->close();

            $this->full_copy("{$tmpdir}/chia-web-gui-{$updatechannel}/", "../../../..{$this->ini["system_root"]}/");
            $this->sendStatus(0, 4, 0, $message);
          }else{
            $this->preverror = true;
            $this->sendStatus(0, 4, 1, "{$message}. Could not open {$tmpfiledir}.");
            return;
          }
        }else{
          $this->preverror = true;
          $this->sendStatus(0, 4, 1, "{$message}. Temporary dir /tmp not found.");
          return;
        }
      }else{
        $this->preverror = true;
        $this->sendStatus(0, 4, 1, $message);
      }
    }

    private function full_copy($source, $dest){
      if(!file_exists($dest)) mkdir($dest, 0755);
      foreach(
       $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
                        \RecursiveIteratorIterator::SELF_FIRST) as $item
      ){
        if(str_contains($iterator->getSubPathname(), "backup" )){
          continue;
        }else{
          if($item->isDir()){
            mkdir($dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }else{
            echo "Copying {$item} -> " . $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname() ."\n";
            copy($item, $dest . DIRECTORY_SEPARATOR . $iterator->getSubPathname());
          }
        }
      }
    }

    //processing-status: 0 Success, 1 Failed, 2 Processing
    private function sendStatus(int $status, int $step, int $processing_status, string $message){
      $now = new \DateTime("now");
      $time = $now->format("Y-m-d H:i:s");
      $this->server->messageFrontendClients(array("siteID" => 3), array("processingUpdate" => array("status" => $status, "step" => $step, "processing-status" => $processing_status, "message" => "{$message}: {$time}")));
    }
  }
?>
