<?php
  namespace ChiaMgmt\System_Update;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The System_Update_Api class handles the webgui update tasks.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class System_Update_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
    /**
     * Variable for handling and detecting previous errors in update process.
     * @var boolean
     */
    private $preverror;
    /**
     * The server configuration file.
     * @var array
     */
    private $ini;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->server = NULL;
      $this->preverror = false;
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
    }

    public function checkUpdateRoutine(){
      try{
        $sql = $this->db_api->execute("SELECT dbversion, userid_updating, lastsucupdate, maintenance_mode FROM system_infos", array());
        $returndata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];
        $returndata["db_update_needed"] = version_compare($returndata["dbversion"], $this->ini["versnummer"]);

        return array("status" => 0, "message" => "Successfully queried system update state.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function processUpdate(array $data, array $loginData = NULL, $server = NULL){
      $this->server = $server;
      $now = new \DateTime();
      $now = $now->format("Y-m-d H:i:s");

      $this->sendStatus(0, 0, 2, "Starting update");
      $this->enableUpdateMode($loginData["userid"]);
      $this->createBackupdirs("Creating backup directories", $now);
      if(!$this->preverror) $this->backupSystemData("Backing up system data", $now);
      if(!$this->preverror) $this->backupSystemDatabase("Backing up databasedata", $now);
      if(!$this->preverror) $this->downloadUpdateData("Downloading and installing update");

      if($this->preverror){
        return $this->logging_api->getErrormessage("001");
      }else{
        return array("status" => 0, "message" => "Update process success.");
      }
    }

    public function finishUpdate(array $data, array $loginData = NULL){
      $db_update_file = file_get_contents(__DIR__ . "/db_update.json");
      $db_update_json = json_decode($db_update_file, true);

      if(array_key_exists("table_structures", $db_update_json)){
        try{
          $db_system = $this->checkUpdateRoutine();

          if($db_system["data"]["db_update_needed"] < 0){
            foreach($db_update_json["table_structures"] AS $tablename => $tablechecks){
              $sql = $this->db_api->execute("SHOW TABLES LIKE '{$tablename}'", array());

              if(count($sql->fetchAll(\PDO::FETCH_ASSOC)) > 0){
                foreach($tablechecks["existing"] AS $columnname => $columndata){
                  $sql = $this->db_api->execute("SHOW COLUMNS FROM {$tablename} WHERE Field = ?", array($columnname));
                  if(count($sql->fetchAll(\PDO::FETCH_ASSOC)) == 0){
                    $this->db_api->execute("ALTER TABLE {$tablename} ADD {$columnname} {$columndata}", array());
                  }
                }
              }else{
                foreach($tablechecks["notexisting"] AS $arrkey => $statement){
                  $this->db_api->execute("{$statement}", array());
                }
              }
            }
          }

          $now = new \DateTime("now");
          $sql = $this->db_api->execute("UPDATE system_infos SET dbversion = ?, userid_updating = ?, lastsucupdate = ?, maintenance_mode = ?",
                                        array($this->ini["versnummer"], 0, $now->format("Y-m-d H:i:s"), 0));
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully finished update to version {$this->ini["versnummer"]}.");
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function disableMaintenanceMode(array $data, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("UPDATE system_infos SET maintenance_mode = ?",
                                      array(0));

        return array("status" => 0, "message" => "Successfully disabled maintenance mode.");
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    private function enableUpdateMode(int $userid){
      try{
        $sql = $this->db_api->execute("UPDATE system_infos SET userid_updating = ?, maintenance_mode = ?",
                                      array($userid, 1));
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    private function createBackupdirs(string $message, string $timestamp){
      $this->sendStatus(0, 1, 2, $message);
      $backupdir = "../../..{$this->ini["backup_root"]}";

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
      $source = "../../..{$this->ini["system_root"]}";
      $dest = "../../..{$this->ini["backup_root"]}/{$timestamp}/files/";
      $this->zipBackup($source, $dest, $timestamp);
      $this->sendStatus(0, 2, 0, $message);
    }

    private function backupSystemDatabase(string $message, string $timestamp){
      $this->sendStatus(0, 3, 2, $message);
      $targetdir = "../../..{$this->ini["backup_root"]}/{$timestamp}/db/mysq_backup.sql";
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

            $this->full_copy("{$tmpdir}/chia-web-gui-{$updatechannel}/", "../../..{$this->ini["system_root"]}/");
            $newversion = $version_file_data[$updatechannel][0]["version"];
            if($this->updateConfigFile($newversion)){
              $this->sendStatus(0, 4, 0, $message);
            }else{
              $this->sendStatus(0, 4, 1, "{$message}. Could not set new version. Please enter it manually: {$newversion}");
            }
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

    private function updateConfigFile($newversion){
      $config_file = __DIR__.'/../../config/config.ini.php';
      $config_data = parse_ini_file($config_file, true);
      print_r($config_data);
      $key = "application";
      $section = "versnummer";
      $value = $newversion;

      $config_data[$key][$section] = $value;
      $new_content = '';
      foreach ($config_data as $section => $section_content) {
          $section_content = array_map(function($value, $key) {
              return "$key='$value'";
          }, array_values($section_content), array_keys($section_content));
          $section_content = implode("\n", $section_content);
          $new_content .= "[$section]\n$section_content\n\n";
      }

      $new_content = ";<?php\n;die(); // For further security;\n;/*\n{$new_content};*/";
      file_put_contents($config_file, $new_content);

      $tempini = parse_ini_file($config_file);
      if(array_key_exists("versnummer", $tempini) && $tempini["versnummer"] == $newversion){
        return true;
      }else {
        return false;
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
