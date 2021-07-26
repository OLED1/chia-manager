<?php
  namespace ChiaMgmt\Nodes;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;

  class Nodes_Api{
    private $db_api, $logging_api, $websocket_api, $ciphering, $iv_length, $options, $encryption_iv, $ini;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function getActiveSubscriptions(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
    }

    public function getActiveRequests(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveRequests")["getActiveRequests"];;
    }

    public function getConfiguredNodes(){
      $returndata = array();

      try{
        $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.nodeauthhash, n.authtype, n.conallow, n.hostname, n.scriptversion, n.ipaddress, n.changeable, n.changedIP
                                       FROM nodes n
                                       JOIN nodetype nt ON nt.nodeid = n.id
                                       JOIN nodetypes_avail nta ON nta.code = nt.code
                                       GROUP BY n.id", array());

        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
        $returnarray = array();

        foreach ($sqdata as $arrkey => $conninfo) {
          $returnarray[$conninfo["id"]] = $conninfo;
          $returnarray[$conninfo["id"]]["nodeauthhash"] = $this->decryptAuthhash($conninfo["nodeauthhash"]);
        }

        return array("status" => 0, "message" => "Sucessfully loaded all client data.", "data" => $returnarray);
      }
      catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function getNodeTypes(){
      try{
        $sql = $this->db_api->execute("SELECT id, description, code, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1", array());

        $returndata = array();
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $info){
          $returndata["by-id"][$info["id"]] = $info;
          $returndata["by-desc"][$info["description"]] = $info;
        }

        return array("status" => 0, "message" => "Sucessfully loaded all available nodetypes.", "data" => $returndata);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function acceptIPChange(array $data){
      if(array_key_exists("nodeid", $data)){
        $nodeid = $data["nodeid"];
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET ipaddress = changedIP, changedIP = ? WHERE id = ?", array("", $nodeid));

          return array("status" => 0, "message" => "IP Change saved.");
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }
    }

    public function acceptNodeRequest(array $data){
      if(array_key_exists("id", $data) && array_key_exists("nodetypes", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1 AND id IN ({$data["nodetypes"]})", array());
          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $types = [];
          $allowed_authtype = [];
          foreach ($sqreturn as $arrkey => $nodetypes) {
            $types[$nodetypes["nodetype"]] = 1;
            $allowed_authtype[$nodetypes["allowed_authtype"]] = 1;
          }

          if(count($types) == 1 && count($allowed_authtype) == 1){
            $authtype = $sqreturn[0]["allowed_authtype"];
            $nodeid = $data["id"];

            $sql = $this->db_api->execute("UPDATE nodes SET conallow = 1, authtype = ? WHERE id = ?", array($authtype, $nodeid));
            $sql = $this->db_api->execute("DELETE FROM nodetype WHERE nodeid = ?", array($nodeid));

            foreach(explode(",", $data["nodetypes"]) AS $arrkey => $nodetype){
              $sql = $this->db_api->execute("INSERT INTO nodetype (id, nodeid, code) VALUES(NULL, ?, ?)", array($nodeid, $nodetype));
            }

            return array("status" => 0, "message" => "Successfully allowed connection for node with ID {$data["id"]}.");
          }else{
            return array("status" => 1, "message" => "The configured nodetypes are not compatible to each other.");
          }
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function declineNodeRequest(array $data){
      if(array_key_exists("id", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET conallow = 0 WHERE id = ?", array($data["id"]));

          return array("status" =>0, "message" => "Successfully declined connection for id {$data["id"]}.");
        }catch(Exception $e){
          print_r($e);
          return array("status" =>1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function loginStatus(array $data, array $loginData = NULL){
      if(array_key_exists("authhash", $loginData)){
        try{
          $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.hostname
          FROM nodes n
          JOIN nodetype nt ON nt.nodeid = n.id
          JOIN nodetypes_avail nta ON nta.code = nt.code
          WHERE n.nodeauthhash = ?
          GROUP BY n.id", array($this->encryptAuthhash($loginData["authhash"])));
          $sqldata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];

          return array("status" => 0, "method" => "loginStatus", "message" => "This node is logged in.", "data" => $sqldata);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function updateScriptVersion(array $data, array $loginData = NULL){
      if(array_key_exists("authhash", $loginData) && array_key_exists("scriptversion", $data) && array_key_exists("chia", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET scriptversion = ?, chiaversion = ?, chiapath = ? WHERE nodeauthhash = ?", array($data["scriptversion"], $data["chia"]["version"], $data["chia"]["path"], $this->encryptAuthhash($loginData["authhash"])));

          return array("status" =>0, "message" => "Successfully updated version.");
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function updateSystemInfo(array $data, array $loginData = NULL){
      if(array_key_exists("system", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("INSERT INTO nodes_systeminfo (id, nodeid, load_1min, load_5min, load_15min, filesystem, memory_total, memory_free, memory_buffers, memory_cached, memory_sreclaimable, memory_shmem, swap_total, swap_free, cpu_count, cpu_cores, cpu_model) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
          array($nodeid, $data["system"]["load"]["1min"], $data["system"]["load"]["5min"], $data["system"]["load"]["15min"],
                json_encode($data["system"]["filesystem"]),
                $data["system"]["memory"]["total"], $data["system"]["memory"]["free"], $data["system"]["memory"]["buffers"], $data["system"]["memory"]["cached"], $data["system"]["memory"]["sreclaimable"], $data["system"]["memory"]["shmem"],
                $data["system"]["swap"]["total"], $data["system"]["swap"]["free"],
                $data["system"]["cpu"]["count"], $data["system"]["cpu"]["cores"], $data["system"]["cpu"]["model"]
          ));

          return array("status" =>0, "message" => "Successfully updated system information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }
    }

    public function getSystemInfo(array $data, array $loginData = NULL){
      if(array_key_exists("nodeid", $data)){
        try{
          $sql = $this->db_api->execute("SELECT nodeid, timestamp, load_1min, load_5min, load_15min, filesystem, memory_total, memory_free, memory_buffers, memory_cached, memory_sreclaimable, memory_shmem, swap_total, swap_free, cpu_count, cpu_cores, cpu_model
                                         FROM nodes_systeminfo
                                         WHERE nodeid = ?
                                         ORDER BY timestamp DESC
                                         LIMIT 1", array($data["nodeid"]));

          return array("status" =>0, "message" => "Successfully loaded latest system information for node {$data["nodeid"]}.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    public function nodeUpdateStatus(array $data, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        return array("status" => 0, "message" => "Successfully queried node update status", "data" => array("nodeid" => $nodeid, "status" => $data));
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function getUpdateChannels(array $data, array $loginData = NULL){
      $channels = file_get_contents(__DIR__ . "/../../../nodepackages/versions.json");
      return array("status" =>0, "message" => "Successfully loaded all updatechannels.", "data" => json_decode($channels, true));
    }

    private function encryptAuthhash(string $encryptedauthhash){
      return openssl_encrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    public function decryptAuthhash(string $encryptedauthhash){
      return openssl_decrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>
