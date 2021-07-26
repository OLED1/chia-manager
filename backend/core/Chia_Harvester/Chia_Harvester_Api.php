<?php
  namespace ChiaMgmt\Chia_Harvester;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Chia_Harvester_Api{
    private $db_api, $logging_api;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function updateHarvesterData(array $data, array $loginData = NULL){
      if(array_key_exists("harvester", $data)){

        $harvesterdata = $data["harvester"];

        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_plots_directories WHERE nodeid = ?", array($nodeid));
          $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

          if($count == 0){
            foreach($harvesterdata AS $finalplotsdir => $mountpointinfo){
              print_r($mountpointinfo);

              if(array_key_exists("devname", $mountpointinfo)){
                $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, devname, mountpoint, finalplotsdir, directorysize, directoryused, totalused_percent, plotcount) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?)",
                                                array($nodeid, $mountpointinfo["devname"], $mountpointinfo["mountpoint"], $mountpointinfo["finalplotsdir"], $mountpointinfo["totalsize"], $mountpointinfo["totalused"], $mountpointinfo["totalusedpercent"], $mountpointinfo["plotcount"]));
              }else{
                $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, finalplotsdir, plotcount) VALUES(NULL, ?, ?, ?)",
                                                array($nodeid, $finalplotsdir, 0));
              }
            }
          }else{
            //$sql = $this->db_api->execute("UPDATE chia_plots_directories SET nodeid = ?, devname = ?, mountpoint = ?, finalplotsdir = ?, directorysize_gb = ?, directoryused_gb = ?, totalused_percent = ?, plotcount = ? WHERE nodeid = ?",
            //array($farmdata["farming_status"], $farmdata["total_chia_farmed"], $farmdata["user_transaction_fees"], $farmdata["block_rewards"], $farmdata["last_height_farmed"], $farmdata["plot_count"], $farmdata["total_size_of_plots"], $farmdata["estimated_network_space"], $farmdata["expected_time_to_win"], $nodeid));
          }

        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }

        return array("status" =>0, "message" => "Successfully updated farmer information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
      }
    }

    public function getHarvesterData(array $data = NULL, array $loginData = NULL, $nodeid = NULL){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT cp.id, cp.nodeid, n.nodeauthhash, n.hostname, cp.devname, cp.mountpoint, cp.finalplotsdir, cp.directorysize, cp.directoryused, cp.totalused_percent, cp.plotcount
                                        FROM chia_plots_directories cp
                                        JOIN nodes n ON cp.nodeid = n.id"
                                        , array());
        }else{
          $sql = $this->db_api->execute("SELECT cp.id, cp.nodeid, n.nodeauthhash, n.hostname, cp.devname, cp.mountpoint, cp.finalplotsdir, cp.directorysize, cp.directoryused, cp.totalused_percent, cp.plotcount
                                        FROM chia_plots_directories cp
                                        JOIN nodes n ON cp.nodeid = n.id
                                        WHERE cp.nodeid = ?"
                                        , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $harvesterinfo){
          $returndata[$harvesterinfo["nodeid"]]["plotdirs"][$harvesterinfo["finalplotsdir"]] = $harvesterinfo;
          $returndata[$harvesterinfo["nodeid"]]["hostname"] = $harvesterinfo["hostname"];
          $returndata[$harvesterinfo["nodeid"]]["nodeauthhash"] = $this->decryptAuthhash($harvesterinfo["nodeauthhash"]);
        }

        return array("status" =>0, "message" => "Successfully loaded chia harvester information.", "data" => $returndata);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function harvesterStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function harvesterServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester service restart for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
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
