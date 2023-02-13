<?php
  namespace ChiaMgmt\Nodes;
  use React\Promise;
  use React\Http\Browser;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\WebSocket\WebSocket_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Chia_Infra_Sysinfo\Chia_Infra_Sysinfo_Api;

  /**
   * The Nodes_Api class contains every needed methods to manage all available nodes.
   * The following nodes are valid: backend, webclient (app), farmer, harvester, wallet.
   * The last stated types can be used at once.
   * This class is used by the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Nodes_Api{
    /**
     * Holds an instance to the WebSocket Class.
     * @var WebSocket_Api
     */
    private $websocket_api;
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
     * Holds an instance to the Encryption Class.
     * @var Encryption_Api
     */
    private $encryption_api;
    /**
     * Holds an instance to the Chia_Overall_Api Class.
     * @var Chia_Overall_Api
     */
    private $chia_overall_api;
        /**
     * Holds an instance to the Chia_Infra_Sysinfo Class.
     * @var Chia_Infra_Sysinfo
     */
    private $chia_infra_sysinfo_api;
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
      $this->encryption_api = new Encryption_Api();
      $this->chia_overall_api = new Chia_Overall_Api();
      $this->server = $server;
    }

    /**
     * Returns a list of active subscriptions known to the websocket server which saves this data locally.
     * Subscriptions contains a list of clients which are currently logged in and accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active subscriptions]}
     */
    public function getActiveSubscriptions(): object
    {
      return Promise\resolve((new WebSocket_Api())->sendToWSS("getActiveSubscriptions"))->then(function($subscriptions){
        return (array_key_exists("getActiveSubscriptions", $subscriptions) ? $subscriptions["getActiveSubscriptions"] : []);
      });
    }

    /**
     * Returns a list of active requests known to the websocket server which saves this data locally.
     * Requests contains a list of clients which are currently waiting to get accepted by the api.
     * Function made for: Backend / Web GUI
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[List of nodes of active requests]}
     */
    public function getActiveRequests(): object
    {
      return Promise\resolve((new WebSocket_Api())->sendToWSS("getActiveRequests"))->then(function($requests){
        return (array_key_exists("getActiveRequests", $requests) ? $requests["getActiveRequests"] : []);
      });
    }

    /**
     * Returns an array of all information available for all nodes.
     * Function made for: Web GUI
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node information]}
     */
    public function getConfiguredNodes(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $nodeid = "";
        $nodetype = "";
  
        if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0){
          $nodeid = "WHERE n.id = {$data["nodeid"]}";
        }

        if(array_key_exists("nodetypenum", $data) && (is_numeric($data["nodetypenum"]) || is_array($data["nodetypenum"]))){
          if(is_numeric($data["nodetypenum"] && $data["nodetypenum"] > 0 && $data["nodetypenum"] < 6)) $nodetype = "AND nt.code = {$data["nodetypenum"]}";
          else if(is_array($data["nodetypenum"])) $nodetype = "AND nt.code IN (" . implode(",", $data["nodetypenum"]) . ")";
        }

        $client_nodes = Promise\resolve((new DB_Api())->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.nodeauthhash, n.authtype,
                                                                        n.conallow, n.hostname, n.scriptversion, n.chiaversion, n.chiapath, n.ipaddress,
                                                                        n.changeable, n.changedIP, MAX(cimu.memory_total) AS memory_total, MAX(cisu.swap_total) AS swap_total,
                                                                        MAX(cis.cpu_cores) AS cpu_cores, MAX(cis.cpu_count) AS cpu_count, MAX(cis.cpu_model) AS cpu_model, lastseen
                                                                FROM nodes n
                                                                JOIN nodetype nt ON nt.nodeid = n.id
                                                                JOIN nodetypes_avail nta ON nta.code = nt.code {$nodetype}
                                                                LEFT JOIN chia_infra_sysinfo cis ON cis.timestamp = (SELECT MAX(timestamp) FROM chia_infra_sysinfo WHERE nodeid = n.id) AND cis.nodeid = n.id
                                                                LEFT JOIN chia_infra_memory_usage cimu ON cimu.sysinfo_id = cis.id
                                                                LEFT JOIN chia_infra_swap_usage cisu ON cisu.sysinfo_id = cis.id
                                                                {$nodeid}
                                                                GROUP BY n.id", array()));

        $client_nodes->then(function($client_nodes_returned) use(&$resolve){
          $returndata = array();

          foreach ($client_nodes_returned->resultRows as $arrkey => $conninfo) {
            $returnarray[$conninfo["id"]] = $conninfo;
            $returnarray[$conninfo["id"]]["nodeauthhash"] = $this->encryption_api->decryptString($conninfo["nodeauthhash"]);
          }
  
          $resolve(array("status" => 0, "message" => "Sucessfully loaded all client data.", "data" => $returnarray));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getConfiguredNodes", "001", $e));
        });
  
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns a list of all nodes known and registered to the api.
     * Function made for: Web GUI
     * @throws Exception $e       Throws an exception on db errors.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB stored node type information]}
     */
    public function getNodeTypes(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $node_types = Promise\resolve((new DB_Api())->execute("SELECT id, description, code, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1", array()));
        $node_types->then(function($node_types_returned) use(&$resolve){
          $returndata = array();
          foreach($node_types_returned->resultRows AS $arrkey => $info){
            $returndata["by-id"][$info["id"]] = $info;
            $returndata["by-desc"][$info["description"]] = $info;
          }

          $resolve(array("status" => 0, "message" => "Sucessfully loaded all available nodetypes.", "data" => $returndata));
        })->otherwise(function(\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getNodeTypes", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Allow a node to reconnect if it's ip has recently changed.
     * Function made for: Web GUI
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array  $data                   { "nodeid" : [The nodes id where the ip has changed], "authhash" : "[The nodes authhash where the ip has changed]" }
     * @param  array $loginData               { NULL } No logindata is needed to query this function
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function acceptIPChange(array $data, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
          $set_node_ip = Promise\resolve((new DB_Api())->execute("UPDATE nodes SET ipaddress = changedIP, changedIP = ? WHERE id = ?", array("", $data["nodeid"])));
          $set_node_ip->then(function($set_node_ip_returned) use(&$resolve, $data){
            $querydata = [];
            $querydata["data"]["acceptIPChange"] = array(
              "status" => 0,
              "message" => "IP Change saved accepted.",
              "data"=> array()
            );
  
            $querydata["nodeinfo"]["authhash"] = $data["authhash"];
            if(!is_null($server)){
              Promise\resolve($server->messageSpecificNode($querydata));
            }else{
              Promise\resolve((new WebSocket_Api())->sendToWSS("messageSpecificNode", $querydata));
            }
  
            $resolve(array("status" => 0, "message" => "IP change saved for node {$data["nodeid"]}."));
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("acceptIPChange", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("acceptIPChange", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Accept a request of (newly) connected node.
     * Function made for: Web GUI
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : [nodeid], "authhash" : [node's authhash], "nodetypes" : [The nodes types as array(e.g. [farmer, harvester])]}
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server     An instance to the Webscoket server to be able to communicate with the node
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function acceptNodeRequest(array $data, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data) && array_key_exists("nodetypes", $data)){
          $nodetypes = Promise\resolve((new DB_Api())->execute("SELECT id, allowed_authtype, nodetype FROM nodetypes_avail WHERE selectable = 1 AND id IN ({$data["nodetypes"]})", array()));
          $nodetypes->then(function($nodetypes_returned) use(&$resolve, $data, $server){
            $types = [];
            $allowed_authtype = [];
            foreach ($nodetypes_returned->resultRows as $arrkey => $nodetypes) {
              $types[$nodetypes["nodetype"]] = 1;
              $allowed_authtype[$nodetypes["allowed_authtype"]] = 1;
            }

            if(count($types) == 1 && count($allowed_authtype) == 1){
              $authtype = $nodetypes_returned->resultRows[0]["allowed_authtype"];
              $nodeid = $data["nodeid"];
              $authhash = $data["authhash"];

              $statements_to_resolve = [
                Promise\resolve((new DB_Api())->execute("UPDATE nodes SET conallow = 1, authtype = ? WHERE id = ?", array($authtype, $nodeid))),
                Promise\resolve((new DB_Api())->execute("DELETE FROM nodetype WHERE nodeid = ?", array($nodeid)))
              ];

              foreach(explode(",", $data["nodetypes"]) AS $arrkey => $nodetype){
                $statements_to_resolve[] = Promise\resolve((new DB_Api())->execute("INSERT INTO nodetype (id, nodeid, code) VALUES(NULL, ?, ?)", array($nodeid, $nodetype)));
              }

              Promise\all($statements_to_resolve)->then(function($all_returned) use(&$resolve, $data, $server){
                $returnmessage = array("status" => 0, "message" => "Successfully allowed connection for node with ID {$data["nodeid"]}.");
                $querydata = [];
                $querydata["data"]["acceptNodeRequest"] = $returnmessage;
                $querydata["nodeinfo"]["authhash"] = $data["authhash"];
    
                if(!is_null($server)){
                  Promise\resolve($server->messageSpecificNode($querydata));
                }else{
                  Promise\resolve((new WebSocket_Api())->sendToWSS("messageSpecificNode", $querydata));
                }
    
                $resolve($returnmessage);
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("acceptNodeRequest", "002", $e));
              });
            }else{
              $resolve($this->logging_api->getErrormessage("acceptNodeRequest", "001"));
            }
          })->otherwise(function(\Exception $e)  use(&$resolve){
            $resolve($this->logging_api->getErrormessage("acceptNodeRequest", "004", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("acceptNodeRequest", "003"));
        }
      };  

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Decline a request of a (newly) connected node.
     * Function made for: Web GUI
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : [nodeid], "authhash" : [node's authhash] }
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server     An instance to the Webscoket server to be able to communicate with the node.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function declineNodeRequest(array $data, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
          $decline_node = Promise\resolve((new DB_Api())->execute("UPDATE nodes SET conallow = 0 WHERE id = ? AND changeable = 1 AND (conallow = 1 OR conallow = 2)", array($data["nodeid"])));
          $decline_node->then(function($decline_node_returned) use(&$resolve, $data, $server){
            $returnmessage = array("status" => 0, "message" => "Successfully declined connection for id {$data["nodeid"]}.");
            $querydata = [];
            $querydata["data"]["declineNodeRequest"] = $returnmessage;
            $querydata["nodeinfo"]["authhash"] = $data["authhash"];
  
            if(!is_null($server)){
              Promise\resolve($server->messageSpecificNode($querydata));
            }else{
              Promise\resolve((new WebSocket_Api())->sendToWSS("messageSpecificNode", $querydata));
            }
  
            $resolve($returnmessage);
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("declineNodeRequest", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("declineNodeRequest", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Removes a node and all ist associated data from the database.
     * Function made for: Web GUI
     * @throws Exception $e                   Throws an exception on db errors.
     * @param  array  $data                   { "nodeid" : [nodeid], "authhash" : [node's authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the Webscoket server to be able to communicate with the node
     * @return array                          {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function removeNodeAndData(array $data, array $loginData = NULL, $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        if(array_key_exists("nodeid", $data) && array_key_exists("authhash", $data)){
          $node_changeable = Promise\resolve((new DB_Api())->execute("SELECT changeable FROM nodes WHERE id = ?", array($data["nodeid"])));
          $node_changeable->then(function($node_changeable_returned) use(&$resolve, $data){
            if(count($node_changeable_returned->resultRows) == 1){
              $changeable = $node_changeable_returned->resultRows[0]["changeable"];

              if($changeable){
                $remove_node = Promise\resolve((new DB_Api())->execute("DELETE FROM nodes WHERE id = ?", array($data["nodeid"])));
                $remove_node->then(function($remove_node_returned) use(&$resolve, $data){
                  $returnmessage = array("status" => 0, "message" => "Successfully removed node {$data["nodeid"]}.", "data" => $data["nodeid"]);
                  $querydata = [];
                  $querydata["data"]["removeNodeAndData"] = $returnmessage;
                  $querydata["nodeinfo"]["authhash"] = $data["authhash"];

                  if(!is_null($server)){
                    Promise\resolve($server->messageSpecificNode($querydata));
                  }else{
                    Promise\resolve((new WebSocket_Api())->sendToWSS("messageSpecificNode", $querydata));
                  }

                  $resolve($returnmessage);
                })->otherwise(function(\Exception $e) use(&$resolve){
                  $resolve($this->logging_api->getErrormessage("removeNodeAndData", "005", $e));
                });
              }else{
                $resolve($this->logging_api->getErrormessage("removeNodeAndData", "001"));
              }
            }else{
              $resolve($this->logging_api->getErrormessage("removeNodeAndData", "002"));
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("removeNodeAndData", "003", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("removeNodeAndData", "004"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the loginstatus of certain node with the given authhash.
     * Function made for: Web GUI
     * @throws Exception $e     Throws an exception on db errors.
     * @param  array  $data     { "authhash" : [A node's authhash]}
     * @param  array $loginData [description]
     * @return array            [description]
     */
    public function loginStatus(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("authhash", $loginData)){
          $loginStatus = Promise\resolve((new DB_Api())->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.hostname
                                                                      FROM nodes n
                                                                      JOIN nodetype nt ON nt.nodeid = n.id
                                                                      JOIN nodetypes_avail nta ON nta.code = nt.code
                                                                      WHERE n.nodeauthhash = ?
                                                                      GROUP BY n.id", array($this->encryption_api->encryptString($loginData["authhash"]))));
          $loginStatus->then(function($loginStatus_returned) use(&$resolve){
            $resolve(array("status" => 0, "method" => "loginStatus", "message" => "This node is logged in.", "data" => $loginStatus_returned->resultRows[0]));
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("loginStatus", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("loginStatus", "002"));
        }
      };  

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
      
      if(array_key_exists("authhash", $loginData)){
        try{
          $sql = $this->db_api->execute("SELECT n.id, GROUP_CONCAT(nta.description SEPARATOR ', ') AS nodetype, n.hostname
          FROM nodes n
          JOIN nodetype nt ON nt.nodeid = n.id
          JOIN nodetypes_avail nta ON nta.code = nt.code
          WHERE n.nodeauthhash = ?
          GROUP BY n.id", array($this->encryption_api->encryptString($loginData["authhash"])));
          //$sqldata = $sql->fetchAll(\PDO::FETCH_ASSOC)[0];
          $sqldata = $sql[0];

          return array("status" => 0, "method" => "loginStatus", "message" => "This node is logged in.", "data" => $sqldata);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Sets the node client's script version to be accessible for the frontend
     * Function made for: Node client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "authhash" : [node's authhash - string], "scriptversion" : [Node's current node script version - string], "chia" : [Node's current installed chia version -string] }
     * @param  array $loginData                { NULL } No logindata needed to query this function.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function updateScriptVersion(array $data, array $loginData = NULL): array
    {
      if(array_key_exists("authhash", $loginData) && array_key_exists("scriptversion", $data) && array_key_exists("chia", $data)){
        try{
          $sql = $this->db_api->execute("UPDATE nodes SET scriptversion = ?, chiaversion = ?, chiapath = ? WHERE nodeauthhash = ?", array($data["scriptversion"], $data["chia"]["version"], $data["chia"]["path"], $this->encryption_api->encryptString($loginData["authhash"])));

          return array("status" =>0, "message" => "Successfully updated version.");
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("002");
      }
    }

    /**
     * Sets the querieng nodes update status for the frontend.
     * Function made for: Node client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "status" : ["Current script update status" - array] }
     * @param  array $loginData                { "authhash" : [Node's authhash] }
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function nodeUpdateStatus(array $data, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql/*->fetchAll(\PDO::FETCH_ASSOC)*/[0]["id"];

        return array("status" => 0, "message" => "Successfully queried node update status.", "data" => array("nodeid" => $nodeid, "status" => $data));
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }

    /**
     * Checks if there is an update available for a node.
     * Function made for: Web client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { "nodeid" : ["A specific nodes id"] }
     * @param  array $loginData                { NULL } No loginData needed to query this function.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" => ["Requested infos - array"]}
     */
    public function checkUpdatesAndChannels(array $data = []): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        $updatepackagepath = "https://files.chiamgmt.edtmair.at/client/versions.json";

        $browser = new Browser();
        $client_updates = $browser->get($updatepackagepath)->then(
          function ($client_updates_returned){
            return json_decode($client_updates_returned->getBody(), true);
          },
          function (\Exception $e) use(&$resolve){
            return $resolve($this->logging_api->getErrormessage("checkUpdatesAndChannels", "001", $e));
          }
        );

        $overall_chia_data = Promise\resolve($this->chia_overall_api->getOverallChiaData());

        if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])){
          $node_data = Promise\resolve((new DB_Api())->execute("SELECT id, hostname, scriptversion, updatechannel, chiaversion FROM nodes WHERE authtype = 2 AND id = ?", array($data["nodeid"])));
        }else{
          $node_data = Promise\resolve((new DB_Api())->execute("SELECT id, hostname, scriptversion, updatechannel, chiaversion FROM nodes WHERE authtype = 2", array()));
        }
        
        Promise\all([$client_updates, $overall_chia_data, $node_data])->then(function($all_returned) use(&$resolve){
          $version_file_data = $all_returned[0];
          $overall_chia_data = $all_returned[1];
          $node_data = $all_returned[2]->resultRows;

          $returndata = [];
          $returndata["updateinfos"] = [];
          foreach($node_data AS $arrkey => $nodedata){
            $returndata["updateinfos"][$nodedata["id"]] = $nodedata;

            $returndata["updateinfos"][$nodedata["id"]]["updateavailable"] =  2;
            if(array_key_exists($nodedata["updatechannel"], $version_file_data)){
              if(!is_null($nodedata["scriptversion"])) $returndata["updateinfos"][$nodedata["id"]]["updateavailable"] = (int)version_compare($nodedata["scriptversion"], $version_file_data[$nodedata["updatechannel"]][array_key_first($version_file_data[$nodedata["updatechannel"]])]);
              $returndata["updateinfos"][$nodedata["id"]]["remoteversion"] = $version_file_data[$nodedata["updatechannel"]][array_key_first($version_file_data[$nodedata["updatechannel"]])];
            }
  
            $returndata["updateinfos"][$nodedata["id"]]["chiaupdateavail"] =  2;
            if(array_key_exists("data", $overall_chia_data) && array_key_exists("blockchain_version", $overall_chia_data["data"])){
              if(!is_null($nodedata["chiaversion"]) && !empty($nodedata["chiaversion"])) $returndata["updateinfos"][$nodedata["id"]]["chiaupdateavail"] = (int)version_compare(trim($nodedata["chiaversion"]), trim($overall_chia_data["data"]["blockchain_version"]));
            }
          }

          $resolve(array("status" => 0, "message" => "Successfully loaded all requested data.", "data" => $returndata));
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("checkUpdatesAndChannels", "002", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Updates the branch of a node client's scripts.
     * Function made for: Web GUI
     * @throws Exception $e     Throws an exception on db errors.
     * @param  array  $data     { "branch" : [The node client's target branch], "nodeid" : [The target node's id]}
     * @param  array $loginData { NULL } No $loginData needed to query this function.
     * @return array            {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data" : ["branch" => [branchname], "nodeid" => [nodeid]]}
     */
    public function updateNodeBranch(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("branch", $data) && array_key_exists("nodeid", $data)){
          $allowedbranches = array("dev","staging","main");
          if(in_array($data["branch"], $allowedbranches)){
            $set_updatechannel = Promise\resole((new DB_Api())->execute("UPDATE nodes SET updatechannel = ? WHERE id = ?", array($data["branch"],$data["nodeid"])));
            $set_updatechannel->then(function($set_updatechannel_returned) use(&$resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully updated branch for node {$data["nodeid"]} to {$data["branch"]}.", "data" => ["branch" => $data["branch"], "nodeid" => $data["nodeid"]]));
            })->otherwise(function(\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("updateNodeBranch", "001", $e));
            });
          }else{
            $resolve($this->logging_api->getErrormessage("updateNodeBranch", "002", "Branch {$data["branch"]} not allowed."));
          }
        }else{
          $resolve($this->logging_api->getErrormessage("updateNodeBranch", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Queries the current states of the chia nodes (Node UP/DOWN, Services running).
     * It refreshes the history data of the nodes and queries the current service stats from all clients.
     * Function made for: CronJob / Backend Client -> Chia Nodes
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } No data needed to query this function.
     * @param  array $loginData                { NULL } No loginData needed to query this function.
     * @param  Object $server     An instance to the Webscoket server to be able to communicate with the nodes directly.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function queryNodesServicesStatus(array $data = [], array $loginData = NULL, Object $server = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData, $server){
        $client_nodes = Promise\resolve($this->getConfiguredNodes(["nodetypenum" => [3,4,5]]));
        $client_nodes->then(function($client_nodes_returned) use(&$resolve, $server, $loginData){
          if(!is_null($server)){
            $activeSubscriptions = Promise\resolve($server->getActiveSubscriptions($loginData));
          }else{
            $activeSubscriptions = Promise\resolve((new WebSocket_Api())->sendToWSS("getActiveSubscriptions"));
          }
  
          $activeSubscriptions->then(function($activeSubscriptions_returned) use(&$resolve, $client_nodes_returned){
            $activeSubscriptions_returned = $activeSubscriptions_returned["getActiveSubscriptions"];
           
            if(array_key_exists("data", $activeSubscriptions_returned) && array_key_exists("data", $client_nodes_returned)){
              foreach($client_nodes_returned["data"] AS $nodeid => $nodedata){
                $found = false;
                foreach(explode(",",$nodedata["nodetype"]) AS $arrkey => $nodetype){
                  if(array_key_exists($nodetype, $activeSubscriptions_returned["data"])){
                    foreach($activeSubscriptions_returned["data"][$nodetype] AS $connectionnr => $conninfo){
                      if($conninfo["nodeid"] == $nodeid){
                        $found = true;

                        Promise\resolve($this->setNodeUpDown(["nodeid" => $nodeid, "updown" => 1]));

                        if(!is_null($server)){
                          $querydata = ["data" => ["get_chia_status" => ["status" => 0,"message" => "Query chia wallet/farmer/harvester running status.","data"=> []]],"nodeinfo" => ["authhash" => $nodedata["nodeauthhash"]]];
                          Promise\resolve($server->messageSpecificNode($querydata));
                        }
                        break;
                      }
                    }
                  }
                  if($found) break;
                }

                if(!$found){
                  $promises = [
                    Promise\resolve($this->setNodeUpDown(["nodeid" => $nodeid, "updown" => 0])),
                    Promise\resolve($this->updateChiaStatus(["nodeid" => $nodeid, "farmer" => ["status" => 1], "wallet" => ["status" => 1], "harvester" => ["status" => 1]]))
                  ];
                }
              }
            }
            
            $resolve(array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => ["onlinestatus" => 0]));
          });
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Changes the Nodes Upstatus in the database. Informs the frontend about changes.
     * 0 = Node DOWN, 1 = Node UP
     * Function made for: Backendclient
     * @param array $data   { "nodeid" : [The systems node id], "updown" : [0=Node Down/1=Node UP] }
     * @return array        Returnes the current status information stored in the database.
     */
    public function setNodeUpDown(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){       
        if(array_key_exists("nodeid", $data) && is_numeric($data["nodeid"]) && $data["nodeid"] > 0 && array_key_exists("updown", $data) && is_numeric($data["updown"]) && ($data["updown"] == 0 || $data["updown"] == 1)){
          $nodes_up_status = Promise\resolve((new DB_Api())->execute("SELECT id, nodeid, onlinestatus, lastreported FROM nodes_up_status WHERE nodeid = ? ORDER BY firstreported DESC LIMIT 1", array($data["nodeid"])));
          $nodes_up_status->then(function($nodes_up_status_returned) use(&$resolve, $data){
            $nodes_up_status_returned = $nodes_up_status_returned->resultRows;
            if(!array_key_exists(0, $nodes_up_status_returned) || $nodes_up_status_returned[0]["onlinestatus"] != $data["updown"]){
              $set_node_up = Promise\resolve((new DB_Api())->execute("INSERT INTO nodes_up_status (id, nodeid, onlinestatus, firstreported, lastreported) VALUES(NULL, ?, ?, current_timestamp(), current_timestamp())", array($data["nodeid"], $data["updown"])));
              $set_node_up->otherwise(function (\Exception $e) use(&$resolve){
                return $resolve($this->logging_api->getErrormessage("setNodeUpDown", "003", $e));
              });
            }
            if(count($nodes_up_status_returned) == 1){
              $update_node_laston = Promise\resolve((new DB_Api())->execute("UPDATE nodes_up_status SET lastreported = current_timestamp() WHERE id = ?", array($nodes_up_status_returned[0]["id"])));
              $update_node_laston->otherwise(function (\Exception $e) use(&$resolve){
                return $resolve($this->logging_api->getErrormessage("setNodeUpDown", "004", $e));
              });
            }
            
            $setNodesStatus = Promise\resolve((new Chia_Infra_Sysinfo_Api())->setAllNodesSystemAndServicesUpStatus($data));
            $setNodesStatus->then(function($setNodesStatus_returned) use(&$resolve){
              if($setNodesStatus_returned["status"] == 0){
                $resolve(array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => []));
              }else{
                $resolve($setNodesStatus_returned);
              }
            });
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("setNodeUpDown", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("setNodeUpDown", "002"));
        }
      };
      
      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Changes the Nodes Services Upstatus in the database. Informs the frontend about changes.
     * Function made for: Node Client
     * Data sent in status: 
     * Service Status: 0 = Service DOWN, 1 = Service UP, false = Service DOWN, true = Service UP
     * Service ID's: 3 = Farmer, 4 = Harvester, 5 = Wallet
     * @param array $data         { "nodeid" : [The systems node id], "wallet" : { "status" => [0=Service Down/1=Service UP] }, "farmer" : { "status" => [0=Service Down/1=Service UP] }, "harvester" : { "status" => [0=Service Down/1=Service UP] }}
     * @param array $loginData   { "authhash" => [The node's authhash] } *Must be set when no nodeid is set in $data  
     * @return array              Returnes the current status information stored in the database.
     */
    public function updateChiaStatus(array $data, array $loginData = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("wallet", $data) && array_key_exists("farmer", $data) && array_key_exists("harvester", $data)){
          $nodeid = NULL;
          if(array_key_exists("nodeid", $data) && is_int($data["nodeid"]) && $data["nodeid"] > 0){
            $nodeid = Promise\resolve($data["nodeid"]);
          }else if(!is_null($loginData) && array_key_exists("authhash", $loginData) && is_string($loginData["authhash"])){
            $nodeid = Promise\resolve((new DB_Api())->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"]))));
          }
          
          $nodeid->then(function($nodeid_returned) use(&$resolve, $data){
            if(!is_numeric($nodeid_returned)) $nodeid_returned = $nodeid_returned->resultRows[0]["id"];

            if(!is_null($nodeid_returned) && $nodeid_returned > 0){ 
              $chia_nodes = Promise\resolve((new DB_Api())->execute("SELECT nt.nodeid, n.hostname, n.nodeauthhash, nta.code AS serviceid, LOWER(nta.description) AS description, nss.id AS curr_service_state_id, nss.servicestate, nss.firstreported, nss.lastreported
                                                                      FROM nodetype nt
                                                                      LEFT JOIN nodes n ON n.id = nt.nodeid
                                                                      LEFT JOIN LATERAL (
                                                                        SELECT id, nodeid, serviceid, servicestate, firstreported, lastreported FROM nodes_services_status WHERE nodeid = n.id AND serviceid = nt.code ORDER BY firstreported DESC LIMIT 1
                                                                      ) AS nss
                                                                      ON nss.serviceid = nt.code
                                                                      JOIN nodetypes_avail nta ON nta.code = nt.code
                                                                      WHERE nt.code IN (3,4,5) AND n.id = ?", array($nodeid_returned)));

              $chia_nodes->then(function($chia_nodes_returned) use(&$resolve, $data){
                $chia_nodes_returned = $chia_nodes_returned->resultRows;
                
                if(count($chia_nodes_returned) > 0){
                  foreach($chia_nodes_returned AS $arrkey => $savedstates){
                    if(is_numeric($data[$savedstates["description"]])){
                      $reported_service_state = intval(!boolval($data[$savedstates["description"]]));
                    }else if(is_bool($data[$savedstates["description"]])){
                      $reported_service_state = intval(boolval($data[$savedstates["description"]]));
                    }else if(is_array($data[$savedstates["description"]])){
                      $reported_service_state = intval(!boolval($data[$savedstates["description"]]["status"]));
                    }
    
                    if((is_numeric($savedstates["servicestate"]) || is_null($savedstates["servicestate"])) && array_key_exists($savedstates["description"], $data) && (($reported_service_state != $savedstates["servicestate"]) || is_null($savedstates["servicestate"]))){
                      $insert_service_state = Promise\resolve((new DB_Api())->execute("INSERT INTO nodes_services_status (id, nodeid, serviceid, servicestate, firstreported, lastreported) VALUES(NULL, ?, ?, ?, current_timestamp(), current_timestamp())", array($nodeid, $savedstates["serviceid"], $reported_service_state)));
                      $insert_service_state->otherwise(function(\Exception $e) use(&$resolve){
                        return $resolve($this->logging_api->getErrormessage("updateChiaStatus", "006", $e));
                      });
                    }
                    if(is_numeric($savedstates["servicestate"])){
                      $update_lastreported = Promise\resolve((new DB_Api())->execute("UPDATE nodes_services_status SET lastreported = current_timestamp() WHERE id = ?", array($savedstates["curr_service_state_id"])));
                      $update_lastreported->otherwise(function(\Exception $e) use(&$resolve){
                        return $resolve($this->logging_api->getErrormessage("updateChiaStatus", "007", $e));
                      });
                    }
                  }
                }else{
                  $resolve($this->logging_api->getErrormessage("updateChiaStatus", "001"));
                }

                $set_current_nodes_states = Promise\resolve((new Chia_Infra_Sysinfo_Api())->setAllNodesSystemAndServicesUpStatus($data));
                $set_current_nodes_states->then(function($set_current_nodes_states_returned) use(&$resolve){
                  $resolve(array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => []));
                });
              })->otherwise(function(\Exception $e) use(&$resolve){
                $resolve($this->logging_api->getErrormessage("updateChiaStatus", "005", $e));
              });
            }else{
              $resolve($this->logging_api->getErrormessage("updateChiaStatus", "002"));
            }
          })->otherwise(function(\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("updateChiaStatus", "004", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("updateChiaStatus", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the current up down states from the node and its services.
     * Function made for: Web/App-Client
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { nodeid : [int], nodetypes : [string|array] } 
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function getCurrentChiaNodesUPAndServiceStatus(array $data = []): object
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){    
        $where_statement_string = "";
        $statement_array = [];
        if(array_key_exists("nodeid", $data)){
          $where_statement_string .= "AND n.id = ?";
          $statement_array[] = $data["nodeid"];
        }

        $nodes_infos = Promise\resolve((new DB_Api())->execute("SELECT nus.id AS node_up_down_id, nss.id AS service_running_status_id, nt.nodeid, n.hostname, nus.onlinestatus, nus.firstreported AS node_firstreported, nus.lastreported AS node_lastreported, nta.description, nss.serviceid, nss.servicestate, nss.firstreported AS service_firstreported, nss.lastreported AS service_lastreported
                                                                FROM nodetype nt
                                                                INNER JOIN nodes n ON n.id = nt.nodeid
                                                                INNER JOIN LATERAL (
                                                                  SELECT id, nodeid, onlinestatus, firstreported, lastreported FROM nodes_up_status WHERE nodeid = n.id ORDER BY firstreported DESC, lastreported DESC LIMIT 1
                                                                ) AS nus ON nus.nodeid = nt.nodeid
                                                                INNER JOIN LATERAL (
                                                                  SELECT id, nodeid, serviceid, servicestate, firstreported, lastreported FROM nodes_services_status WHERE nodeid = n.id AND serviceid = nt.code ORDER BY firstreported DESC, lastreported DESC LIMIT 1
                                                                ) AS nss
                                                                INNER JOIN nodetypes_avail nta ON nta.code = nt.code
                                                                WHERE nt.code IN (3,4,5) {$where_statement_string}", $statement_array));

        $nodes_infos->then(function($nodes_infos_returned) use(&$resolve){
          $returndata = [];
          foreach($nodes_infos_returned->resultRows AS $arrkey => $serviceinfo){
            if(!array_key_exists($serviceinfo["nodeid"], $returndata)){
              $returndata[$serviceinfo["nodeid"]] = [
                "nodeinfo" => [
                  "nodeid" => $serviceinfo["nodeid"],
                  "hostname" => $serviceinfo["hostname"]
                ],
                "onlinestatus" => [
                  "entry_id" => $serviceinfo["node_up_down_id"],
                  "status" => $serviceinfo["onlinestatus"],
                  "node_firstreported" => $serviceinfo["node_firstreported"],
                  "node_lastreported" => $serviceinfo["node_lastreported"]
                ],
                "services" => []
              ];
            }
            $returndata[$serviceinfo["nodeid"]]["services"][$serviceinfo["serviceid"]] = [
              "entry_id" => $serviceinfo["service_running_status_id"],
              "servicestate" => $serviceinfo["servicestate"],
              "service_desc" => $serviceinfo["description"],
              "service_firstreported" => $serviceinfo["service_firstreported"],
              "service_lastreported" => $serviceinfo["service_lastreported"]
            ];
          }
  
          $resolve(array("status" => 0, "message" => "Succesfully loaded active subscriptions and upstatus.", "data" => $returndata));
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getCurrentChiaNodesUPAndServiceStatus", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
