<?php
namespace ChiaMgmt\WebSocketServer;

use React\EventLoop\Loop;
use React\Promise;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use ChiaMgmt\Login\Login_Api;
use ChiaMgmt\RequestHandler\RequestHandler_Api;
use ChiaMgmt\Sites\Sites_Api;
use ChiaMgmt\Logging\Logging_Api;
use ChiaMgmt\System\System_Api;

/**
 * The ChiaWebSocketServer class contains the websocket server itself and it's data processing functions.
 * This class is mainly used by the web(/app) client.
 * @version 0.1.1
 * @author OLED1 - Oliver Edtmair
 * @since 0.1.0
 * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
 */
class ChiaWebSocketServerNew implements MessageComponentInterface {
  /**
  * The local connected client object.
  * @var SplObjectStorage
  */
  private $clients;
  /**
   * The local connected client array.
   * @var array
   */
  private $users;
  /**
   * The local active connected clients array.
   * @var array
   */
  private $subscription;
  /**
   * Holds an instance to the Login Class.
   * @var Login_Api
   */
  private $login_api;
  /**
   * The local active request clients array.
   * @var array
   */
  private $requests;
  /**
   * Holds an instance to the Sites Class.
   * @var Sites_Api
   */
  private $sites_api;
  /**
   * Holds an array of available system enabled sites.
   * @var array
   */
  private $sites_data;
  /**
   * Holds an instance to the Logging Class.
   * @var Logging_Api
   */
  private $logging;
  /**
   * @var
   */
  private $eventLoop;
  /**
   * An array which includes all informated connected nodes.
   * @var array
   */
  private $connectedNodesInformed;

  /**
   * Initialises the needed and above stated private variables.
   */
  public function __construct() {
    echo "[{$this->getDate()}] [----------] INFO SERVER: Starting websocket server.\n";
    echo "[{$this->getDate()}] [----------] INFO SERVER: Waiting for requests.\n";
    $this->clients = new \SplObjectStorage;
    $this->users = [];
    $this->subscription = [];
    $this->requests = [];
    $this->connectedNodesInformed = [];

    $this->login_api = new Login_Api();
    $this->logging = new Logging_Api($this, $this);
    $this->sites_api = new Sites_Api();
  }

  /**
   * This websocketserver specific function will be called as soon as a client opens a new connection to the server.
   * @param  ConnectionInterface $conn  Websocket specific information about the current client connection.
   */
  public function onOpen(ConnectionInterface $conn) {
      // Store the new connection to send messages to later
      $this->clients->attach($conn);
      $this->users[$conn->resourceId] = $conn;

      $avilable_sites = Promise\resolve($this->sites_api->getSiteInfos(["siteid" => NULL]));
      $avilable_sites->then(function($avilable_sites_returned){
        echo "[{$this->getDate()}] [----------] INFO SERVER: Updated available sites.\n";
        $this->sites_data = $avilable_sites_returned["data"];
      });

      echo "[{$this->getDate()}] [----------] INFO SERVER: New connection ({$conn->resourceId}).\n";
  }

