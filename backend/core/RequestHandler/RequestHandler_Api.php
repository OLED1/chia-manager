<?php
  namespace ChiaMgmt\RequestHandler;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Login\Login_Api;
  use ChiaMgmt\System\System_Api;
  use ChiaMgmt\Mailing\Mailing_Api;
  use ChiaMgmt\Logging\Logging_Api;

  require __DIR__ . '/../../../vendor/autoload.php';

  class RequestHandler_Api{
    private $db_api, $login_api, $ini, $logging, $ciphering, $iv_length, $options, $encryption_iv;
    private $subscriptions, $requests, $nodeid;

    public function __construct(){
      $this->ciphering = "AES-128-CTR";
      $this->iv_length = openssl_cipher_iv_length($this->ciphering);
      $this->options = 0;
      $this->encryption_iv = '1234567891011121';

      $this->login_api = new Login_Api();
      $this->db_api = new DB_Api();
      $this->logging = new Logging_Api($this);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');
    }

    public function processRequest(array $loginData, array $backendInfo, array $data){
      $this_class = new $backendInfo['namespace']();
      $return = $this_class->{$backendInfo['method']}($data, $loginData);
      sleep(1);
      return array($backendInfo['method'] => $return);
    }

    public function processGetActiveSubscriptions(array $loginData, array $subscriptions){
      if($loginData["authhash"] == $this->ini["web_client_auth_hash"] ||
          $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
        return array("getActiveSubscriptions" => array("status" => 0, "message" => "Successfully loaded active subscriptions.", "data" => $subscriptions));
      }else{
        //return array("status" => 1, "message" => "You are not allowed to query this information.");
        return $this->logging->getErrormessage("001");
      }
    }

    public function processGetActiveRequests(array $loginData, array $requests){

      if($loginData["authhash"] == $this->ini["web_client_auth_hash"] ||
          $loginData["authhash"] == $this->ini["backend_client_auth_hash"]){
        return array("getActiveRequests" => array("status" => 0, "message" => "Successfully loaded active requests.", "data" => $requests));
      }else{
        //return array("status" => 1, "message" => "You are not allowed to query this information.");
        return $this->logging->getErrormessage("001");
      }
    }

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
          //return array("updateFrontendViewingSite" => array("status" => 1, "message" => "This user seems not to be subscribed."));
          return $this->logging->getErrormessage("001");
      }else{
        //return array("updateFrontendViewingSite" => array("status" => 1, "message" => "Not all information stated or you are not allowed to query this information."));
        return $this->logging->getErrormessage("002");
      }
    }

    public function processNodeConnectionChanged(array $subscriptions){
      return array("connectedNodesChanged" => array("status" => 0, "message" => "Successfully handeled connection request.", "data" => $subscriptions));
    }

    public function processConnectionRequest(array $requests){
      return array("clientConnectionRequest" => array("status" => 0, "message" => "Successfully handeled connection request.", "data" => $requests));
    }

    public function requesterLogin(string $nodeip, array $data, array $nodeinfo){
      if(array_key_exists("authhash", $data)){
        //$encryptedauthhash = hash('sha256',$data["authhash"]);
        $encryptedauthhash = $this->encryptAuthhash($data["authhash"]);

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
              if($sqldata["authtype"] == 1){ //Authtype = 1 means there must be username and session string stated
                if(array_key_exists("userid", $data) && array_key_exists("sessionid", $data)){
                  $authenticated = $this->login_api->checklogin($data["sessionid"], $data["userid"]);
                  $authenticated["nodeinfo"]["type"] = $sqldata["nodetype"];

                  return $authenticated;
                }else{
                  //return array("status" => 1, "message" => "No login information stated or too less data provided.");
                  return $this->logging->getErrormessage("001");
                }
              }else if($sqldata["authtype"] == 0){ //Authtype is currently not known, because this node is not authenticated to the api
                $returndata = $this->logging->getErrormessage("012");
                $returndata["data"]["newauthhash"] = $data["authhash"];
                return $returndata;
              }else if($sqldata["authtype"] == 2){ //Authtype = 2 means that this node needs an accepted IP address and authhash
                $authenticated = array("status" => 0, "message" => "This node is allowed to connect to the api.");
                $authenticated["nodeinfo"]["type"] = $sqldata["nodetype"];

                return $authenticated;
              }else if($sqldata["authtype"] == 3){ //Authtype = 3 means that this node needs no further login information only the authhash. Usage only for backendClient!
                if($sqldata["nodetype"] == "backendClient" && $nodeip == "localhost"){
                  return array("status" => 0, "message" => "This node is allowed to connect.", "nodeinfo" => array("type" => $sqldata["nodetype"]));
                }else{
                  //return array("status" => 1, "message" => "This node is not allowed to connect.");
                  return $this->logging->getErrormessage("004");
                }
              }else{
                //return array("status" => 1, "message" => "Authtype " . $sqldata["authtype"] . " not valid.");
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
            $sql = $this->db_api->execute("SELECT ipaddress, nodeauthhash FROM nodes WHERE hostname = ?", array($nodeinfo["hostname"]));
            $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

            if(count($sqdata) == 0){
              $newnodeauthhash = substr(str_shuffle('0123456789abcdefghijklmnopqrstuvwxyz'), 0, 35);
              $encryptedauthhash = $this->encryptAuthhash($newnodeauthhash);
              $sql = $this->db_api->execute("INSERT INTO nodes (id, nodeauthhash, hostname, conallow, authtype, ipaddress) VALUES (NULL, ?, ?, ?, ?, ?)",
                                            array($encryptedauthhash, $nodeinfo["hostname"], 2, 0, $nodeip));

              $sql = $this->db_api->execute("INSERT INTO nodetype (id, nodeid, code) VALUES (NULL, (SELECT id FROM nodes WHERE nodeauthhash = ?), 99)",
                                            array($encryptedauthhash));

              $returndata = $this->logging->getErrormessage("006");
              $returndata["data"]["newauthhash"] = $newnodeauthhash;
              return $returndata;
            }else if(count($sqdata) == 1){

              if($nodeip == $sqdata[0]["ipaddress"]){
                if(strlen(trim($data["authhash"])) == 0) $data = $this->logging->getErrormessage("013");
                else $data = $this->logging->getErrormessage("007");

                $data["data"]["newauthhash"] = $this->decryptAuthhash($sqdata[0]["nodeauthhash"]);

                return $data;
              }else{
                $sql = $this->db_api->execute("UPDATE nodes SET changedIP = ? WHERE hostname = ?", array($nodeip, $nodeinfo["hostname"]));
                return $this->logging->getErrormessage("011");
              }

              //return array("status" => 1, "message" => "This node is waiting for authentication please wait.");
              //return $this->logging->getErrormessage("007");
            }
          }else{
            //return array("status" => 1, "message" => "There is an error on the database. To much rows where returned.");
            return $this->logging->getErrormessage("008");
          }
        }catch(Exception $e){
          //print_r($e);
          //return array("status" => 1, "message" => "An error occured.");
          return $this->logging->getErrormessage("009", $e);
        }
      }else{
        //return array("status" => 1, "message" => "Data missing.");
        return $this->logging->getErrormessage("010");
      }
    }

    private function encryptAuthhash(string $authhash){
      return openssl_encrypt($authhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }

    private function decryptAuthhash(string $encryptedauthhash){
      return openssl_decrypt($encryptedauthhash, $this->ciphering, $this->ini["serversalt"], $this->options, $this->encryption_iv);
    }
  }
?>