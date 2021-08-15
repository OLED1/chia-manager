<?php
namespace ChiaMgmt\WebSocketServer;

use Ratchet\MessageComponentInterface;
use Ratchet\ConnectionInterface;
use ChiaMgmt\Login\Login_Api;
use ChiaMgmt\RequestHandler\RequestHandler_Api;
use ChiaMgmt\Sites\Sites_Api;
use ChiaMgmt\Logging\Logging_Api;

require __DIR__ . '/../../../vendor/autoload.php';

class ChiaWebSocketServer implements MessageComponentInterface {
    protected $clients, $users, $subscription, $websocketClient, $login_api, $requestHandler, $requests, $sites_api, $sites_data, $sites_data1, $logging;

    public function __construct() {
      echo "[{$this->getDate()}] INFO: Starting websocket server\n";
      $this->clients = new \SplObjectStorage;
      $this->users = [];
      $this->subscription = [];
      $this->requests = [];

      $this->login_api = new Login_Api();
      $this->sites_api = new Sites_Api();
      $this->logging = new Logging_Api($this);
      $this->sites_data = $this->sites_api->getSiteInfos(["siteid" => NULL])["data"];
    }

    public function onOpen(ConnectionInterface $conn) {
        // Store the new connection to send messages to later
        $this->clients->attach($conn);
        $this->users[$conn->resourceId] = $conn;

        echo "[{$this->getDate()}] INFO: New connection! ({$conn->resourceId})\n";
    }