  /**
   * This websocketserver specific function will be called as soon as a client sends in an action to the server.
   * If the client sent in a valid json array, the request will be processed using the RequestHandler.
   * It will be checked if the client is allowed to connect and send in or query information to/from the api.
   * After that the requester gets an information via websocket and the websocket client if a user is viewing the site for which the data is meant for.
   * @param  ConnectionInterface $from    Websocket specific information about the current client connection.
   * @param  array $msg                   The message as json which was sent in from the client.
   */
  public function onMessage(ConnectionInterface $from, $msg) {
    Loop::get()->addTimer(0, function() use($from, $msg){
      $data = json_decode($msg, true);
      $request_id = substr(str_shuffle(str_repeat($x='0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ', ceil(10/strlen($x)) )),1,10);
      echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP GENERAL: Added connection {$from->resourceId} to event loop.\n";
      
      if(is_array($data) && array_key_exists("node" , $data) && array_key_exists("request" , $data)
      && array_key_exists("data" , $data["request"]) && array_key_exists("backendInfo" , $data["request"])){
        //Setting variables for processing
        $nodeInfo = $data["node"];
        $request = $data["request"];
        $loginData = $request["logindata"];
        $reqData = $request["data"];
        $backendInfo = $request["backendInfo"];
        $nodeip = "localhost";
        if(array_key_exists("X-Forwarded-For", $from->httpRequest->getHeaders())) $nodeip = $from->httpRequest->getHeaders()['X-Forwarded-For'][0];

        $requestHandler = new RequestHandler_Api($this);

        if(is_array($loginData) && is_array($data["node"]["nodeinfo"])){
          $request_login = Promise\resolve($requestHandler->requesterLogin($nodeip, $loginData, $data["node"]["nodeinfo"]));
          $request_login->then(function($request_login_returned) use($requestHandler, $request_id, $from, $msg, $data, $request, $nodeInfo, $loginData, $reqData, $backendInfo, $nodeip){
            echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP REQUEST_LOGIN: Connection request returned: " . json_encode($request_login_returned) . "\n";

            if($request_login_returned["status"] == "010005006" || $request_login_returned["status"] == "010005007" ||
              $request_login_returned["status"] == "010005012" || $request_login_returned["status"] == "010005002" ||
              $request_login_returned["status"] == "010005011" || $request_login_returned["status"] == "010005013"
            ){
              echo "[{$this->getDate()}] [{$request_id}] EVENTLOOP INFO: Send new connection request to frontend.\n";
              $request_login_returned["data"]["resid"] = $from->resourceId;
              if($request_login_returned["status"] == "010004002") $this->requests[$request_login_returned["data"]["authhash"]] = $request_login_returned["data"];
              else $this->requests[$request_login_returned["data"]["authhash"]] = $request_login_returned["data"];

              $message_frontend_clients = Promise\resolve($this->messageFrontendClients(array("siteID" => 2), $this->requestHandler->processConnectionRequest($this->requests), $request_id));
              $message_frontend_clients->then(function($message_frontend_clients_returned) use($request_id, $from){
                echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_PROCESSING: Successfully sent message to client {$from->resourceId}.\n";
              });
            }

            if(array_key_exists("status", $request_login_returned) && $request_login_returned["status"] == 0){
              foreach(explode(",", $request_login_returned["nodeinfo"]["type"]) AS $arrkey => $type){
                foreach(explode(",", $type) AS $arrkey => $this_type){
                  $siteID = NULL;
                  if(trim($this_type) == "webClient" && array_key_exists(trim($this_type), $this->subscription) &&
                    array_key_exists($from->resourceId, $this->subscription[trim($this_type)]) && array_key_exists("siteID", $this->subscription[trim($this_type)][$from->resourceId]))
                    $siteID = $this->subscription[trim($this_type)][$from->resourceId]["siteID"];
      
                  $this->subscription[trim($this_type)][$from->resourceId] = $request_login_returned["nodeinfo"]["nodedata"];
      
                  if(!is_null($siteID)) $this->subscription[trim($this_type)][$from->resourceId]["siteID"] = $siteID;
                }

                if($type != "backendClient" && !array_key_exists($type, $this->subscription) || !array_key_exists($from->resourceId, $this->subscription[$type])){
                  echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_CONNECTION: Newly connected {$type} client connected.\n";
                  if(!in_array($from->resourceId, $this->connectedNodesInformed)){
                    array_push($this->connectedNodesInformed, $from->resourceId);
                    //Set Node to UP
                    $set_node_up_down = $this->requestHandler->processRequest([], ['namespace' => 'ChiaMgmt\Nodes\Nodes_Api', 'method' => 'setNodeUpDown'], ["nodeid" => $requesterLogin["nodeinfo"]["nodedata"]["nodeid"], "updown" => 1]); 
                    $this->messageFrontendClients([], $set_node_up_down, $from->resourceId, ['namespace' => 'ChiaMgmt\Nodes\Nodes_Api'], $request_id);
                  }
                }else{
                  echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_CONNECTION: Detected frontend/backend Client or existing connection.\n";
                }

                echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_CONNECTION: New backendRequest from {$nodeInfo["nodeinfo"]["hostname"]}.\n";
                echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_CONNECTION: Requested socketaction: {$nodeInfo["socketaction"]} from {$from->resourceId}.\n";
                echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_CONNECTION: Transmitted data {$msg}\n";

                switch($nodeInfo["socketaction"]){
                  case "wssonlinestatus":
                    $this->users[$from->resourceId]->send(json_encode(array($nodeInfo["socketaction"] => array("status" => 0, "message" => "Websocket server ready to rumble.", "data" => getmypid()))));
                    break;
                  case "backendRequest": //Returns the requested value to all frontend Clients which are viewing a specific site
                    if(is_array($loginData) && is_array($backendInfo) && is_array($reqData)){
                      $this_req = Promise\resolve($requestHandler->processRequest($loginData, $backendInfo, $reqData, $this));
                      $this_req->then(function($this_req_returned) use($loginData, $from, $backendInfo, $request_id){
                        $message_frontend_clients = Promise\resolve($this->messageFrontendClients($loginData, $this_req_returned, $from->resourceId, $backendInfo, $request_id));
                        $message_frontend_clients->then(function($message_frontend_clients_returned) use($request_id, $from){
                          echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_PROCESSING: Successfully sent message to client {$from->resourceId}.\n";
                        });
                      });
                    }else{
                      $this->users[$from->resourceId]->send(json_encode(array($backendInfo['method'] => array("status" => 1, "message" => "One of the required arrays has a wrong datatype."))));
                    }
                    break;
                  case "ownRequest": //Returns the requested value just to the requesters open socket
                    if(is_array($loginData) && is_array($backendInfo) && is_array($reqData)){
                      $this_req = Promise\resolve($requestHandler->processRequest($loginData, $backendInfo, $reqData, $this));
                      $this_req->then(function($this_req_returned) use($from, $request_id){
                        $this->users[$from->resourceId]->send(json_encode($this_req_returned));
                        echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_PROCESSING: Successfully sent message to client {$from->resourceId}.\n";
                      });
                    }else{
                      $this->users[$from->resourceId]->send(json_encode(array($backendInfo['method'] => array("status" => 1, "message" => "One of the required arrays has a wrong datatype."))));
                    }
                    break;
                  case "updateFrontendViewingSite": //Updates the viewing site for a user connected to a web client
                    $this_req = Promise\resolve($requestHandler->processUpdateFrontendViewingSite($loginData, $this->subscription, $reqData, $from->resourceId));
                    $this_req->then(function($this_req_returned) use($from, $request_id){
                      if(array_key_exists("data", $this_req_returned["updateFrontendViewingSite"])){
                        $this->subscription = $this_req_returned["updateFrontendViewingSite"]["data"];
                        unset($this_req_returned["updateFrontendViewingSite"]["data"]);
                      }
                      $this->users[$from->resourceId]->send(json_encode($this_req_returned));
                      echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CLIENT_PROCESSING: Successfully set frontend viewing site for {$from->resourceId}.\n";
                    });
                    break;
                  case "getActiveSubscriptions": //Returns the current subscriptions for all connected WS Nodes
                    echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP ACT_SUB: Querying active subscriptions.\n";
                    $this_req = Promise\resolve($requestHandler->processGetActiveSubscriptions($loginData, $this->subscription));
                    $this_req->then(function($this_req_returned) use($from, $request_id){
                      $this->users[$from->resourceId]->send(json_encode($this_req_returned));
                      echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP ACT_SUB: Processed and returned active subscriptions.\n";
                    });
                    break;
                  case "getActiveRequests": //Returns the current subscriptions for all connected WS Nodes
                    echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP ACT_REQ: Querying active requests.\n";
                    $this_req = Promise\resolve($requestHandler->processGetActiveRequests($loginData, $this->requests));
                    $this_req->then(function($this_req_returned) use($from, $request_id){
                      $this->users[$from->resourceId]->send(json_encode($this_req_returned));
                      echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP ACT_SUB: Processed and returned active requests.\n";
                    });
                    break;
                  case "queryCronData":
                    echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CRON_PROCESSING: Querying cron data.\n";
                    if(is_array($loginData) && is_array($backendInfo) && is_array($reqData)){
                      $this_req = Promise\resolve($requestHandler->processCronRequest($loginData, $backendInfo, $reqData, $nodeInfo, $this));
                      $this_req->then(function($this_req_returned) use($from, $request_id, $backendInfo){
                        $message_frontend_clients = Promise\resolve($this->messageFrontendClients(array("siteID" => 3), $this_req_returned, NULL, NULL, $request_id));
                        $message_frontend_clients->then(function($message_frontend_clients_returned) use($request_id, $from, $this_req_returned){
                          echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CRON_PROCESSING: Successfully sent message to frontend clients." . json_encode($this_req_returned) . ". \n";
                        });
                        echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP CRON_PROCESSING: Cronjob execution finished. It took {$this_req_returned["cronJobExecution"]["data"]["duration"]} seconds.\n";
                      });

                      $this->users[$from->resourceId]->send(json_encode(array("cronJobExecution" => array("status" => 0, "message" => "Successfully queried system background jobs. They will be processed soon.", "data" => date("Y-m-d H:i:s")))));
                    }else{
                      $this->users[$from->resourceId]->send(json_encode(array($backendInfo['method'] => array("status" => 1, "message" => "One of the required arrays has a wrong datatype."))));
                    }
                    break;
                  default:
                    $this->users[$from->resourceId]->send(json_encode(array("status" => 1, "message" => "Socketaction " . $nodeInfo["socketaction"] . " not known.")));
        
                }
              }
            }else{
              $this->users[$from->resourceId]->send(json_encode(array("loginStatus" => $request_login_returned)));
            }
          });

        }else{
          $this->users[$from->resourceId]->send(json_encode(array("notallinformationstated" => array("status" => 1, "message" => "Not all information stated."))));
        }
      }else{
        $this->users[$from->resourceId]->send(json_encode(array("notallinformationstated" => array("status" => 1, "message" => "Not all information stated."))));
      }

      $unsubscribe_backend = Promise\resolve($this->unsubscribeAllBackendClients());
      $unsubscribe_backend->then(function($unsubscribe_backend_returned){
        echo "[{$this->getDate()}] [----------] INFO SERVER: Unsubscribed backend clients.\n";
      });

      echo "[{$this->getDate()}] [{$request_id}] INFO EVENTLOOP GENERAL: Connection {$from->resourceId} request processed.\n";
    });
  }

