<?php
  namespace ChiaMgmt\Chia_Infra_Sysinfo;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Chia_Infra_Sysinfo_Api{
    private $db_api, $logging_api;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
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
          return $this->logging->getErrormessage("001", $e);
        }
      }
    }

    public function getSystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
        try{
          if(is_null($nodeid)){
            $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cif.timestamp, cif.load_1min, cif.load_5min, cif.load_15min, cif.filesystem, cif.memory_total, cif.memory_free, cif.memory_buffers, cif.memory_cached, cif.swap_total, cif.swap_free, cif.cpu_count, cif.cpu_cores, cif.cpu_model
                                            FROM nodes n
                                            LEFT JOIN chia_infra_sysinfo cif ON cif.nodeid = n.id AND cif.timestamp = (SELECT max(cif1.timestamp) FROM chia_infra_sysinfo cif1 WHERE cif1.nodeid = n.id)
                                            WHERE n.id = (
                                                SELECT nt.nodeid FROM nodetype nt WHERE nt.code >= 3 AND nt.code <= 5 AND nt.nodeid = n.id LIMIT 1
                                            )", array());
          }else{
            $sql = $this->db_api->execute("SELECT n.id, n.hostname, n.nodeauthhash, cif.timestamp, cif.load_1min, cif.load_5min, cif.load_15min, cif.filesystem, cif.memory_total, cif.memory_free, cif.memory_buffers, cif.memory_cached, cif.swap_total, cif.swap_free, cif.cpu_count, cif.cpu_cores, cif.cpu_model
                                            FROM nodes n
                                            LEFT JOIN chia_infra_sysinfo cif ON cif.nodeid = n.id AND cif.timestamp = (SELECT max(cif1.timestamp) FROM chia_infra_sysinfo cif1 WHERE cif1.nodeid = n.id)
                                            WHERE n.id = ?
                                            )", array($data["nodeid"]));
          }

          $returnarray = [];
          foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $sysinfodata){
            $returnarray[$sysinfodata["id"]] = $sysinfodata;
          }

          return array("status" => 0, "message" => "Successfully loaded latest system information.", "data" => $returnarray);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }
    }

    public function querySystemInfo(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["querySystemInfo"] = array(
        "status" => 0,
        "message" => "Query systeminfo data.",
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

    private function encryptAuthhash(string $encryptedauthhash){
      return openssl_encrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    public function decryptAuthhash(string $encryptedauthhash){
      return openssl_decrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }

?>
