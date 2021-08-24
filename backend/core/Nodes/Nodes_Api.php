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
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function getActiveSubscriptions(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
    }

    public function getActiveRequests(){
      $this->websocket_api = new WebSocket_Api();
      return $this->websocket_api->sendToWSS("getActiveRequests")["getActiveRequests"];
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
        return $this->logging_api->getErrormessage("001", $e);
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
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function acceptIPChange(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET ipaddress = changedIP, changedIP = ? WHERE id = ?", array("", $data["nodeid"]));

          $querydata = [];
          $querydata["data"]["acceptIPChange"] = array(
            "status" => 0,
            "message" => "IP Change saved accepted.",
            "data"=> array()
          );

          $querydata["nodeinfo"]["authhash"] = $data["authhash"];
          if(!is_null($server)){
            $server->messageSpecificNode($querydata);
          }else{
            $this->websocket_api = new WebSocket_Api();
            $activeSubscriptions = $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
          }

          return array("status" => 0, "message" => "IP Change saved for node {$data["nodeid"]}.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    public function acceptNodeRequest(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data) && array_key_exists("nodetypes", $data)){
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
            $nodeid = $data["nodeid"];
            $authhash = $data["authhash"];

            $sql = $this->db_api->execute("UPDATE nodes SET conallow = 1, authtype = ? WHERE id = ?", array($authtype, $nodeid));
            $sql = $this->db_api->execute("DELETE FROM nodetype WHERE nodeid = ?", array($nodeid));

            foreach(explode(",", $data["nodetypes"]) AS $arrkey => $nodetype){
              $sql = $this->db_api->execute("INSERT INTO nodetype (id, nodeid, code) VALUES(NULL, ?, ?)", array($nodeid, $nodetype));
            }

            $returnmessage = array("status" => 0, "message" => "Successfully allowed connection for node with ID {$data["nodeid"]}.");
            $querydata = [];
            $querydata["data"]["acceptNodeRequest"] = $returnmessage;
            $querydata["nodeinfo"]["authhash"] = $data["authhash"];

            if(!is_null($server)){
              $server->messageSpecificNode($querydata);
            }else{
              $this->websocket_api = new WebSocket_Api();
              $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
            }

            return $returnmessage;
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    public function declineNodeRequest(array $data, array $loginData = NULL, $server = NULL){
      if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET conallow = 0 WHERE id = ?", array($data["nodeid"]));

          $returnmessage = array("status" => 0, "message" => "Successfully declined connection for id {$data["nodeid"]}.");
          $querydata = [];
          $querydata["data"]["declineNodeRequest"] = $returnmessage;
          $querydata["nodeinfo"]["authhash"] = $data["authhash"];

          if(!is_null($server)){
            print_r($server->messageSpecificNode($querydata));
          }else{
            $this->websocket_api = new WebSocket_Api();
            print_r($this->websocket_api->sendToWSS("messageSpecificNode", $querydata));
          }

          return $returnmessage;
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
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
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function updateScriptVersion(array $data, array $loginData = NULL){
      if(array_key_exists("authhash", $loginData) && array_key_exists("scriptversion", $data) && array_key_exists("chia", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET scriptversion = ?, chiaversion = ?, chiapath = ? WHERE nodeauthhash = ?", array($data["scriptversion"], $data["chia"]["version"], $data["chia"]["path"], $this->encryptAuthhash($loginData["authhash"])));

          return array("status" =>0, "message" => "Successfully updated version.");
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function updateSystemInfo(array $data, array $loginData = NULL){
      if(array_key_exists("system", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("INSERT INTO chia_infra_sysinfo (id, nodeid, load_1min, load_5min, load_15min, filesystem, memory_total, memory_free, memory_buffers, memory_cached, swap_total, swap_free, cpu_count, cpu_cores, cpu_model) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
          array($nodeid, $data["system"]["load"]["1min"], $data["system"]["load"]["5min"], $data["system"]["load"]["15min"],
                json_encode($data["system"]["filesystem"]),
                $data["system"]["memory"]["total"], $data["system"]["memory"]["free"], $data["system"]["memory"]["buffers"], $data["system"]["memory"]["cached"],
                $data["system"]["swap"]["total"], $data["system"]["swap"]["free"],
                $data["system"]["cpu"]["count"], $data["system"]["cpu"]["cores"], $data["system"]["cpu"]["model"]
          ));

          return array("status" => 0, "message" => "Successfully updated system information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }
    }

    public function getSystemInfo(array $data, array $loginData = NULL){
      if(array_key_exists("nodeid", $data)){
        try{
          $sql = $this->db_api->execute("SELECT nodeid, timestamp, load_1min, load_5min, load_15min, filesystem, memory_total, memory_free, memory_buffers, memory_cached, swap_total, swap_free, cpu_count, cpu_cores, cpu_model
                                         FROM nodes_systeminfo
                                         WHERE nodeid = ?
                                         ORDER BY timestamp DESC
                                         LIMIT 1", array($data["nodeid"]));

          return array("status" =>0, "message" => "Successfully loaded latest system information for node {$data["nodeid"]}.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
        }catch(Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    public function nodeUpdateStatus(array $data, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        return array("status" => 0, "message" => "Successfully queried node update status", "data" => array("nodeid" => $nodeid, "status" => $data));
      }catch(Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    public function getUpdateChannels(array $data, array $loginData = NULL){
      $channels = file_get_contents(__DIR__ . "/../../../nodepackages/versions.json");
      return array("status" =>0, "message" => "Successfully loaded all updatechannels.", "data" => json_decode($channels, true));
    }

    public function queryNodesServicesStatus(array $data = [], array $loginData = NULL, $server = NULL, $ignoreDate = false){
      $allNodeStatus = $this->getNodeStatus($data);
      if($allNodeStatus["status"] > 0) return $allNodeStatus;

      $allNodeStatus = $allNodeStatus["data"];
      if(!is_null($server)){
        $activeSubscriptions = $server->getActiveSubscriptions($loginData);
      }else{
        $this->websocket_api = new WebSocket_Api();
        $activeSubscriptions = $this->websocket_api->sendToWSS("getActiveSubscriptions")["getActiveSubscriptions"];
      }

      $foundnode = [];
      $datatosave = [];

      $now = new \DateTime();
      if($activeSubscriptions["status"] == 0 && array_key_exists("data", $activeSubscriptions)){
        foreach($activeSubscriptions["data"] AS $nodetype => $allnodesconnected){
          if($nodetype != "webClient" && $nodetype != "backendClient"){
            foreach($allnodesconnected AS $connid => $nodedata){
              if(array_key_exists($nodedata["nodeid"], $allNodeStatus) && !in_array($nodedata["nodeid"], $foundnode)){
                array_push($foundnode, $nodedata["nodeid"]);
              }
            }
          }
        }
      }else{
        return $activeSubscriptions;
      }

      //Onlinestatus: 0 = Disconnected, 1 = Connected, 2 = Querying
      foreach($allNodeStatus AS $arrkey => $nodedata){
        if(in_array($nodedata["nodeid"], $foundnode)){
          $onlinestatus = 1;
          $walletstatus = 2;
          $farmerstatus = 2;
          $harvesterstatus = 2;
        }else{
          $onlinestatus = 0;
          $walletstatus = 0;
          $farmerstatus = 0;
          $harvesterstatus = 0;
        }

        try{
          if(array_key_exists($nodedata["nodeid"], $allNodeStatus)){
            $querytime = new \DateTime($allNodeStatus[$nodedata["nodeid"]]["querytime"]);
            $querytime->modify('+30 seconds');

            if($now > $querytime || $ignoreDate){
              $this->queryDataFromNode($nodedata, $server);

              $sql = $this->db_api->execute("UPDATE nodes_status SET onlinestatus = ?, walletstatus = ?, farmerstatus = ?, harvesterstatus = ?, querytime = ? WHERE nodeid = ?",
                                            array($onlinestatus, $walletstatus, $farmerstatus, $harvesterstatus, $now->format("Y-m-d H:i:s"), $nodedata["nodeid"]));
            }
          }else{
            $this->queryDataFromNode($nodedata, $server);

            $sql = $this->db_api->execute("INSERT INTO nodes_status (id, nodeid, onlinestatus, walletstatus, farmerstatus, harvesterstatus, querytime)
                                            VALUES(NULL, ?, ?, ?, ?, ?, ?)",
                                            array($nodedata["nodeid"], $onlinestatus, $walletstatus, $farmerstatus, $harvesterstatus, $now->format("Y-m-d H:i:s")));
          }
        }catch(Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }

      return $this->getNodeStatus($data);
    }

    public function setNodeServiceStats(array $data = [], array $loginData = NULL, $server = NULL){
      if(array_key_exists("type", $data) && array_key_exists("stat", $data) && array_key_exists("nodeid", $data)){
        if(is_numeric($data["stat"]) && $data["type"] >= 3 && $data["type"] <= 5){
          if($data["type"] == 3){
            $updateCol = "farmerstatus";
          }else if($data["type"] == 4){
            $updateCol = "harvesterstatus";
          }else if($data["type"] == 5){
            $updateCol = "walletstatus";
          }

          $sql = $this->db_api->execute("UPDATE nodes_status SET $updateCol = ? WHERE nodeid = ?", array($data["stat"], $data["nodeid"]));

          return array("status" => 0, "message" => "Successfully updated $updateCol for node {$data["nodeid"]}.");
        }else{
          //TODO Implement correct status code
          return array("status" => 1, "message" => "Stat {$data["stat"]} no known.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    private function getNodeStatus(array $nodedata = []){
      if(count($nodedata) == 0){
        $sql = $this->db_api->execute("SELECT n.id AS nodeid, n.nodeauthhash FROM nodetype nt JOIN nodes n ON n.id = nt.nodeid WHERE code >= 3 AND code <= 5 GROUP by n.id", array());
        $nodedata = $sql->fetchAll(\PDO::FETCH_ASSOC);
      }

      $nodeids = [];
      $or_statement = "";
      for($i = 0; $i < count($nodedata); $i++){
        if(array_key_exists($i+1, $nodedata)){
          $or_statement .= "nodeid = ? OR ";
        }else{
          $or_statement .= "nodeid = ?";
        }
        array_push($nodeids, $nodedata[$i]["nodeid"]);
      }

      try{
        $sql = $this->db_api->execute("SELECT nodeid, onlinestatus, walletstatus, farmerstatus, harvesterstatus, querytime FROM nodes_status WHERE $or_statement", $nodeids);
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);


        for($i = 0; $i < count($sqreturn); $i++){
          $sqreturn[$sqreturn[$i]["nodeid"]] = $sqreturn[$i];
          unset($sqreturn[$i]);
        }

        return array("status" => 0, "message" => "Successfully loaded requested node status.", "data" => $sqreturn);
      }catch(Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    private function queryDataFromNode(array $nodedata, $server = NULL){
      $sql = $this->db_api->execute("SELECT na.description, n.nodeauthhash FROM nodetype nt JOIN nodetypes_avail na ON na.code = nt.code JOIN nodes n ON n.id = nt.nodeid WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = ?", array($nodedata["nodeid"]));

      foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $infos){
        $querydata = [];
        $querydata["data"]["query" . $infos["description"] . "Status"] = array(
          "status" => 0,
          "message" => "Query " . $infos["description"] . " running status.",
          "data"=> array()
        );
        $querydata["nodeinfo"]["authhash"] = $this->decryptAuthhash($infos["nodeauthhash"]);
        if(!is_null($server)){
          $server->messageSpecificNode($querydata);
        }else{
          $this->websocket_api = new WebSocket_Api();
          $activeSubscriptions = $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
        }
      }
    }

    private function encryptAuthhash(string $encryptedauthhash){
      return openssl_encrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    public function decryptAuthhash(string $encryptedauthhash){
      return openssl_decrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>