  /**
   * This websocketserver specific function will be called as soon as a client closes the connection to the server.
   * This function removes the client connection from the local array.
   * @param  ConnectionInterface $conn  Websocket specific information about the current client connection.
   */
  public function onClose(ConnectionInterface $conn) {
    $resolver = function (callable $resolve, callable $reject, callable $notify) use($conn){
      // The connection is closed, remove it, as we can no longer send it messages
      $this->clients->detach($conn);
      unset($this->users[$conn->resourceId]);

      $found_client = false;
      foreach($this->subscription AS $clientType => $clientConnections){
        if(array_key_exists($conn->resourceId, $clientConnections) && !is_null($clientConnections[$conn->resourceId])){
          $found_client = true;
          $message = "{$clientType}connection {$conn->resourceId} has disconnected.\n";
        }
      }

      if($found_client){
        (new Logging_Api($this, $this))->getErrormessage("onClose", "001", $message);
        unset($this->connectedNodesInformed[$conn->resourceId]);

        $changed = false;
        $types = [];
        foreach($this->subscription AS $type => $connections){
          foreach($connections AS $conid => $values){
            if($conid == $conn->resourceId){
              if(!$changed) {
                //Set Node to DOWN
                $set_node_up_down = Promise\resolve((new RequestHandler_Api())->processRequest([], ['namespace' => 'ChiaMgmt\Nodes\Nodes_Api', 'method' => 'setNodeUpDown'], ["nodeid" => $this->subscription[$type][$conid]["nodeid"], "updown" => 0])); 
                $set_node_up_down->then(function($set_node_up_down_returned, $conn){
                  $message_frontend_clients = Promise\resolve($this->messageFrontendClients([], $set_node_up_down_returned, $conn->resourceId, ['namespace' => 'ChiaMgmt\Nodes\Nodes_Api']));
                  $message_frontend_clients->then(function($request_id, $message_frontend_clients_returned){
                    echo "[{$this->getDate()}] [----------] INFO SERVER CLIENT_CLOSED_CONN: Successfully sent message to client {$conid}\n";
                  });
                });
              }          
              unset($this->subscription[$type][$conid]);
              array_push($types, $type);
              $changed = true;
            }
          }
        }
        foreach($this->requests AS $authhash => $infos){
          if($infos["resid"] == $conn->resourceId){
            unset($this->requests[$authhash]);
            $changed = true;
          }
        }
      }else{
        $message = "Backendconnection {$conn->resourceId} has disconnected.\n";
      }
    };
  }

