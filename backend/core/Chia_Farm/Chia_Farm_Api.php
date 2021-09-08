<?php
  namespace ChiaMgmt\Chia_Farm;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;

  class Chia_Farm_Api{
    private $db_api, $logging_api, $nodes_api;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->nodes_api = new Nodes_Api();
    }

    public function updateFarmData(array $data, array $loginData = NULL){
      if(array_key_exists("farm", $data) && array_key_exists("farming_status", $data["farm"])){
        try{
          $farmdata = $data["farm"];
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_farm WHERE nodeid = ?", array($nodeid));
          $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

          if(array_key_exists("total_chia_farmed", $farmdata)){
            $totalchiafarmed = $farmdata["total_chia_farmed"];
            $usertransactionfees = $farmdata["user_transaction_fees"];
            $blockrewards = $farmdata["block_rewards"];
            $lastheigthfarmed = $farmdata["last_height_farmed"];
          }else{
            $totalchiafarmed = 0;
            $usertransactionfees = 0;
            $blockrewards = 0;
            $lastheigthfarmed = 0;
          }

          if($count == 0){
            $sql = $this->db_api->execute("INSERT INTO chia_farm (id, nodeid, farming_status, total_chia_farmed, user_transaction_fees, block_rewards, last_height_farmed, plot_count, total_size_of_plots, estimated_network_space, expected_time_to_win) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            array($nodeid, $farmdata["farming_status"], $totalchiafarmed, $usertransactionfees, $blockrewards, $lastheigthfarmed, $farmdata["plot_count_for_all_harvesters"], $farmdata["total_size_of_plots"], $farmdata["estimated_network_space"], $farmdata["expected_time_to_win"]));
          }else{
            $sql = $this->db_api->execute("UPDATE chia_farm SET farming_status = ?, total_chia_farmed = ?, user_transaction_fees = ?, block_rewards = ?, last_height_farmed = ?, plot_count = ?, total_size_of_plots = ?, estimated_network_space = ?, expected_time_to_win = ? WHERE nodeid = ?",
            array($farmdata["farming_status"], $totalchiafarmed, $usertransactionfees, $blockrewards, $lastheigthfarmed, $farmdata["plot_count_for_all_harvesters"], $farmdata["total_size_of_plots"], $farmdata["estimated_network_space"], $farmdata["expected_time_to_win"], $nodeid));
          }

          $this->updateChallenges($data["farm"], $loginData);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully updated farm information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getFarmData($data, $loginData, $nodeid)["data"]]);
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    public function getFarmData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.farming_status, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_farm cf ON cf.nodeid = nt.nodeid
                                          WHERE nt.code = 3"
                                          , array());
        }else{
          $sql = $this->db_api->execute("SELECT nt.nodeid, cf.farming_status, n.hostname, n.nodeauthhash, cf.total_chia_farmed, cf.user_transaction_fees, cf.block_rewards, cf.last_height_farmed, cf.plot_count, cf.total_size_of_plots, cf.estimated_network_space, cf.expected_time_to_win, cf.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_farm cf ON cf.nodeid = nt.nodeid
                                          WHERE nt.code = 3 AND nt.nodeid = ?"
                                          , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $farminfo){
          $farminfo["nodeauthhash"] = $this->decryptAuthhash($farminfo["nodeauthhash"]);
          $returndata[$farminfo["nodeid"]] = $farminfo;
        }

        return array("status" =>0, "message" => "Successfully loaded chia farm information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function farmerStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 3, "stat" => ($data["status"] == 0 ? 0 : 1), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" => 0, "message" => "Successfully queried farmer status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function queryFarmData(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["queryFarmData"] = array(
        "status" => 0,
        "message" => "Query Farm data.",
        "data"=> array()
      );

      $callfunction = "messageAllNodes";
      if(array_key_exists("nodeinfo", $querydata) && array_key_exists("authhash", $querydata["nodeinfo"])){
        $querydata["nodeinfo"]["authhash"] = $data["authhash"];
        $callfunction = "messageSpecificNode";
      }

      if(!is_null($server)){
        return $server->$callfunction($querydata);
      }else{
        $this->websocket_api = new WebSocket_Api();
        return $this->websocket_api->sendToWSS($callfunction, $querydata);
      }
    }

    public function restartFarmerService(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["restartFarmerService"] = array(
        "status" => 0,
        "message" => "Restart farmer service.",
        "data"=> array()
      );
      $querydata["nodeinfo"]["authhash"] = $data["authhash"];

      if(!is_null($server)){
        return $server->messageSpecificNode($querydata);
      }else{
        $this->websocket_api = new WebSocket_Api();
        return $this->websocket_api->sendToWSS("messageSpecificNode", $querydata);
      }
    }

    public function farmerServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried farmer service restart for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function updateChallenges(array $data = NULL, array $loginData = NULL){
      if(array_key_exists("challenges", $data)){
        try{
          $now = new \DateTime("now");

          $sql = $this->db_api->execute("DELETE FROM chia_farm_challenges", array());

          foreach ($data["challenges"] as $arrkey => $challenge) {
            $exploded = explode(" ", $challenge);
            $sql = $this->db_api->execute("INSERT INTO chia_farm_challenges (id, date, hash, hash_index) VALUES (NULL, ?, ?, ?)",
                                          array($now->format("Y-m-d H:i:s"), $exploded[1], $exploded[3]));
          }

          return array("status" => 0, "message" => "Successfully updated challenges information.");
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    public function getAllChallenges(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT date, hash, hash_index FROM chia_farm_challenges", array());

        return array("status" =>0, "message" => "Successfully queried all challenges.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
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