    public function onMessage(ConnectionInterface $from, $msg) {
      $data = json_decode($msg, true);

      if(array_key_exists("node" , $data) && array_key_exists("request" , $data)
         && array_key_exists("data" , $data["request"]) && array_key_exists("backendInfo" , $data["request"])){

        //Setting variables for processing
        $nodeInfo = $data["node"];
        $request = $data["request"];
        $loginData = $request["logindata"];
        $reqData = $request["data"];
        $backendInfo = $request["backendInfo"];
        $nodeip = "localhost";
        if(array_key_exists("X-Forwarded-For", $from->httpRequest->getHeaders())) $nodeip = $from->httpRequest->getHeaders()['X-Forwarded-For'][0];

        //Authenticate Node usind ReqeuestHandler
        $this->requestHandler = new RequestHandler_Api();

        if(is_array($loginData) && is_array($data["node"]["nodeinfo"])){
          $requesterLogin = $this->requestHandler->requesterLogin($nodeip, $loginData, $data["node"]["nodeinfo"]);
        }else{
          $this->users[$from->resourceId]->send(json_encode(array("notallinformationstated" => array("status" => 1, "message" => "Not all information stated."))));
        }

        echo "[{$this->getDate()}] INFO: {$requesterLogin["message"]}\n";

        if($requesterLogin["status"] == "008005006" || $requesterLogin["status"] == "008005007" ||
          $requesterLogin["status"] == "008005012" || $requesterLogin["status"] == "008005002" ||
          $requesterLogin["status"] == "008005011"
        ){
          echo "[{$this->getDate()}] INFO: Send new connection request to frontend.\n";
          $requesterLogin["data"]["resid"] = $from->resourceId;
          if($requesterLogin["status"] == "005004002") $this->requests[$requesterLogin["data"]["authhash"]] = $requesterLogin["data"];
          else $this->requests[$requesterLogin["data"]["newauthhash"]] = $requesterLogin["data"];
          $this->messageFrontendClients(array("siteID" => 2), $this->requestHandler->processConnectionRequest($this->requests));

          /*if($requesterLogin["status"] == "005004002") $this->subscription[$requesterLogin["data"]["authhash"]] = $requesterLogin["data"];
          else $this->subscription[$requesterLogin["data"]["newauthhash"]] = $requesterLogin["data"];
          $this->messageFrontendClients(array("siteID" => 2), $this->requestHandler->processNodeConnectionChanged($this->subscription));*/
        }

        if(array_key_exists("status", $requesterLogin) && $requesterLogin["status"] == 0){
          foreach(explode(",", $requesterLogin["nodeinfo"]["type"]) AS $arrkey => $type){
            if($type != "backendClient" && (!array_key_exists($type, $this->subscription) || !array_key_exists($from->resourceId, $this->subscription[$type]))){
                echo "[{$this->getDate()}] INFO: Newly connected {$type} client connected.\n";
                foreach(explode(",", $type) AS $arrkey => $this_type){
                  $this->subscription[trim($this_type)][$from->resourceId] = $loginData;
                }
                $this->messageFrontendClients(array("siteID" => 2), $this->requestHandler->processNodeConnectionChanged($this->subscription));
            }else{
              echo "[{$this->getDate()}] INFO: Detected backend Client or existing connection.\n";
              foreach(explode(",", $type) AS $arrkey => $this_type){
                $this->subscription[trim($this_type)][$from->resourceId] = $loginData;
              }
            }
          }

          echo "[{$this->getDate()}] INFO: New backendRequest from " . $nodeInfo["nodeinfo"]["hostname"] . "\n";
          echo "[{$this->getDate()}] INFO: Requested socketaction: ". $nodeInfo["socketaction"] . " from {$from->resourceId}.\n";
          echo "[{$this->getDate()}] INFO: Transmitted data {$msg}.\n";

          switch($nodeInfo["socketaction"]){
            case "wssonlinestatus":
              $this->users[$from->resourceId]->send(json_encode(array("status" => 0, "message" => "Websocket server ready to rumble.", "data" => getmypid())));
              break;
            case "backendRequest": //Returns the requested value to all frontend Clients which are viewing a specific site
              if(is_array($loginData) && is_array($backendInfo) && is_array($reqData)){
                $this_req = $this->requestHandler->processRequest($loginData, $backendInfo, $reqData);
                $this->messageFrontendClients($loginData, $this_req, $from->resourceId, $backendInfo);
              }else{
                $this->users[$from->resourceId]->send(json_encode(array($backendInfo['method'] => array("status" => 1, "message" => "One of the required arrays has a wrong datatype."))));
              }
              break;
            case "ownRequest": //Returns the requested value just to the requesters open socket
              if(is_array($loginData) && is_array($backendInfo) && is_array($reqData)){
                $this_req = $this->requestHandler->processRequest($loginData, $backendInfo, $reqData);
                $this->users[$from->resourceId]->send(json_encode($this_req));
              }else{
                $this->users[$from->resourceId]->send(json_encode(array($backendInfo['method'] => array("status" => 1, "message" => "One of the required arrays has a wrong datatype."))));
              }
              break;
            case "getActiveSubscriptions": //Returns the current subscriptions for all connected WS Nodes
              $this_req = $this->requestHandler->processGetActiveSubscriptions($loginData, $this->subscription);
              $this->users[$from->resourceId]->send(json_encode($this_req));
              break;
            case "getActiveRequests": //Returns the current subscriptions for all connected WS Nodes
              $this_req = $this->requestHandler->processGetActiveRequests($loginData, $this->requests);
              $this->users[$from->resourceId]->send(json_encode($this_req));
              break;
            case "updateFrontendViewingSite": //Updates the viewing site for a user connected to a web client
              $this_req = $this->requestHandler->processUpdateFrontendViewingSite($loginData, $this->subscription, $reqData, $from->resourceId);
              if(array_key_exists("data", $this_req["updateFrontendViewingSite"])){
                $this->subscription = $this_req["updateFrontendViewingSite"]["data"];
                unset($this_req["updateFrontendViewingSite"]["data"]);
              }
              $this->users[$from->resourceId]->send(json_encode($this_req));
              break;
            case "messageSpecificNode":
              $this->users[$from->resourceId]->send(json_encode($this->messageSpecificNode($loginData, $reqData, $from->resourceId)));
              break;
              case "queryCronData":
              echo "[{$this->getDate()}] INFO: Querying cron data.\n";
              $this->users[$from->resourceId]->send(json_encode($this->messageAllNodes($nodeInfo, $reqData)));
              break;
            case "informAllWebclients":
              break;
            case "informSpecificWebclient":
              break;
            case "informAllNodes":
              break;
            default:
              $this->users[$from->resourceId]->send(json_encode(array("status" => 1, "message" => "Socketaction " . $nodeInfo["socketaction"] . " not known.")));
          }
        }else{
          $this->users[$from->resourceId]->send(json_encode(array("loginStatus" => $requesterLogin)));
        }
      }else{
        $this->users[$from->resourceId]->send(json_encode(array("notallinformationstated" => array("status" => 1, "message" => "Not all information stated."))));
      }
      $this->unsubscribeAllBackendClients();
    }