  /**
   * This websocketserver specific function will be called as soon as an error happens to the websocket server.
   * The errors will be logged to the server's logging file.
   * @param  ConnectionInterface $conn  Websocket specific information about the current client connection.
   */
  public function onError(ConnectionInterface $conn, \Exception $e) {
      echo "[{$this->getDate()}] [----------] CRITICAL SERVER: An error has occurred: {$e->getMessage()}.\n";
      (new Logging_Api($this, $this))->getErrormessage("onError", "001", $e->getMessage());
      //Do not close the connection to client on exception, only log.
      //$conn->close();
  }

  /**
   * Returns a list of active subscriptions.
   * @param  array  $loginData  The clients logindata to be sure the client is logged in.
   * @return array              The requested subscriptions list.
   */
  public function getActiveSubscriptions(array $loginData): object
  {
    return (new RequestHandler_Api())->processGetActiveSubscriptions($loginData, $this->subscription);
  }

  /**
   * Retuns the current formatted date.
   * This function is only meant for server logging in CLI.
   * @return DateTime The current date.
   */
  private function getDate(){
    return date("d.m.Y H:i:s");
  }

  /**
   * Sends a message to all web/app-frontend clients which are viewing a specific site.
   * @param  array  $loginData    The clients logindata.
   * @param  array  $datatosend   The json formatted data which should be sent to the frontend client(s).
   * @param  int $mycon           The connection ID of the sending client to be able to send back an info.
   * @param  array $backendInfo   The backend related information for data processing like namespace- and functionname.
   */
  public function messageFrontendClients(array $loginData, array $datatosend, int $mycon = NULL, array $backendInfo = NULL, string $reqID = "----------"){
    $resolver = function (callable $resolve, callable $reject, callable $notify) use($loginData, $datatosend, $mycon, $backendInfo, $reqID){

      echo "[{$this->getDate()}] [----------] INFO SERVER MSG_CLIENTS: Got command message frontend clients " . json_encode($datatosend) . "\n";

      if(is_null($backendInfo) && array_key_exists("siteID", $loginData) && $loginData["siteID"] > 0){
        $siteID = $this->sites_data["by-id"][$loginData["siteID"]]["sitestoinform"];
      }else if(!is_null($backendInfo) && array_key_exists("namespace", $backendInfo) && $backendInfo["namespace"] != ""){
        $siteID = $this->sites_data["by-namespace"][$backendInfo["namespace"]]["sitestoinform"];
      }else{
        echo "[{$this->getDate()}] [{$reqID}] WARNING SERVER MSG_CLIENTS: No siteid can be found.\n";
        return;
      }

      if(!array_key_exists("loginStatus", $datatosend) && array_key_exists("webClient", $this->subscription)){
        foreach($this->subscription["webClient"] AS $connectionid => $condetails){
          if(array_key_exists("siteID", $condetails) && array_key_exists("userid", $condetails) &&
            in_array($condetails["siteID"], $siteID)){
  
            echo "[{$this->getDate()}] [{$reqID}] INFO SERVER MSG_CLIENTS: User {$condetails["userid"]} is viewing site {$condetails["siteID"]} so he will be informed using socket $connectionid.\n";
            if(array_key_exists($connectionid, $this->users)){
              echo "[{$this->getDate()}] [{$reqID}] INFO SERVER MSG_CLIENTS: Informing $connectionid about changes.\n";
              $this->users[$connectionid]->send(json_encode($datatosend));
            }else{
              echo "[{$this->getDate()}] [{$reqID}] WARNING SERVER MSG_CLIENTS: Connection $connectionid is not existing.\n";
            }
          }else{
            echo "[{$this->getDate()}] [{$reqID}] INFO SERVER MSG_CLIENTS: User {$condetails["userid"]} is not watching a site which satisfies one of these sites: " . json_encode($siteID) . ".\n";
          }
        }
      }else if(array_key_exists("loginStatus", $datatosend)){
        foreach($this->subscription["webClient"] AS $connectionid => $condetails){
          if($loginData["userid"] == $condetails["userid"]){
            echo "[{$this->getDate()}] [{$reqID}] INFO SERVER MSG_CLIENTS: User {$condetails["userid"]} logged in. Informing using socket $mycon.\n";
            $this->users[$mycon]->send(json_encode($datatosend));
            break;
          }
        }
      }
      $resolve("Sent message to client with ID {$mycon}.");
    };

    $canceller = function () {
      throw new Exception('Promise cancelled');
    };

    return new Promise\Promise($resolver, $canceller);
  }

