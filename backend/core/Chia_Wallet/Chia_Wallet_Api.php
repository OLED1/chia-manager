<?php
  namespace ChiaMgmt\Chia_Wallet;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;

  class Chia_Wallet_Api{
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

    public function updateWalletData(array $data, array $loginData = NULL){
      if(array_key_exists("wallet", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          foreach($data["wallet"] AS $walletid => $walletdata){
            $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_wallets WHERE walletid = ? AND nodeid = ?", array($walletid, $nodeid));
            $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

            if($count == 0){
              $sql = $this->db_api->execute("INSERT INTO chia_wallets (id, nodeid, walletid, walletaddress, walletheight, syncstatus, wallettype, totalbalance, pendingtotalbalance, spendable) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
              array($nodeid, $walletid, $walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"]));
            }else{
              $sql = $this->db_api->execute("UPDATE chia_wallets SET  walletaddress = ?, walletheight = ?, syncstatus = ?, wallettype = ?, totalbalance = ?, pendingtotalbalance = ?, spendable = ? WHERE walletid = ? AND nodeid = ?",
              array($walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"], $walletid, $nodeid));
            }
          }
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully updated wallet information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getWalletData($data, $loginData, $nodeid)["data"]]);
      }else{
        //TODO Implement correct status code
        return array("status" =>1, "message" => "Not all data stated.");
      }
    }

    public function getWalletData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT cw.walletid, nt.nodeid, n.nodeauthhash, n.hostname, cw.walletaddress, cw.walletheight, cw.syncstatus, cw.wallettype, cw.totalbalance, cw.pendingtotalbalance, cw.spendable, cw.querydate
                                         FROM nodetype nt
                                         JOIN nodes n ON n.id = nt.nodeid
                                         LEFT JOIN chia_wallets cw ON cw.nodeid = nt.nodeid
                                         WHERE nt.code = 5"
                                         , array());
        }else{
          $sql = $this->db_api->execute("SELECT cw.walletid, nt.nodeid, n.nodeauthhash, n.hostname, cw.walletaddress, cw.walletheight, cw.syncstatus, cw.wallettype, cw.totalbalance, cw.pendingtotalbalance, cw.spendable, cw.querydate
                                         FROM nodetype nt
                                         JOIN nodes n ON n.id = nt.nodeid
                                         LEFT JOIN chia_wallets cw ON cw.nodeid = nt.nodeid
                                         WHERE nt.code = 5 AND nt.nodeid = ?"
                                         , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $walletinfo){
          $walletinfo["nodeauthhash"] = $this->decryptAuthhash($walletinfo["nodeauthhash"]);
          $returndata[$walletinfo["nodeid"]][(is_numeric($walletinfo["walletid"]) ? $walletinfo["walletid"] : 0)] = $walletinfo;
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function walletStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 5, "stat" => ($data["status"] == 0 ? 1 : 0), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried wallet status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function queryWalletData(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["queryWalletData"] = array(
        "status" => 0,
        "message" => "Query Wallet data.",
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

    public function restartWalletService(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["restartWalletService"] = array(
        "status" => 0,
        "message" => "Restart wallet service.",
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

    public function walletServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried wallet service restart for node $nodeid.", "data" => $data);
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
