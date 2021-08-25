<?php
  namespace ChiaMgmt\Chia_Harvester;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;

  class Chia_Harvester_Api{
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

    public function updateHarvesterData(array $data, array $loginData = NULL){
      if(array_key_exists("harvester", $data)){
        $harvesterdata = $data["harvester"];

        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_plots_directories WHERE nodeid = ?", array($nodeid));
          $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

          $harvesterdbdata = $this->getHarvesterData(NULL, NULL, $nodeid, false);

          foreach($harvesterdata AS $finalplotsdir => $mountpointinfo){
            if($harvesterdbdata["status"] == 0 && count($harvesterdbdata["data"]) > 0){
              if($harvesterdbdata["status"] == 0 && array_key_exists($finalplotsdir, $harvesterdbdata["data"][$nodeid]["plotdirs"])){
                $diff = $this->diffData($harvesterdbdata["data"][$nodeid]["plotdirs"][$finalplotsdir], $mountpointinfo);
                if(count($diff) > 0){
                  if(array_key_exists("devname", $mountpointinfo)){
                    $sql = $this->db_api->execute("UPDATE chia_plots_directories SET devname = ?, mountpoint = ?, totalsize = ?, totalused = ?, totalusedpercent = ?, plotcount = ? WHERE finalplotsdir = ? AND nodeid = ?;",
                    array($mountpointinfo["devname"], $mountpointinfo["mountpoint"], $mountpointinfo["totalsize"], $mountpointinfo["totalused"], $mountpointinfo["totalusedpercent"], $mountpointinfo["plotcount"], $mountpointinfo["finalplotsdir"], $nodeid));
                  }else{
                    $sql = $this->db_api->execute("UPDATE chia_plots_directories SET devname = ?, mountpoint = ?, totalsize = ?, totalused = ?, totalusedpercent = ?, plotcount = ? WHERE finalplotsdir = ? AND nodeid = ?;",
                    array(NULL, NULL, NULL, NULL, NULL, 0, $finalplotsdir, $nodeid));
                  }
                }
              }else if(!array_key_exists($finalplotsdir, $harvesterdbdata["data"][$nodeid]["plotdirs"])){
                if(array_key_exists("devname", $mountpointinfo)){
                  $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, devname, mountpoint, finalplotsdir, totalsize, totalused, totalusedpercent, plotcount) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?)",
                          array($nodeid, $mountpointinfo["devname"], $mountpointinfo["mountpoint"], $mountpointinfo["finalplotsdir"], $mountpointinfo["totalsize"], $mountpointinfo["totalused"], $mountpointinfo["totalusedpercent"], $mountpointinfo["plotcount"]));
                }else{
                  $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, finalplotsdir, plotcount) VALUES(NULL, ?, ?, ?)",
                          array($nodeid, $finalplotsdir, 0));
                }              }
            }else{
              if(array_key_exists("devname", $mountpointinfo)){
                $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, devname, mountpoint, finalplotsdir, totalsize, totalused, totalusedpercent, plotcount) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?)",
                        array($nodeid, $mountpointinfo["devname"], $mountpointinfo["mountpoint"], $mountpointinfo["finalplotsdir"], $mountpointinfo["totalsize"], $mountpointinfo["totalused"], $mountpointinfo["totalusedpercent"], $mountpointinfo["plotcount"]));
              }else{
                $sql = $this->db_api->execute("INSERT INTO chia_plots_directories (id, nodeid, finalplotsdir, plotcount) VALUES(NULL, ?, ?, ?)",
                        array($nodeid, $finalplotsdir, 0));
              }
            }
            $plotsfound = [];
            if(array_key_exists("plotsfound", $mountpointinfo)) $plotsfound = $mountpointinfo["plotsfound"];
            $this->updateFoundPlots($plotsfound, $finalplotsdir, $nodeid);
          }

          if(array_key_exists($nodeid, $harvesterdbdata["data"])){
            foreach($harvesterdbdata["data"][$nodeid]["plotdirs"] AS $finalplotsdir => $mointpointdata){
              if(!array_key_exists($finalplotsdir, $harvesterdata)){
                $sql = $this->db_api->execute("DELETE FROM chia_plots_directories WHERE nodeid = ? AND finalplotsdir = ?", array($nodeid, $finalplotsdir));
                $this->removePlots($nodeid, $finalplotsdir);
              }
            }
          }
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully updated farmer information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getHarvesterData($data, $loginData, $nodeid, false)["data"]]);
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    private function diffData(array $dbdata, array $mountpointdata){
      unset($dbdata["id"]);
      unset($dbdata["nodeid"]);
      unset($dbdata["nodeauthhash"]);
      unset($dbdata["hostname"]);
      unset($mountpointdata["plotsfound"]);

      if(array_key_exists("devname", $mountpointdata) || !is_null($dbdata["devname"])){
        return array_diff($dbdata, $mountpointdata);
      }else{
        return [];
      }
    }

    private function updateFoundPlots(array $plotdata, string $finalplotsdir, int $nodeid){
      try{
        $sql = $this->db_api->execute("SELECT id FROM chia_plots_directories WHERE finalplotsdir = ? AND nodeid = ?", array($finalplotsdir, $nodeid));
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

        foreach($sqreturn AS $arrkey => $plotinfos){
          $finalmountid = $plotinfos["id"];

          $sql = $this->db_api->execute("SELECT id, finalmountid, nodeid, k_size, plot_key, pool_key, filename FROM chia_plots", array());
          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $tempdbdata = $sqreturn;
          foreach($sqreturn AS $dbarrkey => $dbplotdata){
            if(!in_array($dbplotdata["filename"], $plotdata) && $dbplotdata["finalmountid"] == $finalmountid){
              $this->removePlots($nodeid, $finalplotsdir);
            }
            $tempdbdata[$dbarrkey] = $dbplotdata["filename"];
          }

          foreach($plotdata AS $arrkey => $localfilename){
            if(!in_array($localfilename, $tempdbdata)){
              $filenameexpl = explode("-", $localfilename);
              $creationdate = new \DateTime("{$filenameexpl[2]}-{$filenameexpl[3]}-{$filenameexpl[4]} {$filenameexpl[5]}:{$filenameexpl[6]}:00");

              $sql = $this->db_api->execute("INSERT INTO chia_plots (id, finalmountid, nodeid, k_size, plotcreationdate, plot_key, pool_key, filename) VALUES(NULL, ?, ?, ?, ?, NULL, NULL, ?)",
                                            array($finalmountid, $nodeid, $filenameexpl[1], $creationdate->format("Y-m-d H:i:s"), $localfilename));
            }
          }
        }
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    private function removePlots(int $nodeid, string $finalplotsdir){
      try{
        $sql = $this->db_api->execute("SELECT id FROM chia_plots_directories WHERE finalplotsdir = ? AND nodeid = ?", array($finalplotsdir, $nodeid));
        $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(count($sqreturn) == 1){
          $sql = $this->db_api->execute("DELETE FROM chia_plots WHERE nodeid = ? AND finalmountid = ?", array($nodeid, $sqreturn[0]["id"]));
        }else{
          return array("status" => 1, "message" => "More than one row was returned. Aborting deleting from db for security reasons.");
        }
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    private function getFoundPlots(int $nodeid, int $finalmountid){
      try{
        $sql = $this->db_api->execute("SELECT finalmountid, k_size, plotcreationdate, plot_key, pool_key, filename, status FROM chia_plots WHERE finalmountid = ? AND nodeid = ?", array($finalmountid, $nodeid));

        return array("status" =>0, "message" => "Successfully loaded chia plots information for node {$nodeid} and mountid {$finalmountid}.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function getHarvesterData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL, bool $getPlots = true){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT cp.id, nt.nodeid, n.nodeauthhash, n.hostname, cp.devname, cp.mountpoint, cp.finalplotsdir, cp.totalsize, cp.totalused, cp.totalusedpercent, cp.plotcount, cp.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_plots_directories cp ON cp.nodeid = nt.nodeid
                                          WHERE nt.code = 4"
                                        , array());
        }else{
          $sql = $this->db_api->execute("SELECT cp.id, nt.nodeid, n.nodeauthhash, n.hostname, cp.devname, cp.mountpoint, cp.finalplotsdir, cp.totalsize, cp.totalused, cp.totalusedpercent, cp.plotcount, cp.querydate
                                          FROM nodetype nt
                                          JOIN nodes n ON n.id = nt.nodeid
                                          LEFT JOIN chia_plots_directories cp ON cp.nodeid = nt.nodeid
                                          WHERE nt.code = 4 AND cp.nodeid = ?"
                                        , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $harvesterinfo){
          if(!is_null($harvesterinfo["finalplotsdir"])) $returndata[$harvesterinfo["nodeid"]]["plotdirs"][$harvesterinfo["finalplotsdir"]] = $harvesterinfo;
          else $returndata[$harvesterinfo["nodeid"]]["plotdirs"]["Unknown"] = $harvesterinfo;

          if($getPlots && !is_null($harvesterinfo["nodeid"]) && !is_null($harvesterinfo["id"])){
            $returndata[$harvesterinfo["nodeid"]]["plotdirs"][$harvesterinfo["finalplotsdir"]]["foundplots"] = $this->getFoundPlots($harvesterinfo["nodeid"], $harvesterinfo["id"]);
          }
          $returndata[$harvesterinfo["nodeid"]]["hostname"] = $harvesterinfo["hostname"];
          $returndata[$harvesterinfo["nodeid"]]["nodeauthhash"] = $this->decryptAuthhash($harvesterinfo["nodeauthhash"]);
        }

        return array("status" =>0, "message" => "Successfully loaded chia harvester information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function queryHarvesterData(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["queryHarvesterData"] = array(
        "status" => 0,
        "message" => "Query harvester data.",
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

    public function restartHarvesterService(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["restartHarvesterService"] = array(
        "status" => 0,
        "message" => "Restart harvester service.",
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

    public function harvesterStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 4, "stat" => ($data["status"] == 0 ? 0 : 1), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    public function harvesterServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryptAuthhash($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester service restart for node $nodeid.", "data" => $data);
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