  /**
   * Sends a message to a specific web/app-frontend clients which are viewing a specific site.
   * @param  array  $data   The json formatted data array which should be sent to the client.
   */
  public function messageSpecificNode(array $data): object
  {
    $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
      if(array_key_exists("nodeinfo", $data) && array_key_exists("authhash", $data["nodeinfo"]) && array_key_exists("data", $data)){
        $alreadyinformed = [];

        echo "[{$this->getDate()}] [----------] INFO SERVER MSG_SPEC_CLIENT: Got command message specific node " . json_encode($data) . "\n";

        foreach($this->requests AS $authhash => $requesterinfo){
          if(!in_array($authhash, $alreadyinformed) && $data["nodeinfo"]["authhash"] == $authhash){
            echo "[{$this->getDate()}] [----------] INFO SERVER MSG_SPEC_CLIENT: Informing {$requesterinfo['resid']} about changes.\n";
            if(array_key_exists($requesterinfo["resid"], $this->users)){
              $this->users[$requesterinfo["resid"]]->send(json_encode($data["data"]));
            }
            array_push($alreadyinformed, $authhash);
          }
        }

        foreach($this->subscription as $nodetype => $nodedata) {
          foreach ($nodedata as $connid => $nodeinfos) {
            if(!in_array($nodeinfos["authhash"], $alreadyinformed) && $data["nodeinfo"]["authhash"] == $nodeinfos["authhash"]){
              echo "[{$this->getDate()}] [----------] INFO SERVER MSG_SPEC_CLIENT: Informing $connid about changes.\n";
              $this->users[$connid]->send(json_encode($data["data"]));
              array_push($alreadyinformed, $nodeinfos["authhash"]);
            }
          }
        }

        $informedcount = count($alreadyinformed);
        if($informedcount > 0){
          return array("messageSpecificNode" => array("status" => 0, "message" => "Successfully queryied cron request to {$informedcount} node(s)."));
        }else{
          $errormessage = Promise\resolve((new Logging_Api($this, $this))->getErrormessage("messageSpecificNode", "001"));
          $errormessage->then(function($errormessage_returned) use(&$resolve, $alreadyinformed){
            echo "[{$this->getDate()}] [----------] INFO SERVER MSG_SPEC_CLIENT: {$errormessage_returned["message"]}.\n";
            $errormessage_returned["data"]["informed"] = $alreadyinformed;

            $resolve($errormessage_returned);
          });
        }
      }else{
        $resolve((new Logging_Api($this, $this))->getErrormessage("messageSpecificNode", "002", json_encode($data)));
      }
    };

