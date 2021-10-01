<?php
  namespace ChiaMgmt\Chia_Wallet;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;

  /**
   * The Chia_Wallet_Api class contains every needed methods to manage all available wallet data.
   * This class is used by the client to send in data and from the webclient to get data.
   * The client can also be managed via this class.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Wallet_Api{
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
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->nodes_api = new Nodes_Api();
      $this->encryption_api = new Encryption_Api();
    }

    /**
     * Update the available wallet data.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array  $data       {'wallet': {'1': {'walletheight': '843911', 'syncstatus': 'Not synced', 'walletid': '1', 'wallettype': 'STANDARD_WALLET', 'totalbalance': '4e-06', 'pendingtotalbalance': '4e-06', 'spendable': '4e-06', 'walletaddress': '[Walletaddress]\n'}}},
     * @param  array  $loginData  {"authhash": "[Querying Node's authhash]"}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {"nodeid": [nodeid], "data": {[newly added wallet data]}}
     */
    public function updateWalletData(array $data, array $loginData = NULL){
      if(array_key_exists("wallet", $data)){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          foreach($data["wallet"] AS $walletid => $walletdata){
            $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_wallets WHERE walletid = ? AND nodeid = ?", array($walletid, $nodeid));
            $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];

            if($count == 0){
              $sql = $this->db_api->execute("INSERT INTO chia_wallets (id, nodeid, walletid, walletaddress, walletheight, syncstatus, wallettype, totalbalance, pendingtotalbalance, spendable, querydate) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp())",
              array($nodeid, $walletid, $walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"]));
            }else{
              $sql = $this->db_api->execute("UPDATE chia_wallets SET  walletaddress = ?, walletheight = ?, syncstatus = ?, wallettype = ?, totalbalance = ?, pendingtotalbalance = ?, spendable = ?, querydate = current_timestamp() WHERE walletid = ? AND nodeid = ?",
              array($walletdata["walletaddress"], $walletdata["walletheight"], $walletdata["syncstatus"], $walletdata["wallettype"], $walletdata["totalbalance"], $walletdata["pendingtotalbalance"], $walletdata["spendable"], $walletid, $nodeid));
            }
          }
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }

        return array("status" => 0, "message" => "Successfully updated wallet information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getWalletData($data, $loginData, $nodeid)["data"]]);
      }else{
        //TODO Implement correct status code
        return array("status" =>1, "message" => "Not all data stated.");
      }
    }

    /**
     * Update all transactions which were incoming our outgoing.
     * Function made for: Node Client
     * @todo Implement function and logging
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array $data       [description]
     * @param  array $loginData  [description]
     * @return array             [description]
     */
    public function updateWalletTransactions(array $data = NULL, array $loginData = NULL){
      if(array_key_exists("transactions", $data) && count($data["transactions"]) > 0){
        try{
          print_r($data["transactions"]);
        }catch(Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }

      return array("status" => 0, "message" => "Successfully added new transaction(s).");
    }

    /**
     * [getLatestTransactionDate description]
     * Function made for: Node Client
     * @param  array $data      [description]
     * @param  array $loginData [description]
     * @return array            [description]
     */
    public function getLatestTransactionDate(array $data = NULL, array $loginData = NULL){
      if(array_key_exists("wallet_ids", $data) && is_array($data["wallet_ids"]) && count($data["wallet_ids"]) > 0){
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $statement = "";
          $statement_arr = [];
          array_push($statement_arr, $nodeid);

          foreach($data["wallet_ids"] AS $arrkey => $walletid){
            if(array_key_exists($arrkey+1, $data["wallet_ids"])){
              $statement .= "(wallet_id = ? AND created_at_time = (SELECT max(created_at_time) FROM chia_wallets_transactions WHERE wallet_id = ?)) OR ";
            }else{
              $statement .= "(wallet_id = ? AND created_at_time = (SELECT max(created_at_time) FROM chia_wallets_transactions WHERE wallet_id = ?))";
            }
            array_push($statement_arr, $walletid);
            array_push($statement_arr, $walletid);
          }

          $returnarray = [];
          if(count($statement_arr) > 0){
            $sql = $this->db_api->execute("SELECT wallet_id, created_at_time FROM chia_wallets_transactions WHERE nodeid = ? AND $statement", $statement_arr);
            $returnarray = $sql->fetchAll(\PDO::FETCH_ASSOC);
          }

          return array("status" => 0, "message" => "Successfully queried latest transaction date for wallet with id " . json_decode($data["wallet_id"]) .".", "data" => $returnarray);
        }catch(Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }
    }

    /**
     * Returns an array of all available on the database stored wallet values.
     * Function made for: Web GUI/App
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array  $loginData               { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer  $server    An instance to websocket server class to be able to send data directly to nodes.
     * @param  int  $nodeid                    The node id to get only node specific data. Can be NULL if all data will be queried. Will be deprecated as soon as the method needs to be called outsite of the web gui.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found wallet data array]}
     */
    public function getWalletData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL){
      try{
        if(is_null($nodeid)){
          $sql = $this->db_api->execute("SELECT cw.walletid, nt.nodeid, n.nodeauthhash, n.hostname, cw.walletaddress, cw.walletheight, cw.syncstatus, cw.wallettype, cw.totalbalance, cw.pendingtotalbalance, cw.spendable, cw.querydate
                                         FROM nodetype nt
                                         JOIN nodes n ON n.id = nt.nodeid
                                         LEFT JOIN chia_wallets cw ON cw.nodeid = nt.nodeid
                                         WHERE nt.code = 5"
                                         , array());
        }else{
          $sql = $this->db_api->execute("SELECT cw.walletid, nt.nodeid, n.nodeauthhash, n.hostname, cw.walletaddress, cw.walletheight, cw.syncstatus, cw.wallettype, cw.totalbalance, cw.pendingtotalbalance, cw.spendable, cw.querydate
                                         FROM nodetype nt
                                         JOIN nodes n ON n.id = nt.nodeid
                                         LEFT JOIN chia_wallets cw ON cw.nodeid = nt.nodeid
                                         WHERE nt.code = 5 AND nt.nodeid = ?"
                                         , array($nodeid));
        }

        $returndata = [];
        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $walletinfo){
          $walletinfo["nodeauthhash"] = $this->encryption_api->decryptString($walletinfo["nodeauthhash"]);
          $returndata[$walletinfo["nodeid"]][(is_numeric($walletinfo["walletid"]) ? $walletinfo["walletid"] : 0)] = $walletinfo;
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Returns an array of all available on the database stored wallet transactions from the (near) past.
     * Function made for: Web GUI/App
     * @todo Implement Logging
     * @throws Exception $e                    Throws an exception on db errors.
     * @param  array  $data                    { NULL } Will be changed to { nodeid: [NULL|nodeid] } as soon as the method needs to be called outsite of the web gui.
     * @param  array  $loginData               { NULL } No logindata will be needed to be able to return valid data.
     * @param  ChiaWebSocketServer  $server    An instance to websocket server class to be able to send data directly to nodes.
     * @param  int  $nodeid                    The node id to get only node specific data. Can be NULL if all data will be queried. Will be deprecated as soon as the method needs to be called outsite of the web gui.
     * @return array                           {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": [Found wallet transaction data array]}
     */
    public function getWalletTransactions(array $data = NULL, array $loginData = NULL){
      $returndata = [];
      try{
        if(is_null($data)){
          $sql = $this->db_api->execute("SELECT id, nodeid, wallet_id, parent_coin_info, amount, amount, confirmed, confirmed_at_height, created_at_time, fee_amount, name, removals, sent, sent_to, spend_bundle, to_address, to_puzzle_hash, trade_id, type FROM chia_wallets_transactions ORDER BY created_at_time ASC", array());
        }else if(!is_null($data) && array_key_exists("nodeid", $data) && array_key_exists("walletid", $data)){
          $sql = $this->db_api->execute("SELECT id, nodeid, wallet_id, parent_coin_info, amount, amount, confirmed, confirmed_at_height, created_at_time, fee_amount, name, removals, sent, sent_to, spend_bundle, to_address, to_puzzle_hash, trade_id, type FROM chia_wallets_transactions WHERE nodeid = ? AND wallet_id = ? ORDER BY created_at_time ASC", array($data["nodeid"], $data["walletid"]));
        }else{
          //TODO Implement correct status codes
          return array("status" => 1, "message" => "No data found.");
        }

        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $transactiondata){
          if(!array_key_exists($transactiondata["nodeid"], $returndata))
            $returndata[$transactiondata["nodeid"]] = [];
          if(!array_key_exists($transactiondata["wallet_id"], $returndata[$transactiondata["nodeid"]]))
            $returndata[$transactiondata["nodeid"]][$transactiondata["wallet_id"]] = [];

          array_push($returndata[$transactiondata["nodeid"]][$transactiondata["wallet_id"]], $transactiondata);
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(Exception $e){
        //TODO Implement correct status codes
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }

      return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
    }

    /**
     * Sets the current walletstatus sent in from the node client.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data       { status: [0 = Running |1 = Not Running] } No data is needed to query this method.
     * @param  array $loginData  { NULL } No logindata is needed to query this method.
     * @return array             Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}
     */
    public function walletStatus(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $this->nodes_api->setNodeServiceStats(["type" => 5, "stat" => ($data["status"] == 0 ? 0 : 1), "nodeid" => $nodeid]);

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried wallet status information for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Informs the node client to query new wallet data.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageAllNodes
     * @param  array $data                  { authhash: [Target Node Authhash] }
     * @param  array $loginData             { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server  An instance to the websocket server to be able to send data to the connected clients.
     * @return array                        Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction calls.
     */
    public function queryWalletData(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["queryWalletData"] = array(
        "status" => 0,
        "message" => "Query Wallet data.",
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
     * Informs the node client to restart the wallet service.
     * Function made for: Communication WebGUI -> Node Client
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-WebSocketServer-ChiaWebSocketServer.html#method_messageSpecificNode
     * @param  array $data                    { authhash: [Target Node Authhash] }
     * @param  array $loginData               { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server    An instance to the websocket server to be able to send data to the connected clients.
     * @return array                          Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction call.
     */
    public function restartWalletService(array $data = NULL, array $loginData = NULL, $server = NULL){
      $querydata = [];
      $querydata["data"]["restartWalletService"] = array(
        "status" => 0,
        "message" => "Restart wallet service.",
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
     * The function which will be called from the node client when the service has been restarted.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array $data      { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI] }
     * @param  array $loginData { authhash: [Querying Node's Authhash] }
     * @return array            Returns {"status": [0|>0], "message": [Status message], "data": { "status": [0 = Success, 1 = Failed], "message": [Specific message about service restart for the WebGUI], nodeid: [Querying Node's ID] }}
     */
    public function walletServiceRestart(array $data = NULL, array $loginData = NULL){
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried wallet service restart for node $nodeid.", "data" => $data);
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }
  }
?>
