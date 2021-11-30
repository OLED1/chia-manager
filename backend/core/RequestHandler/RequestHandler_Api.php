<?php
  namespace ChiaMgmt\RequestHandler;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\System_Update\System_Update_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;

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
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
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
      $this->db_api = new DB_Api();
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
    public function processRequest(array $loginData, array $backendInfo, array $data){
      if($this->system_update_api->checkUpdateRoutine()["data"]["maintenance_mode"] == 1 && $backendInfo["method"] != "finishUpdate" && $backendInfo["method"] != "disableMaintenanceMode"){
        return $this->logging->getErrormessage("001");
      }

      if(class_exists($backendInfo['namespace']) && method_exists($backendInfo['namespace'], $backendInfo['method'])){
        try{
          $this_class = new $backendInfo['namespace']($this->server);
          $return = $this_class->{$backendInfo['method']}($data, $loginData, $this->server);

          return array($backendInfo['method'] => $return);
        }catch(\Exception $e){
          $returndata[$backendInfo['method']] = $this->logging->getErrormessage("002", "Class {$backendInfo['namespace']} or function {$backendInfo['method']} not existing.");
          return $returndata;
        }
      }else{
        $returndata[$backendInfo['method']] = $this->logging->getErrormessage("003", "Class {$backendInfo['namespace']} or function {$backendInfo['method']} not existing.");
        return $returndata;
      }
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
    public function processCronRequest(array $loginData, array $backendInfo, array $data, array $nodeInfo, $server = NULL){
      $system_api = new System_Api();
      $system_api->setCurrentCronjobRunTimestamp();
      
      //Query new overall data
      $overall_api = new Chia_Overall_Api();
      $server->messageFrontendClients(array("siteID" => 9), array("queryOverallData" => $overall_api->queryOverallData()));
      
      //Query new available performance data from the chia nodes
      $querydata["data"]["querySystemInfo"] = array(
        "status" => 0,
        "message" => "Query systeminfo data.",
        "data"=> array()
      );
      $server->messageAllNodes($querydata);

      //Inform frontend about new sysinfo data
      $chia_infra_sysinfo_api = new Chia_Infra_Sysinfo_Api();
      $server->messageFrontendClients(array("siteID" => 8), array("getSystemInfo" => $chia_infra_sysinfo_api->getSystemInfo()));

      //Query new available wallet data from the chia nodes
      $querydata["data"] = [];
      $querydata["data"]["queryWalletData"] = array(
        "status" => 0,
        "message" => "Query wallet data.",
        "data"=> array()
      );
      $server->messageAllNodes($querydata);

      //Query new available transactions data from the chia nodes
      $querydata["data"] = [];
      $querydata["data"]["queryWalletTransactions"] = array(
        "status" => 0,
        "message" => "Query wallet transaction data.",
        "data"=> array()
      );
      $server->messageAllNodes($querydata);

      //Query new available farmer data from the chia nodes
      $querydata["data"] = [];
      $querydata["data"]["queryFarmData"] = array(
        "status" => 0,
        "message" => "Query farm transaction data.",
        "data"=> array()
      );
      $server->messageAllNodes($querydata);

      $now = new \Datetime("now");
      return array("cronJobExecution" => array("status" => 0, "message" => "Successfully executed system background jobs.", "data" => $now->format("Y-m-d H:i:s")));
    }

    /**
     * Return a list of currently connected websocket clients in json format.
     * Function made for: All websocket clients
     * @param  array  $loginData      The nodes logindata.
     * @param  array  $subscriptions  A list of subscriptions given from the websocket server.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processGetActiveSubscriptions(array $loginData, array $subscriptions){
      if($loginData["authhash"] == $this->ini["web_client_auth_hash"] ||
          $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
        return array("getActiveSubscriptions" => array("status" => 0, "message" => "Successfully loaded active subscriptions.", "data" => $subscriptions));
      }else{
        return $this->logging->getErrormessage("001");
      }
    }

    /**
     * Return a list of currently for connection waiting websocket clients in json format.
     * Function made for: All websocket clients
     * @param  array  $loginData      The nodes logindata.
     * @param  array  $requests       A list of all clients which are waiting for authorisation given from the websocket server.
     * @return array                  {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processGetActiveRequests(array $loginData, array $requests){

      if($loginData["authhash"] == $this->ini["web_client_auth_hash"] ||
          $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
        return array("getActiveRequests" => array("status" => 0, "message" => "Successfully loaded active requests.", "data" => $requests));
      }else{
        return $this->logging->getErrormessage("001");
      }
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
    public function processUpdateFrontendViewingSite(array $loginData, array $subscriptions, array $data, int $connid){
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

            if($found) return array("updateFrontendViewingSite" => array("status" => 0, "message" => "Sucessfully updated siteID.", "data" => $subscriptions));
          }
          return $this->logging->getErrormessage("001");
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Informs the frontend that a/some node(s) has joined or disjoined the websocket server.
     * Function made for: Communication between Backend and Web/App Client
     * @param  array  $subscriptions A list of subscriptions given from the websocket server.
     * @param  array  $changedtypes  An array which contains the node types where the connection has changed.
     * @param  int    $connstatus    The connection status. Either 0 or 1.
     * @return array                 { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : ["subscriptions" : [A list of current active clients], "changedtypes" => [An array of the changed types], "connstatus" => [0 = disconnected|1 = connected]] }
     */
    public function processNodeConnectionChanged(array $subscriptions, array $changedtypes, int $connstatus){
      return array("connectedNodesChanged" => array("status" => 0, "message" => "Successfully handeled connection request.", "data" => ["subscriptions" => $subscriptions, "changedtypes" => $changedtypes, "connstatus" => $connstatus]));
    }

    /**
     * Informs the frontend that a/some node(s) has joined or disjoined the websocket server and waits for connection permission.
     * Function made for: Communication between Backend and Web/App Client
     * @param  array  $requests  A list of all clients which are waiting for authorisation given from the websocket server.
     * @return array             {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : [The queried data] }
     */
    public function processConnectionRequest(array $requests){
      return array("clientConnectionRequest" => array("status" => 0, "message" => "Successfully handeled connection request.", "data" => $requests));
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
    public function requesterLogin(string $nodeip, array $data, array $nodeinfo){
      if(array_key_exists("authhash", $data)){
        if(!is_Null($data["authhash"])) $encryptedauthhash = $this->encryption_api->encryptString($data["authhash"]);
        else $encryptedauthhash = "";

        try{
          $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.authtype, n.conallow, n.hostname, n.ipaddress
                                        FROM nodes n
                                        JOIN nodetype nt ON nt.nodeid = n.id
                                        JOIN nodetypes_avail nta ON nta.code = nt.code
                                        WHERE n.nodeauthhash = ? AND n.hostname = ?
                                        GROUP BY n.id", array($encryptedauthhash, $nodeinfo["hostname"]));
          $sqldata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $ipaddressvalid = true;
          if(array_key_exists("0", $sqldata) && !in_array("webClient", explode(",", $sqldata[0]["nodetype"])) && !in_array("backendClient", explode(",", $sqldata[0]["nodetype"]))){
            if($nodeip != $sqldata[0]["ipaddress"]) $ipaddressvalid = false;
          }

          if(count($sqldata) == 1 && $ipaddressvalid){
            $sqldata = $sqldata[0];

            if($sqldata["conallow"] == 1){
              if($sqldata["authtype"] == 1 || $sqldata["authtype"] == 2){
                $sql = $this->db_api->execute("UPDATE nodes SET lastseen = current_timestamp() WHERE id = ?",array($sqldata["id"]));
              }
              if($sqldata["authtype"] == 1){ //Authtype = 1 means there must be username and session string stated
                if(array_key_exists("userid", $data) && array_key_exists("sessionid", $data)){
                  $authenticated = $this->login_api->checklogin($data["sessionid"], $data["userid"]);
                  $authenticated["nodeinfo"]["nodedata"]["userid"] = $data["userid"];
                  $authenticated["nodeinfo"]["nodedata"]["sessionid"] = $data["sessionid"];
                  $authenticated["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                  $authenticated["nodeinfo"]["type"] = $sqldata["nodetype"];

                  return $authenticated;
                }else{
                  return $this->logging->getErrormessage("001");
                }
              }else if($sqldata["authtype"] == 0){ //Authtype is currently not known, because this node is not authenticated to the api
                $returndata = $this->logging->getErrormessage("012");
                $returndata["data"]["newauthhash"] = $data["authhash"];
                return $returndata;
              }else if($sqldata["authtype"] == 2){ //Authtype = 2 means that this node needs an accepted IP address and authhash
                $authenticated = array("status" => 0, "message" => "This node is allowed to connect to the api.");
                $authenticated["nodeinfo"]["nodedata"]["nodeid"] = $sqldata["id"];
                $authenticated["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                $authenticated["nodeinfo"]["type"] = $sqldata["nodetype"];

                return $authenticated;
              }else if($sqldata["authtype"] == 3){ //Authtype = 3 means that this node needs no further login information only the authhash. Usage only for backendClient!
                if($sqldata["nodetype"] == "backendClient" && $nodeip == "localhost"){
                  $authenticated = array("status" => 0, "message" => "This node is allowed to connect.");
                  $authenticated["nodeinfo"]["nodedata"]["authhash"] = $data["authhash"];
                  $authenticated["nodeinfo"]["type"] = $sqldata["nodetype"];

                  return $authenticated;
                }else{
                  return $this->logging->getErrormessage("004");
                }
              }else{
                return $this->logging->getErrormessage("005", "Authtype " . $sqldata["authtype"] . " not valid.");
              }
            }else if($sqldata["conallow"] == 2){
              $returndata = $this->logging->getErrormessage("002");
              $returndata["data"]["authhash"] = $data["authhash"];
              $returndata["data"]["resid"] = $data["authhash"];
              return $returndata;
            }else if($sqldata["conallow"] == 0){
              return $this->logging->getErrormessage("003");
            }
          }else if((count($sqldata) == 1 || count($sqldata) == 0) && !$ipaddressvalid || !is_null($nodeip)){
            $sql = $this->db_api->execute("SELECT id, ipaddress, nodeauthhash FROM nodes WHERE hostname = ?", array($nodeinfo["hostname"]));
            $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if(count($sqdata) == 0 && array_key_exists("hostname", $nodeinfo)){
              $newnodeauthhash = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 35);
              $encryptedauthhash = $this->encryption_api->encryptString($newnodeauthhash);

              $sql = $this->db_api->execute("INSERT INTO nodes (id, nodeauthhash, hostname, conallow, authtype, ipaddress) VALUES (NULL, ?, ?, ?, ?, ?)",
                                            array($encryptedauthhash, $nodeinfo["hostname"], 2, 0, $nodeip));

              $sql = $this->db_api->execute("INSERT INTO nodetype (id, nodeid, code) VALUES (NULL, (SELECT id FROM nodes WHERE nodeauthhash = ?), 99)",
                                            array($encryptedauthhash));

              $sql = $this->db_api->execute("SELECT id, ipaddress, nodeauthhash FROM nodes WHERE hostname = ?", array($nodeinfo["hostname"]));
              $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

              $returndata = $this->logging->getErrormessage("006");
              $returndata["data"]["nodeid"] = $sqdata[0]["id"];
              $returndata["data"]["newauthhash"] = $newnodeauthhash;
              return $returndata;
            }else if(count($sqdata) == 1){

              if($nodeip == $sqdata[0]["ipaddress"]){
                if(strlen(trim($data["authhash"])) == 0) $data = $this->logging->getErrormessage("013");
                else $data = $this->logging->getErrormessage("007");

                $data["data"]["nodeid"] = $sqdata[0]["id"];
                $data["data"]["newauthhash"] = $this->encryption_api->decryptString($sqdata[0]["nodeauthhash"]);

                return $data;
              }else{
                $sql = $this->db_api->execute("UPDATE nodes SET changedIP = ? WHERE hostname = ?", array($nodeip, $nodeinfo["hostname"]));
                $data = $this->logging->getErrormessage("011");
                $data["data"]["newauthhash"] = $this->encryption_api->decryptString($sqdata[0]["nodeauthhash"]);
                return $data;
              }
            }else{
              return $this->logging->getErrormessage("014", "Cannot add host with IP {$nodeip}. Not all data stated.");
            }
          }else{
            return $this->logging->getErrormessage("008");
          }
        }catch(\Exception $e){
          return $this->logging->getErrormessage("009", $e);
        }
      }else{
        return $this->logging->getErrormessage("010");
      }
    }
  }
?>