    $canceller = function () {
      throw new Exception('Promise cancelled');
    };

    return new Promise\Promise($resolver, $canceller);
  }

  /**
   * Sends a message to all web/app-frontend, backend and chia clients.
   * @param  array  $data   The json formatted data array which should be sent to the client.
   */
  public function messageAllNodes(array $data): object
  {
    $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
      $alreadyinformed = [];

      echo "[{$this->getDate()}] [----------] INFO SERVER MSG_ALL_NODES: Got command message all nodes " . json_encode($data) . "\n";

      foreach($this->subscription as $nodetype => $nodedata) {
        if(!in_array("webClient", explode(",", $nodetype)) && !in_array("backendClient", explode(",", $nodetype))){
          foreach ($nodedata as $connid => $nodeinfos) {
            if(!in_array($nodeinfos["authhash"], $alreadyinformed)){
              echo "[{$this->getDate()}] [----------] INFO SERVER MSG_ALL_NODES: Informing $connid about changes.\n";
              $this->users[$connid]->send(json_encode($data["data"]));
              array_push($alreadyinformed, $nodeinfos["authhash"]);
            }
          }
        }
      }

      foreach($this->requests AS $authhash => $requesterinfo){
        if(!in_array($authhash, $alreadyinformed) && array_key_exists("nodeinfo", $data) && $data["nodeinfo"]["authhash"] == $authhash){
          echo "[{$this->getDate()}] [----------] INFO SERVER MSG_ALL_NODES: Informing {$requesterinfo['resid']} about changes.\n";
          $this->users[$requesterinfo["resid"]]->send(json_encode($data["data"]));
          array_push($alreadyinformed, $authhash);
        }
      }
  
      $informedcount = count($alreadyinformed);
      if($informedcount > 0){
        $resolve(array("messageSpecificNode" => array("status" => 0, "message" => "Successfully queryied cron request to {$informedcount} node(s).")));
      }else{
        $errormessage = Promise\resolve((new Logging_Api($this, $this))->getErrormessage("messageAllNodes", "001"));
        $errormessage->then(function($errormessage_returned) use(&$resolve, $alreadyinformed){
          echo "[{$this->getDate()}] [----------] INFO SERVER MSG_ALL_NODES: {$errormessage_returned["message"]}.\n";
          $errormessage_returned["data"]["informed"] = $alreadyinformed;

          $resolve($errormessage_returned);
        });
      }
    };

    $canceller = function () {
      throw new Exception('Promise cancelled');
    };

    return new Promise\Promise($resolver, $canceller);
  }

  /**
   * Deletes all connections of the backend client because they are not persistent.
   */
  private function unsubscribeAllBackendClients(){
    $resolver = function (callable $resolve, callable $reject, callable $notify){
      if(array_key_exists("backendClient", $this->subscription)){
        foreach($this->subscription["backendClient"] AS $connid => $conndata){
          if(array_key_exists($connid, $this->users)) unset($this->users[$connid]);
        }
        unset($this->subscription["backendClient"]);
      }

      $resolve("Unsubscribed Backendclients");
    };

    $canceller = function () {
      throw new Exception('Promise cancelled');
    };

    return new Promise\Promise($resolver, $canceller);
  }
}
