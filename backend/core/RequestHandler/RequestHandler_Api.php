<?php
  namespace ChiaMgmt\RequestHandler;
  use React\Promise;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;
  use ChiaMgmt\Alerting\Alerting_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;

  /**
   * The RequestHandler_Api class validates all requests to the websocket api.
   * This class is used by the node client, web(/app) client and the backend client to validate their logged in states and if they are allowed to send in or get data.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class RequestHandler_Api{
    /**
     * Holds an instance to the Login Class.
     * @var Logi_Api
     */
    private $login_api;
    /**
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging;
    /**
    * Holds an instance to the Encryption Class.
    * @var Encryption_Api
    */
    private $encryption_api;
    /**
     * The server configuration file.
     * @var array
     */
    private $ini;
    /**
    * Holds an array of the logged in clients.
    * @var array
    */
    private $subscriptions;
    /**
    * Holds an array of the clients which wants to login.
    * @var array
    */
    private $requests;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * The constructur sets the needed above stated private variables except $subscriptions and $requests.
     */
    public function __construct(object $server = NULL){
      $this->login_api = new Login_Api();
      $this->logging = new Logging_Api($this, $server);
      $this->system_update_api = new System_Update_Api();
      $this->encryption_api = new Encryption_Api();
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * Checks if a incoming data request or send in request of a specific node is valid.
     * It will checked first if the requesting node is logged in. If not the node is not allowed to send in or get data.
     * Function made for: All websocket clients
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $loginData              The nodes logindata.
     * @param  array  $backendInfo            { method : [The method which should be called] }
     * @param  array  $data                   { "maintenance_mode" : [1 = true |0 = false] }
     * @param  WebSocketServer $server        An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The returnded data] } from subquery.
     */
    public function processRequest(array $loginData, array $backendInfo, array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $backendInfo, $data){
        $check_updates = Promise\resolve((new System_Update_Api())->checkUpdateRoutine());
        $check_updates->then(function($check_updates_returned) use(&$resolve){
          if($check_updates_returned["data"]["maintenance_mode"] == 1 && $backendInfo["method"] != "finishUpdate" && $backendInfo["method"] != "disableMaintenanceMode"){
            return $resolve($this->logging->getErrormessage("processRequest", "001"));
          }
        });

        if(class_exists($backendInfo['namespace']) && method_exists($backendInfo['namespace'], $backendInfo['method'])){
          $dynamic_request = Promise\resolve((new $backendInfo['namespace']($this->server))->{$backendInfo['method']}($data, $loginData, $this->server));
          $dynamic_request->then(function($dynamic_request_returned) use(&$resolve, $backendInfo){
            $resolve(array($backendInfo['method'] => $dynamic_request_returned));
          })->otherwise(function (\Exception $e) use(&$resolve, $backendInfo){
            $returndata[$backendInfo['method']] = $this->logging->getErrormessage("processRequest", "002", "Class {$backendInfo['namespace']} or function {$backendInfo['method']} not existing.");
            $resolve($returndata);
          });
        }else{
          $returndata[$backendInfo['method']] = $this->logging->getErrormessage("processRequest", "003", "Class {$backendInfo['namespace']} or function {$backendInfo['method']} not existing.");
          $resolve($returndata);
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Calls and executes the setup cronjob.
     * Function made for: All websocket clients
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array  $loginData              The nodes logindata.
     * @param  array  $backendInfo            { method : [The method which should be called] }
     * @param  array  $data                   { "maintenance_mode" : [1 = true |0 = false] }
     * @param  WebSocketServer $server        An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The returnded data] } from subquery.
     */
    public function processCronRequest(array $loginData, array $backendInfo, array $data, array $nodeInfo, $server): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $backendInfo, $data, $server){
        $cronjob_promises = [
          Promise\resolve((new System_Api())->setCurrentCronjobRunTimestamp()),
          Promise\resolve((new Chia_Overall_Api())->queryOverallData()),
          Promise\resolve((new Exchangerates_Api())->queryExchangeRatesData()),
          Promise\resolve((new Chia_Infra_Sysinfo_Api())->getSystemInfo()),
          Promise\resolve($this->processRequest($loginData, ["namespace" => "ChiaMgmt\Nodes\Nodes_Api", "method" => "queryNodesServicesStatus"], [])),
        ];
        
        
        Promise\all($cronjob_promises)->then(function($all_returned) use(&$resolve, $server){ 
          Promise\resolve($server->messageFrontendClients(array("siteID" => 9), array("queryOverallData" => $all_returned[1])));
          Promise\resolve($server->messageFrontendClients(array("siteID" => 8), array("getSystemInfo" => $all_returned[3])));
          Promise\resolve($server->messageAllNodes(array("data" => ["querySystemInfo" => [ "status" => 0, "message" => "Query systeminfo data.", "data"=> []]])));
          Promise\resolve($server->messageAllNodes(array("data" => ["queryWalletData" => ["status" => 0, "message" => "Query wallet data.", "data"=>[]]])));
          Promise\resolve($server->messageAllNodes(array("data" => ["queryWalletTransactions" => ["status" => 0, "message" => "Query wallet transaction data.", "data"=>[]]])));
          Promise\resolve($server->messageAllNodes(array("data" => ["queryFarmData" => ["status" => 0, "message" => "Query farm transaction data.", "data"=>[]]])));
          Promise\resolve($server->messageAllNodes(array("data" => ["queryHarvesterData" => ["status" => 0, "message" => "Query harvester transaction data.", "data"=>[]]])));
          Promise\resolve($server->messageAllNodes(array("data" => ["get_script_version" => ["status" => 0, "message" => "Query chia node overall data.", "data"=>[]]])));
         
          $alertAllWARNCRIT = Promise\resolve((new Alerting_Api)->alertAllFoundWARNandCRIT());
          $alertAllWARNCRIT->then(function($alertAllWARNCRIT_returned) use(&$resolve){
            $resolve(array("cronJobExecution" => array("status" => 0, "message" => "Successfully executed system background jobs.", "data" => date("Y-m-d H:i:s"))));
          });
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Return a list of currently connected websocket clients in json format.
     * Function made for: All websocket clients
     * @param  array  $loginData      The nodes logindata.
     * @param  array  $subscriptions  A list of subscriptions given from the websocket server.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processGetActiveSubscriptions(array $loginData, array $subscriptions): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $subscriptions){
        if($loginData["authhash"] == $this->ini["web_client_auth_hash"] || $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
          $resolve(array("getActiveSubscriptions" => array("status" => 0, "message" => "Successfully loaded active subscriptions.", "data" => $subscriptions)));
        }else{
          $resolve($this->logging->getErrormessage("processGetActiveSubscriptions", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller); 
    }

    /**
     * Return a list of currently for connection waiting websocket clients in json format.
     * Function made for: All websocket clients
     * @param  array  $loginData      The nodes logindata.
     * @param  array  $requests       A list of all clients which are waiting for authorisation given from the websocket server.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processGetActiveRequests(array $loginData, array $requests): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $requests){
        if($loginData["authhash"] == $this->ini["web_client_auth_hash"] || $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
            $resolve(array("getActiveRequests" => array("status" => 0, "message" => "Successfully loaded active requests.", "data" => $requests)));
        }else{
          $resolve($this->logging->getErrormessage("processGetActiveRequests", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller); 
    }

    /**
     * Updates the siteid from a connected webclient if the frontend site has changed. This is needed to push newly added data to the correct site.
     * This enables the websocket server not to use broadcasts for the gui but targets the user which is currently viewing the correct site.
     * Function made for: Web/App Client
     * @param  array  $loginData      The nodes logindata.
     * @param  array  $subscriptions  A list of subscriptions given from the websocket server.
     * @param  array  $data           { userID : [The users id], siteID : [The site which the user is currently viewing] }
     * @param  int    $connid         The connection id of the user who changed the site, given from the websocket server.
     * @return array                  { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [Current subscriptions] }
     */
    public function processUpdateFrontendViewingSite(array $loginData, array $subscriptions, array $data, int $connid): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $subscriptions, $data, $connid){
        if(array_key_exists("userID", $data) && array_key_exists("siteID", $data) &&
        ($loginData["authhash"] == $this->ini["web_client_auth_hash"] ||
        $loginData["authhash"] == $this->ini["backend_client_auth_hash"])){
          if(array_key_exists("webClient", $subscriptions)){
            $found = false;
            foreach($subscriptions["webClient"] AS $connection => $value){
              if(array_key_exists("userid", $value) && $value["userid"] == $data["userID"] && $connection == $connid){
                $found = true;
                $subscriptions["webClient"][$connection]["siteID"] = $data["siteID"];
              }
            }

            if($found) return $resolve(array("updateFrontendViewingSite" => array("status" => 0, "message" => "Sucessfully updated siteID.", "data" => $subscriptions)));
          }
          $resolve($this->logging->getErrormessage("processUpdateFrontendViewingSite", "001"));
      }else{
        $resolve($this->logging->getErrormessage("processUpdateFrontendViewingSite", "002"));
      }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Informs the frontend that a/some node(s) has joined or disjoined the websocket server and waits for connection permission.
     * Function made for: Communication between Backend and Web/App Client
     * @param  array  $requests  A list of all clients which are waiting for authorisation given from the websocket server.
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processConnectionRequest(array $requests): object
    {
      return Promise\resolve(array("clientConnectionRequest" => array("status" => 0, "message" => "Successfully handeled connection request.", "data" => $requests)));
    }

    /**
     * Validates the loginstatus of a data or connection request.
     * Bevore a client is able to query data from the websocket server it's login status will be checked beforehand.
     * If a node client has newly connected it will be registered to the api and ca be accepted.
     * Function made for: All node types
     * @param  string $nodeip    The connected node's ip address.
     * @param  array  $data      { "authhash" : [The connected node's authhash] }
     * @param  array  $nodeinfo  An array of the client's connection infos.
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function requesterLogin(string $nodeip, array $data, array $nodeinfo): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($nodeip, $data, $nodeinfo){
        if(array_key_exists("authhash", $data)){
          if(!is_Null($data["authhash"])) $encryptedauthhash = $this->encryption_api->encryptString($data["authhash"]);
          else $encryptedauthhash = "";

          $node_info = Promise\resolve((new DB_Api())->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.authtype, n.conallow, n.hostname, n.ipaddress
                                                                FROM nodes n
                                                                JOIN nodetype nt ON nt.nodeid = n.id
                                                                JOIN nodetypes_avail nta ON nta.code = nt.code
                                                                WHERE n.nodeauthhash = ? AND n.hostname = ?
                                                                GROUP BY n.id", array($encryptedauthhash, $nodeinfo["hostname"])));

          $node_info->then(function($node_info_returned) use(&$resolve, $nodeip, $data, $nodeinfo){
            $node_info_returned = $node_info_returned->resultRows;

            $ipaddressvalid = true;
            if(array_key_exists("0", $node_info_returned) && !in_array("webClient", explode(",", $node_info_returned[0]["nodetype"])) && !in_array("backendClient", explode(",", $node_info_returned[0]["nodetype"]))){
              if($nodeip != $node_info_returned[0]["ipaddress"]) $ipaddressvalid = false;
            }

            if(count($node_info_returned) == 1 && $ipaddressvalid){
              $node_info_returned = $node_info_returned[0];

              if($node_info_returned["conallow"] == 1){
                if($node_info_returned["authtype"] == 1 || $node_info_returned["authtype"] == 2){
                  $conallow = Promise\resolve((new DB_Api())->execute("UPDATE nodes SET lastseen = current_timestamp() WHERE id = ?",array($node_info_returned["id"])));
                  $conallow->otherwise(function (\Exception $e) use(&$resolve){
                    return $resolve($this->logging->getErrormessage("requesterLogin", "015", $e));
                  });  
                }
                if($node_info_returned["authtype"] == 1){ //Authtype = 1 means there must be username and session string stated
                  if(array_key_exists("userid", $data) && array_key_exists("sessionid", $data)){
                    $authenticated = Promise\resolve((new Login_Api())->checklogin($data["sessionid"], $data["userid"]));
                    $authenticated->then(function($authenticated_returned) use(&$resolve, $data, $node_info_returned){
                      $authenticated_returned["nodeinfo"]["nodedata"]["userid"] = $data["userid"];
                      $authenticated_returned["nodeinfo"]["nodedata"]["sessionid"] = $data["sessionid"];
                      $authenticated_returned["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                      $authenticated_returned["nodeinfo"]["type"] = $node_info_returned["nodetype"];
    
                      $resolve($authenticated_returned);
                    });
                  }else{
                    return $resolve($this->logging->getErrormessage("requesterLogin", "001"));
                  }
                }else if($node_info_returned["authtype"] == 0){ //Authtype is currently not known, because this node is not authenticated to the api
                  $returndata = $this->logging->getErrormessage("requesterLogin", "012");
                  $returndata["data"]["authhash"] = $data["authhash"];
                  return $returndata;
                }else if($node_info_returned["authtype"] == 2){ //Authtype = 2 means that this node needs an accepted IP address and authhash
                  $authenticated = array("status" => 0, "message" => "This node is allowed to connect to the api.");
                  $authenticated["nodeinfo"]["nodedata"]["nodeid"] = $node_info_returned["id"];
                  $authenticated["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                  $authenticated["nodeinfo"]["type"] = $node_info_returned["nodetype"];
  
                  $resolve($authenticated);
                }else if($node_info_returned["authtype"] == 3){ //Authtype = 3 means that this node needs no further login information only the authhash. Usage only for backendClient!
                  if($node_info_returned["nodetype"] == "backendClient" && $nodeip == "localhost"){
                    $authenticated = array("status" => 0, "message" => "This node is allowed to connect.");
                    $authenticated["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                    $authenticated["nodeinfo"]["type"] = $node_info_returned["nodetype"];
  
                    $resolve($authenticated);
                  }else{
                    return $resolve($this->logging->getErrormessage("requesterLogin", "004"));
                  }
                }else{
                  return $resolve($this->logging->getErrormessage("requesterLogin", "005", "Authtype " . $node_info_returned["authtype"] . " not valid."));
                }
              }else if($node_info_returned["conallow"] == 2){
                $returndata = $this->logging->getErrormessage("requesterLogin", "002");
                $returndata["data"]["authhash"] = $data["authhash"];
                $returndata["data"]["resid"] = $data["authhash"];
                $resolve($returndata);
              }else if($node_info_returned["conallow"] == 0){
                $resolve($this->logging->getErrormessage("requesterLogin", "003"));
              }
            }else if((count($node_info_returned) == 1 || count($node_info_returned) == 0) && !$ipaddressvalid || !is_null($nodeip)){
              $node_data = Promise\resolve((new DB_Api())->execute("SELECT id, ipaddress, nodeauthhash FROM nodes WHERE hostname = ?", array($nodeinfo["hostname"])));
              $node_data->then(function($node_data_returned) use(&$resolve, $nodeinfo, $nodeip){
                $node_data_returned = $node_data_returned->resultRows;

                if(count($node_data_returned) == 0 && array_key_exists("hostname", $nodeinfo)){
                  $newnodeauthhash = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 35);
                  $encryptedauthhash = $this->encryption_api->encryptString($newnodeauthhash);

                  $insert_new_node = Promise\resolve((new DB_Api())->execute("INSERT INTO nodes (id, nodeauthhash, hostname, conallow, authtype, ipaddress) VALUES (NULL, ?, ?, ?, ?, ?)",
                                                      array($encryptedauthhash, $nodeinfo["hostname"], 2, 0, $nodeip)));
                  $insert_new_node_type = Promise\resolve((new DB_Api())->execute("INSERT INTO nodetype (id, nodeid, code) VALUES (NULL, (SELECT id FROM nodes WHERE nodeauthhash = ?), 99)",
                                                          array($encryptedauthhash)));

                  Promise\all([$insert_new_node, $insert_new_node_type])->then(function($insert_node_info_returned){
                    $returndata = $this->logging->getErrormessage("requesterLogin", "006");
                    $returndata["data"]["nodeid"] = $insert_node_info_returned[0]->insertId;
                    $returndata["data"]["authhash"] = $newnodeauthhash;
                    $resolve($returndata);                  
                  })->otherwise(function (\Exception $e) use(&$resolve){
                    return $resolve($this->logging->getErrormessage("requesterLogin", "016", $e));
                  });
                }else if(count($node_data_returned) == 1){
                  if($nodeip == $node_data_returned[0]["ipaddress"]){
                    if(strlen(trim($data["authhash"])) == 0) $data = $this->logging->getErrormessage("requesterLogin", "013");
                    else $data = $this->logging->getErrormessage("requesterLogin", "007");
    
                    $data["data"]["nodeid"] = $node_data_returned[0]["id"];
                    $data["data"]["authhash"] = $this->encryption_api->decryptString($node_data_returned[0]["nodeauthhash"]);
    
                    $resolve($data);
                  }else{
                    $updateNodes = Promise\resolve((new DB_Api())->execute("UPDATE nodes SET changedIP = ? WHERE hostname = ?", array($nodeip, $nodeinfo["hostname"])));
                    $updateNodes->otherwise(function (\Exception $e) use(&$resolve){
                      return $resolve($this->logging->getErrormessage("requesterLogin", "017", $e));
                    }); 

                    $data = $this->logging->getErrormessage("requesterLogin", "011");
                    $data["data"]["authhash"] = $this->encryption_api->decryptString($node_data_returned[0]["nodeauthhash"]);
                    $resolve($data);
                  }
                }else{
                  return $resolve($this->logging->getErrormessage("requesterLogin", "014", "Cannot add host with IP {$nodeip}. Not all data stated."));
                }
              })->otherwise(function (\Exception $e) use(&$resolve){
                return $resolve($this->logging->getErrormessage("requesterLogin", "015", $e));
              }); 
            }else{
              return $resolve($this->logging->getErrormessage("requesterLogin", "008"));
            }
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging->getErrormessage("requesterLogin", "009", $e));
          });
        }else{
          $resolve($this->logging->getErrormessage("requesterLogin", "010"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
