<?php
  namespace ChiaMgmt\Chia_Harvester;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Chia_Harvester_Api class contains every needed methods to manage all available harvester data.
   * This class is used by the client to send in data and from the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Harvester_Api{
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
     * Holds an instance to the Nodes Class.
     * @var Nodes_Api
     */
    private $nodes_api;
    /**
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this, $server);
      $this->nodes_api = new Nodes_Api();
      $this->encryption_api = new Encryption_Api();
      $this->server = $server;
    }

    /**
     * Update the available harvester data.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array  $data       {"harvester": {"/mnt/EDOUSB002": {}, "/mnt/KUMUSB003": {}, "/mnt/KUMUSB005": {}, "/mnt/xchtestmount/XCHTEST1": {}}}
     * @param  array  $loginData  {"authhash": "[Querying Node's authhash]"}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {"nodeid": [nodeid], "data": {[newly added harvester data]}}
     */
    public function updateHarvesterData(array $data, array $loginData = NULL){
      if(array_key_exists("harvester", $data)){
        $harvesterdata = $data["harvester"];

        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
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

    /**
     * Checks if sent in data is different to the databases stored data.
     * Function made for: Backend (Private)
     * @param  array  $dbdata         The database stored data.
     * @param  array  $mountpointdata The sent in mountpoint data from the node.
     * @return array                  Returns an array with the missing (not in database existing) data.
     */
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

    /**
     * Updates the list of found plots of a certain node.
     * Function made for: Node Client
     * @throws Exception $e           Throws an exception on db errors.
     * @param  array  $plotdata       An array of reported found plots of a certain node.
     * @param  string $finalplotsdir  An array of reported found (final) plot directories.
     * @param  int    $nodeid         The id of the node where the sent in data belongs.
     * @return array                  Returns a message array with an errorcode in case of an db error, otherwise nothing.
     */
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

    /**
     * Cleans up all plots from a certain node in case new plots are reported to keep the database table clean.
     * Function made for: Backend (Private)
     * @throws Exception $e           Throws an exception on db errors.
     * @param  int    $nodeid         The reporting node's id.
     * @param  string $finalplotsdir  The final plot directory where the plots has changed.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
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

    /**
     * Returns an array of found plots of a specific node.
     * Function made for: Backend (Private)
     * @throws Exception $e         Throws an exception on db errors.
     * @param  int    $nodeid       The node's id from which the data is needed
     * @param  int    $finalmountid The final mount directory from where the plotdata should be loaded.
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found plots array]}
     */
    private function getFoundPlots(int $nodeid, int $finalmountid){
      try{
        $sql = $this->db_api->execute("SELECT finalmountid, k_size, plotcreationdate, plot_key, pool_key, filename, status FROM chia_plots WHERE finalmountid = ? AND nodeid = ?", array($finalmountid, $nodeid));

        return array("status" =>0, "message" => "Successfully loaded chia plots information for node {$nodeid} and mountid {$finalmountid}.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Returns an array of all available on the database stored harvester values.
     * Function made for: Web GUI/App
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array  $loginData               { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer  $server    An instance to websocket server class to be able to send data directly to nodes.
     * @param  int  $nodeid                    The node id to get only node specific data. Can be NULL if all data will be queried. Will be deprecated as soon as the method needs to be called outsite of the web gui.
     * @param  boolean $getPlots               When TRUE associated plots will be loaded too. When FALSE no plots will be loaded.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found harvester data array]}
     */
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
          $returndata[$harvesterinfo["nodeid"]]["nodeauthhash"] = $this->encryption_api->decryptString($harvesterinfo["nodeauthhash"]);
        }

        return array("status" =>0, "message" => "Successfully loaded chia harvester information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Informs the node client to query new harvester data.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageAllNodes
     * @param  array $data                  { authhash: [Target Node Authhash] }
     * @param  array $loginData             { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server  An instance to the websocket server to be able to send data to the connected clients.
     * @return array                        Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction calls.
     */
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

    /**
     * Informs the node client to restart the harvester service.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @param  array $data                    { authhash: [Target Node Authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the websocket server to be able to send data to the connected clients.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction call.
     */
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

    /**
     * Sets the current harvesterstatus sent in from the node client.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data       { status: [0 = Running |1 = Not Running] } No data is needed to query this method.
     * @param  array $loginData  { NULL } No logindata is needed to query this method.
     * @return array             Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function harvesterStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 4, "stat" => ($data["status"] == 0 ? 0 : 1), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * The function which will be called from the node client when the service has been restarted.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data      { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI] }
     * @param  array $loginData { authhash: [Querying Node's Authhash] }
     * @return array            Returns {"status": [0|>0], "message": [Status message], "data": { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI], nodeid: [Querying Node's ID] }}
     */
    public function harvesterServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester service restart for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }
  }
?>
