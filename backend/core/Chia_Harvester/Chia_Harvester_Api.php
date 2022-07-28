<?php
  namespace ChiaMgmt\Chia_Harvester;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Harvester\Data_Objects\Plots;

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
     * Holds an instance to the Infra Sysinfo Class.
     * @var Chia_Infra_Sysinfo
     */
    private $chia_infra_sysinfo_api;
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
      $this->chia_infra_sysinfo_api = new Chia_Infra_Sysinfo_Api();
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
    public function updateHarvesterData(array $data, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/[0]["id"];
        
        $sql = $this->db_api->execute("SELECT id, nodeid, mountpoint, plotcount FROM chia_plots_directories WHERE nodeid = ?", array($nodeid));
        $foundplottingdirectories = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/;

        $stated_diff_array = [];
        $db_saved_diff_array = [];
        $insert_update_statements = "";
        $insert_update_data = [];

        //Creating the diff arrays to detect changes from database to newly reported data
        foreach($data AS $finalmointpoint => $plots){
          array_push($stated_diff_array, $finalmointpoint);  
        }
        foreach($foundplottingdirectories AS $arrkey => $plotdirdata){
          array_push($db_saved_diff_array, $plotdirdata["mountpoint"]);
        }
        $stated_to_db_diff = array_diff($stated_diff_array, $db_saved_diff_array);

        //Creating insert statement if new values are reported
        foreach($stated_to_db_diff AS $arrkey => $mountpoint){
          $insert_update_statements .= "INSERT INTO chia_plots_directories (id, nodeid, mountpoint, plotcount, firstreported, lastupdated, querydate) VALUES(NULL, ?, ?, ?, current_timestamp(), current_timestamp(), current_timestamp());";
          array_push($insert_update_data, $nodeid, $mountpoint, count($data[$mountpoint]));
        }
        
        //Updating not reported or renewed plot directories
        foreach($foundplottingdirectories AS $arrkey => $plotdirdata){
          $this_set_statement = "";
          if(array_key_exists($plotdirdata["mountpoint"], $data)){
            $this_set_statement = "plotcount = ?, lastupdated = current_timestamp(),";
            array_push($insert_update_data, count($data[$plotdirdata["mountpoint"]]));
          }
          $insert_update_statements .= "UPDATE chia_plots_directories SET {$this_set_statement} querydate = current_timestamp() WHERE mountpoint = ? AND nodeid = ?;";
          array_push($insert_update_data, $plotdirdata["mountpoint"], $nodeid);
        }
        
        $sql = $this->db_api->execute($insert_update_statements, $insert_update_data);
        $this->updateFoundPlots($data, $nodeid);
        
        return array("status" => 0, "message" => "Successfully updated farmer information for node $nodeid.", "data" => ["nodeid" => $nodeid]);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
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
    private function updateFoundPlots(array $plotdata, int $nodeid): array
    {
      try{
        foreach($plotdata AS $mountpoint => $plotdata){
          $sql = $this->db_api->execute("SELECT id FROM chia_plots_directories WHERE nodeid = ? AND mountpoint = ?", array($nodeid, $mountpoint));
          $cpd_id = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/;

          if(count($cpd_id) == 1){
            $cpd_id = $cpd_id[0]["id"];
  
            foreach($plotdata AS $arrkey => $thisplot){
              $formatted_data = new Plots($thisplot);
              $sql = $this->db_api->execute("INSERT INTO chia_plots (id,cpd_id,file_size,filename,plot_seed,plot_id,plot_public_key,pool_contract_puzzle_hash,pool_public_key,size,time_modified,last_reported) 
                                              VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp()) ON DUPLICATE KEY UPDATE last_reported = current_timestamp()
              ", [
                $cpd_id, $formatted_data->get_file_size(), $formatted_data->get_filename(), $formatted_data->get_plot_seed(), $formatted_data->get_plot_id(), 
                $formatted_data->get_plot_public_key(), $formatted_data->get_pool_contract_puzzle_hash(), $formatted_data->get_pool_public_key(),
                $formatted_data->get_k_size(), $formatted_data->get_time_modified()
              ]);
            }
          }else{
            return array("status" => 1, "message" => "CPD_ID could not be determined because there were no id returned or too much rows.");
          }
        }
  
        return array("status" => 0, "message" => "Successfully updated found plots");
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Returns an array of all available on the database stored harvester values.
     * Function made for: Web GUI/App
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array  $loginData               { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer  $server    An instance to websocket server class to be able to send data directly to nodes.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found harvester data array]}
     */
    public function getHarvesterData(array $data = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $nodeid = NULL;
        $getPlots = true;
        $getPlots_statement = "";
        $getPlots_join = "";
        $nodeid_statement = "";
        $statement_array = [];
        if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0) $nodeid = $data["nodeid"];
        if(!is_null($data) && array_key_exists("getPlots", $data) && is_bool($data["getPlots"])) $getPlots = $data["getPlots"];

        if($getPlots){
          $getPlots_join = "LEFT JOIN chia_plots cp ON cp.cpd_id = cpd.id";
          $getPlots_statement = ", cp.file_size AS plot_file_size, cp.filename AS plot_filename, cp.plot_id, cp.size AS plot_size, cp.time_modified AS plot_time_modified, cp.last_reported AS plot_last_reported, cp.plot_public_key, cp.pool_public_key AS plot_pool_public_key";
        }
        if(is_numeric($nodeid)){
          $nodeid_statement = "AND nt.nodeid = ?";
          $statement_array[0] = $nodeid;
        }

        $harvester_data = Promise\resolve((new DB_Api())->execute("SELECT nt.nodeid AS nodeid, n.nodeauthhash, n.hostname, cpd.mountpoint, cpd.plotcount, cpd.firstreported AS mount_firstrepoted, cpd.lastupdated AS mount_lastupdated,  cisf.device AS mount_device, cisf.size AS mount_size, cisf.used AS mount_used, cisf.avail AS mount_avail {$getPlots_statement}
                                                                    FROM nodetype nt
                                                                    INNER JOIN nodes n ON n.id = nt.nodeid
                                                                    LEFT JOIN chia_plots_directories cpd ON cpd.nodeid = n.id
                                                                    LEFT JOIN chia_infra_sysinfo cis ON cis.nodeid = n.id AND cis.timestamp = (SELECT max(cis1.timestamp) FROM chia_infra_sysinfo cis1 WHERE cis1.nodeid = n.id)
                                                                    LEFT JOIN chia_infra_sysinfo_filesystems cisf ON cisf.sysinfo_id = cis.id AND cisf.mountpoint = cpd.mountpoint
                                                                    {$getPlots_join}
                                                                    WHERE nt.code = 4 {$nodeid_statement}"
                                                                  , $statement_array));

        $harvester_data->then(function($harvester_data_returned) use(&$resolve){
          $returndata = [];

          foreach($harvester_data_returned->resultRows AS $arrkey => $harvesterinfo){
            $nodeid = $harvesterinfo["nodeid"];
            if(!array_key_exists($nodeid, $returndata)){
              $returndata[$nodeid] = ["nodeauthhash" => $harvesterinfo["nodeauthhash"], "hostname" => $harvesterinfo["hostname"]];
              $returndata[$nodeid]["plotdirs"] = [];
            }
            unset($harvesterinfo["nodeid"], $harvesterinfo["nodeauthhash"], $harvesterinfo["hostname"]);
            
            if(!is_null($harvesterinfo) && array_key_exists("mountpoint", $harvesterinfo) && !is_null($harvesterinfo["mountpoint"])){
              if(!array_key_exists($harvesterinfo["mountpoint"], $returndata[$nodeid]["plotdirs"])){
                $returndata[$nodeid]["plotdirs"][$harvesterinfo["mountpoint"]] = [
                  "plotcount" => $harvesterinfo["plotcount"],
                  "mount_device" => $harvesterinfo["mount_device"],
                  "mount_size" => $harvesterinfo["mount_size"],
                  "mount_used" => $harvesterinfo["mount_used"],
                  "mount_avail" => $harvesterinfo["mount_avail"],
                  "mount_firstrepoted" => $harvesterinfo["mount_firstrepoted"], 
                  "mount_lastupdated" => $harvesterinfo["mount_lastupdated"],
                  "plots" => []
                ];
              }
              if($harvesterinfo["plotcount"] > 0){
                unset($harvesterinfo["plotcount"], $harvesterinfo["mount_firstrepoted"], $harvesterinfo["mount_lastupdated"], $harvesterinfo["mount_device"], $harvesterinfo["mount_size"], $harvesterinfo["mount_used"], $harvesterinfo["mount_avail"]);
                array_push($returndata[$nodeid]["plotdirs"][$harvesterinfo["mountpoint"]]["plots"], $harvesterinfo);
              }
            }
          }

          $resolve(array("status" =>0, "message" => "Successfully loaded chia harvester information.", "data" => $returndata));
        })->otherwise(function (\Exception $e) use(&$resolve){
          print_r($e);
          $resolve($this->logging_api->getErrormessage("getHarvesterData", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);    
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
    public function queryHarvesterData(array $data = NULL, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        $querydata = [];
        $querydata["data"]["queryHarvesterData"] = array(
          "status" => 0,
          "message" => "Query harvester data.",
          "data"=> array()
        );
  
        $callfunction = "messageAllNodes";
        if(array_key_exists("nodeinfo", $data) && array_key_exists("authhash", $data["nodeinfo"])){
          $querydata["nodeinfo"]["authhash"] = $data["nodeinfo"]["authhash"];
          $callfunction = "messageSpecificNode";
        }

        if(!is_null($server)){
          $function_call = Promise\resolve($server->$callfunction($querydata));
        }else{
          $websocket_api = new WebSocket_Api();
          $function_call = Promise\resolve((new WebSocket_Api())->sendToWSS($callfunction, $querydata));
        }

        $function_call->then(function($function_call_returned) use(&$resolve){
          $resolve($function_call_returned);
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function restartHarvesterService(array $data = NULL, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        if(array_key_exists("authhash", $data)){
          $querydata = [];
          $querydata["data"]["restartHarvesterService"] = array(
            "status" => 0,
            "message" => "Restart harvester service.",
            "data"=> array()
          );
          $querydata["nodeinfo"]["authhash"] = $data["authhash"];

          if(!is_null($server)){
            $function_call = Promise\resolve($server->messageSpecificNode($querydata));
          }else{
            $function_call = Pormise\resolve((new WebSocket_Api())->sendToWSS("messageSpecificNode", $querydata));
          }

          $function_call->then(function($function_calls_returned) use(&$resolve){
            $resolve($function_calls_returned);
          });
        }else{
          $resolve($this->logging_api->getErrormessage("restartHarvesterService", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function harvesterServiceRestart(array $data = NULL, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried harvester service restart for node $nodeid.", "data" => $data);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }
  }
?>
