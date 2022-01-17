<?php
  namespace ChiaMgmt\Chia_Wallet;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;
  use ChiaMgmt\Nodes\Nodes_Api;
  use ChiaMgmt\Encryption\Encryption_Api;
  use ChiaMgmt\Chia_Wallet\Data_Objects\Walletdata;
  use ChiaMgmt\Chia_Wallet\Data_Objects\Wallettransaction;

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
     * Update the available wallet data.
     * Function made for: Node Client
     * @throws Exception $e       Throws an exception on db errors.
     * @see https://files.chiamgmt.edtmair.at/docs/classes/ChiaMgmt-Encryption-Encryption-Api.html#method_encryptString
     * @param  array  $data       {'wallet': {'1': {'walletheight': '843911', 'syncstatus': 'Not synced', 'walletid': '1', 'wallettype': 'STANDARD_WALLET', 'totalbalance': '4e-06', 'pendingtotalbalance': '4e-06', 'spendable': '4e-06', 'walletaddress': '[Walletaddress]\n'}}},
     * @param  array  $loginData  {"authhash": "[Querying Node's authhash]"}
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {"nodeid": [nodeid], "data": {[newly added wallet data]}}
     */
    public function updateWalletData(array $data, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        foreach($data AS $walletid => $walletdata){
          if(is_numeric($walletid) && is_array($walletdata)){
            $sql = $this->db_api->execute("SELECT Count(*) as count FROM chia_wallets WHERE walletid = ? AND nodeid = ?", array($walletid, $nodeid));
            $count = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["count"];
            $formatted_data = new Walletdata($walletdata);

            if($count == 0){
              $sql = $this->db_api->execute("INSERT INTO chia_wallets (id, nodeid, walletid, walletaddress, walletheight, syncstatus, wallettype, totalbalance, pendingtotalbalance, spendable, querydate) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, current_timestamp())",
              array($nodeid, $walletid, $formatted_data->get_address(), $formatted_data->get_height(), $formatted_data->get_syncstatus(), $formatted_data->get_type(), $formatted_data->get_confirmed_wallet_balance(), $formatted_data->get_unconfirmed_wallet_balance(), $formatted_data->get_spendable_balance()));
            }else{
              $sql = $this->db_api->execute("UPDATE chia_wallets SET  walletaddress = ?, walletheight = ?, syncstatus = ?, wallettype = ?, totalbalance = ?, pendingtotalbalance = ?, spendable = ?, querydate = current_timestamp() WHERE walletid = ? AND nodeid = ?",
              array($formatted_data->get_address(), $formatted_data->get_height(), $formatted_data->get_syncstatus(), $formatted_data->get_type(), $formatted_data->get_confirmed_wallet_balance(), $formatted_data->get_unconfirmed_wallet_balance(), $formatted_data->get_spendable_balance(), $walletid, $nodeid));
            }
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }

      return array("status" => 0, "message" => "Successfully updated wallet information for node $nodeid.", "data" => ["nodeid" => $nodeid, "data" => $this->getWalletData($data, $loginData, $nodeid)["data"]]);
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
    public function updateWalletTransactions(array $data = NULL, array $loginData = []): array
    {
        try{
          $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
          $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

          $sql = $this->db_api->execute("SELECT name FROM chia_wallets_transactions WHERE nodeid = ?", array($nodeid));
          $found_transactions = $sql->fetchAll(\PDO::FETCH_ASSOC);

          foreach($found_transactions as $arrkey => $transactionname){
            $found_transactions[$arrkey] = $transactionname["name"];
          }

          foreach($data AS $walletid => $transactions){
            if(array_key_exists("transactions", $transactions)){
              foreach($transactions["transactions"] AS $arrkey => $transactiondata){
                $formatted_data = new Wallettransaction($transactiondata);
                if(!in_array($formatted_data->get_transaction_name(), $found_transactions)){
                  $sql = $this->db_api->execute("INSERT INTO chia_wallets_transactions (id, nodeid, wallet_id, parent_coin_info, amount, confirmed, confirmed_at_height, created_at_time, fee_amount, name, removals, sent, sent_to, spend_bundle, to_address, to_puzzle_hash, trade_id, type) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                  array($nodeid, $walletid, $formatted_data->get_parent_coin_info(), $formatted_data->get_amount(),
                        $formatted_data->get_confirmed(), $formatted_data->get_confirmed_at_height(), $formatted_data->get_created_at_time(), $formatted_data->get_fee_amount(),
                        $formatted_data->get_transaction_name(), $formatted_data->get_removals(), $formatted_data->get_sent(), $transactiondata["sent_to"],
                        $formatted_data->get_spend_bundle(), $formatted_data->get_to_address(), $formatted_data->get_to_puzzle_hash(), (is_null($formatted_data->get_trade_id()) ? 0 : $formatted_data->get_trade_id()),
                        $formatted_data->get_type()
                      ));
                }
              }
            }
          }
          return array("status" => 0, "message" => "Successfully added new transaction(s) for node $nodeid.", "data" => ["nodeid" => $nodeid]);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
        }
    }

    /**
     * [getLatestTransactionDate description]
     * Function made for: Node Client
     * @param  array $data      [description]
     * @param  array $loginData [description]
     * @return array            [description]
     */
    public function getLatestTransactionDate(array $data = NULL, array $loginData = NULL): array
    {
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
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("001", $e);
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
    public function getWalletData(array $data = NULL, array $loginData = NULL, $server = NULL, int $nodeid = NULL): array
    {
      $return_transactions = true;
      if(!is_null($data) && array_key_exists("nodeid", $data) && is_numeric($data["nodeid"])) $nodeid = $data["nodeid"];
      if(!is_null($data) && array_key_exists("return_transactions", $data) && is_bool($data["return_transactions"])) $return_transactions = $data["return_transactions"];
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
          $nodeid = $walletinfo["nodeid"];
          $walletinfo["nodeauthhash"] = $this->encryption_api->decryptString($walletinfo["nodeauthhash"]);
          if(!array_key_exists($nodeid, $returndata)) $returndata[$nodeid] = [];
          if(!array_key_exists("hostinfo", $returndata[$nodeid])){
            $returndata[$nodeid]["hostinfo"] = [
              "hostname" => $walletinfo["hostname"],
              "nodeauthhash" => $walletinfo["nodeauthhash"]
            ];
          }
          unset($walletinfo["hostname"], $walletinfo["nodeauthhash"], $walletinfo["nodeid"]);
          $returndata[$nodeid]["walletinfo"][(is_numeric($walletinfo["walletid"]) ? $walletinfo["walletid"] : 0)] = $walletinfo;
          if($return_transactions && !array_key_exists("transactions", $returndata[$nodeid])){
            $found_transactions = $this->getWalletTransactions(["nodeid" => $nodeid]);
            if(array_key_exists("data", $found_transactions) && array_key_exists($nodeid, $found_transactions["data"])){
              $returndata[$nodeid]["transactions"] = $found_transactions["data"][$nodeid];
            }else{
              $returndata[$nodeid]["transactions"] = [];
            }
          }
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
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
    public function getWalletTransactions(array $data = NULL, array $loginData = NULL): array
    {
      $returndata = [];
      try{
        if(is_null($data) || !is_numeric($data["nodeid"])){
          $sql = $this->db_api->execute("SELECT id, nodeid, wallet_id, parent_coin_info, amount, amount, confirmed, confirmed_at_height, created_at_time, fee_amount, name, removals, sent, sent_to, spend_bundle, to_address, to_puzzle_hash, trade_id, type FROM chia_wallets_transactions ORDER BY created_at_time ASC", array());
        }else if(!is_null($data) && array_key_exists("nodeid", $data)){
          $sql = $this->db_api->execute("SELECT id, nodeid, wallet_id, parent_coin_info, amount, amount, confirmed, confirmed_at_height, created_at_time, fee_amount, name, removals, sent, sent_to, spend_bundle, to_address, to_puzzle_hash, trade_id, type FROM chia_wallets_transactions WHERE nodeid = ? ORDER BY created_at_time ASC", array($data["nodeid"]));
        }else{
          return $this->logging_api->getErrormessage("001");
        }

        foreach($sql->fetchAll(\PDO::FETCH_ASSOC) AS $arrkey => $transactiondata){
          if(!array_key_exists($transactiondata["nodeid"], $returndata))
            $returndata[$transactiondata["nodeid"]] = [];
          if(!array_key_exists($transactiondata["wallet_id"], $returndata[$transactiondata["nodeid"]]))
            $returndata[$transactiondata["nodeid"]][$transactiondata["wallet_id"]] = [];

          array_push($returndata[$transactiondata["nodeid"]][$transactiondata["wallet_id"]], $transactiondata);
        }

        return array("status" =>0, "message" => "Successfully loaded chia wallet information.", "data" => $returndata);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
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
    public function queryWalletData(array $data = NULL, array $loginData = NULL, $server = NULL): array
    {
      $querydata = [];
      $querydata_0["data"]["queryWalletData"] = array(
        "status" => 0,
        "message" => "Query wallet data.",
        "data"=> array()
      );
      $querydata_1["data"]["queryWalletTransactions"] = array(
        "status" => 0,
        "message" => "Query wallet transaction data.",
        "data"=> array()
      );

      $callfunction = "messageAllNodes";
      if(array_key_exists("nodeinfo", $data) && array_key_exists("authhash", $data["nodeinfo"])){
        $querydata_0["nodeinfo"]["authhash"] = $data["nodeinfo"]["authhash"];
        $querydata_1["nodeinfo"]["authhash"] = $data["nodeinfo"]["authhash"];
        $callfunction = "messageSpecificNode";
      }

      if(!is_null($server)){
        $server->$callfunction($querydata_0);
        return $server->$callfunction($querydata_1);
      }else{
        $this->websocket_api = new WebSocket_Api();
        $this->websocket_api->sendToWSS($callfunction, $querydata_0);
        return $this->websocket_api->sendToWSS($callfunction, $querydata_1);
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
    public function restartWalletService(array $data = NULL, array $loginData = NULL, $server = NULL): array
    {
      if(array_key_exists("authhash", $data)){
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
      }else{
        return $this->logging_api->getErrormessage("001");
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
    public function walletServiceRestart(array $data = NULL, array $loginData = NULL): array
    {
      try{
        $sql = $this->db_api->execute("SELECT id FROM nodes WHERE nodeauthhash = ? LIMIT 1", array($this->encryption_api->encryptString($loginData["authhash"])));
        $nodeid = $sql->fetchAll(\PDO::FETCH_ASSOC)[0]["id"];

        $data["data"] = $nodeid;
        return array("status" =>0, "message" => "Successfully queried wallet service restart for node $nodeid.", "data" => $data);
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("001", $e);
      }
    }
  }
?>