    public function onClose(ConnectionInterface $conn) {
        // The connection is closed, remove it, as we can no longer send it messages
        $this->clients->detach($conn);

        $message = "Connection {$conn->resourceId} has disconnected\n";
        echo "[{$this->getDate()}] WARNING: {$message}\n";
        $this->logging->getErrormessage("001", $message);

        unset($this->users[$conn->resourceId]);

        $changed = false;
        foreach($this->subscription AS $type => $connections){
          foreach($connections AS $conid => $values){
            if($conid == $conn->resourceId){
              unset($this->subscription[$type][$conid]);
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

        if($changed){
          $this->messageFrontendClients(array("siteID" => 2), $this->requestHandler->processNodeConnectionChanged($this->subscription));
        }
    }

    public function onError(ConnectionInterface $conn, \Exception $e) {
        echo "[{$this->getDate()}] CRITICAL: An error has occurred: {$e->getMessage()}\n";
        $this->logging->getErrormessage("001", $e->getMessage());

        $conn->close();
    }

    private function getDate(){
      return date("d.m.Y H:i:s");
    }

    private function messageFrontendClients(array $loginData, array $datatosend, int $mycon = NULL, array $backendInfo = NULL){
      if(is_null($backendInfo) && array_key_exists("siteID", $loginData) && $loginData["siteID"] > 0){
        $siteID = $this->sites_data["by-id"][$loginData["siteID"]]["sitestoinform"];
      }else if(!is_null($backendInfo) && array_key_exists("namespace", $backendInfo) && $backendInfo["namespace"] != ""){
        $siteID = $this->sites_data["by-namespace"][$backendInfo["namespace"]]["sitestoinform"];
      }else{
        echo "[{$this->getDate()}] WARNING: No siteid can be found.\n";
      }

      if(!array_key_exists("loginStatus", $datatosend) && array_key_exists("webClient", $this->subscription)) {
        foreach($this->subscription["webClient"] AS $connectionid => $condetails){
          if(array_key_exists("siteID", $condetails) && array_key_exists("userid", $condetails) &&
            in_array($condetails["siteID"], $siteID)){

            echo "[{$this->getDate()}] INFO: User {$condetails["userid"]} is viewing site {$condetails["siteID"]} so he will be informed using socket $connectionid.\n";
            if(array_key_exists($connectionid, $this->users)){
              echo "[{$this->getDate()}] INFO: Informing $connectionid about changes.\n";
              $this->users[$connectionid]->send(json_encode($datatosend));
            }else{
              echo "[{$this->getDate()}] WARNING: Connection $connectionid is not existing.\n";
            }
          }else{
            echo "[{$this->getDate()}] WARNING: No connection found which satisfies one of these sites: " . json_encode($siteID) . ".\n";
          }
        }
      }else if(array_key_exists("loginStatus", $datatosend)){
        foreach($this->subscription["webClient"] AS $connectionid => $condetails){
          if($loginData["userid"] == $condetails["userid"]){
            echo "[{$this->getDate()}] INFO: User {$condetails["userid"]} logged in. Informing using socket $mycon.\n";
            $this->users[$mycon]->send(json_encode($datatosend));
            break;
          }
        }
      }
    }

    private function messageSpecificNode(array $request, array $data, int $mycon){
      if(array_key_exists("nodeinfo", $data) && array_key_exists("authhash", $data["nodeinfo"]) &&
        array_key_exists("data", $data)
      ){
        $alreadyinformed = [];

        foreach($this->requests AS $authhash => $requesterinfo){
          if(!in_array($authhash, $alreadyinformed) && $data["nodeinfo"]["authhash"] == $authhash){
            echo "[{$this->getDate()}] INFO: Informing {$requesterinfo['resid']} about changes.\n";
            $this->users[$requesterinfo["resid"]]->send(json_encode($data["data"]));
            array_push($alreadyinformed, $authhash);
          }
        }

        foreach($this->subscription as $nodetype => $nodedata) {
          foreach ($nodedata as $connid => $nodeinfos) {
            if(!in_array($nodeinfos["authhash"], $alreadyinformed) && $data["nodeinfo"]["authhash"] == $nodeinfos["authhash"]){
              echo "[{$this->getDate()}] INFO: Informing $connid about changes.\n";
              $this->users[$connid]->send(json_encode($data["data"]));
              array_push($alreadyinformed, $nodeinfos["authhash"]);
            }
          }
        }

        $informedcount = count($alreadyinformed);
        if($informedcount > 0){
          return array("messageSpecificNode" => array("status" => 0, "message" => "Successfully queryied cron request to {$informedcount} node(s)."));
        }else{
          $data = $this->logging->getErrormessage("001");
          return array("messageSpecificNode" => $data);
        }
      }else{
        return array("messageSpecificNode" => $this->logging->getErrormessage("002", json_encode($request)));
      }
    }

    private function messageAllNodes(array $nodeInfo, array $data){
      $alreadyinformed = [];

      foreach($this->subscription as $nodetype => $nodedata) {
        if(!in_array("webClient", explode(",", $nodetype)) && !in_array("backendClient", explode(",", $nodetype))){
          foreach ($nodedata as $connid => $nodeinfos) {
            if(!in_array($nodeinfos["authhash"], $alreadyinformed)){
              echo "[{$this->getDate()}] INFO: Informing $connid about changes.\n";
              $this->users[$connid]->send(json_encode(array($nodeInfo["socketaction"] => array("status" => 0, "message" => "Process command received {$nodeInfo["socketaction"]}."))));
              array_push($alreadyinformed, $nodeinfos["authhash"]);
            }
          }
        }
      }

      $informedcount = count($alreadyinformed);
      if($informedcount > 0){
        return array($nodeInfo["socketaction"] => array("status" => 0, "message" => "Successfully queryied cron request to {$informedcount} node(s)."));
      }else{
        //return array($nodeInfo["socketaction"] => array("status" => 0, "message" => "No nodes online to inform."));
        $data = $this->logging->getErrormessage("001");
        //Add Node ID to data as data
        return array($nodeInfo["socketaction"] => $data);
      }
    }

    private function unsubscribeAllBackendClients(){
      if(array_key_exists("backendClient", $this->subscription)){
        foreach($this->subscription["backendClient"] AS $connid => $conndata){
          if(array_key_exists($connid, $this->users)) unset($this->users[$connid]);
        }
        unset($this->subscription["backendClient"]);
      }
    }
}
