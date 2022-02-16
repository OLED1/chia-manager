<?php
  namespace ChiaMgmt\Chia_Overall;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The Chia_Overall_Api class contains every needed methods to manage all available overview data.
   * It stores and manages values regarding chia netspace and other chia information.
   * The data will be queried from an external open api.
   * This class is used by the backendclient to query information from the external api and from the webclient to get data.
   * @see https://api.chiaprofitability.com/
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Chia_Overall_Api{
    /**
     * Holds an instance to the Database Class.
     * @var DB_Api
     */
    private $db_api;
    /**
     * Holds an instance to the Nodes Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds a system config json array.
     * @var array
     */
    private $ini;
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
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;
    }

    /**
     * This function is called to query and get current chia overall at once. The querying is capped at a request limit from 5min per request.
     * When the cap of 5min per request is not met, the current stored db values are sent back in the return.
     * Function made for: Web Client
     * @throws Exception $e                 Throws an exception on db errors.
     * @todo                                Implement historical data loading.
     * @param  array $data                  { NULL } No requestdata needed to query this function.
     * @param  array $loginData             { NULL } No logindata needed to query this function.
     * @param  ChiaWebSocketServer $server  { NULL } No valid instance is needed to query this function.
     * @param  DateTime $fromtime           { NULL } On NULL only the last queryied data is returned. If a DateTime is given it will return historical data newer than. (Currently not implemented)
     * @return array                        Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}} from the subfunction calls.
     */
    public function queryOverallData(array $data = NULL, array $loginData = NULL, $server = NULL, DateTime $fromtime = NULL): array
    {
      if(array_key_exists("xchscan_api", $this->ini)){
        $sql = $this->db_api->execute("SELECT querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $now = new \DateTime("now");
        $lastquerytime = new \DateTime($sqdata[0]["querydate"]);
        $lastquerytime->modify("+2 minutes");

        try{
          if(count($sqdata) == 0 || $lastquerytime <= $now){
            $extapidata = $this->getDataFromExtApi();

            if(!is_null($extapidata) && array_key_exists("data", $extapidata) && array_key_exists("netspace", $extapidata["data"]) && array_key_exists("chia_price", $extapidata["data"]) && array_key_exists("xch_blockheight", $extapidata["data"]) && array_key_exists("blockchain_version", $extapidata["data"])){
              $netspacedata = $extapidata["data"]["netspace"];
              $chia_price_usd = $extapidata["data"]["chia_price"]["usd"];
              $blockheightdata = $extapidata["data"]["xch_blockheight"];
              $blockchainversion = $extapidata["data"]["blockchain_version"];

              if($extapidata["status"] == 0){
                $sql = $this->db_api->execute("SELECT netspace FROM chia_overall WHERE querydate > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY querydate ASC LIMIT 1", array());
                $historynetspace = $sql->fetchAll(\PDO::FETCH_ASSOC);

                $sql = $this->db_api->execute("SELECT MIN(price_usd) AS daymin_24h_usd, MAX(price_usd) AS daymax_24h_usd FROM chia_overall WHERE querydate > DATE_SUB(NOW(), INTERVAL 24 HOUR)", array());
                $historyprice = $sql->fetchAll(\PDO::FETCH_ASSOC);

                if(count($historynetspace) == 1 && count($historyprice) == 1){
                  $daychange_percent = explode(" ",$extapidata["data"]["netspace"])[0] / explode(" ",$historynetspace[0]["netspace"])[0] * 100 - 100;
                  $daymin_24h_usd = $historyprice[0]["daymin_24h_usd"];
                  $daymax_24h_usd = $historyprice[0]["daymax_24h_usd"];
                  $daychange_24h_percent = $chia_price_usd / $historyprice[0]["daymax_24h_usd"] * 100 - 100;
                }else{
                  $daychange_percent = 0;
                  $daymin_24h_usd = $chia_price_usd;
                  $daymax_24h_usd = $chia_price_usd;
                  $daychange_24h_percent = 0;
                }

                $sql = $this->db_api->execute("INSERT INTO chia_overall (id, blockchain_version, daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate) VALUES(NULL, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), NOW())",
                                                array($blockchainversion, number_format($daychange_percent,4), $netspacedata, $blockheightdata,
                                                $chia_price_usd, $daymin_24h_usd, $daymax_24h_usd, $daychange_24h_percent)
                                              );
              }else{
                return $this->logging_api->getErrormessage("001");
              }
            }else{
              return $extapidata;
            }
          }

          return $this->getOverallChiaData($fromtime);
        }catch(\Exception $e){
          return $this->logging_api->getErrormessage("002", $e);
        }
      }else{
        return $this->logging_api->getErrormessage("003");
      }
    }

    /**
     * Returns the newest overall informaiton stored in the db.
     * @throws Exception $e        Throws an exception on db errors.
     * @todo                       Implement historical data loading.
     * @param  DateTime $fromtime  { NULL } On NULL only the last queryied data is returned. If a DateTime is given it will return historical data newer than. (Currently not implemented)
     * @return array               Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}.
     */
    public function getOverallChiaData(DateTime $fromtime = NULL): array
    {
      try{
        if(is_null($fromtime)){
          $sql = $this->db_api->execute("SELECT blockchain_version, daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(array_key_exists("0", $sqdata)){
            return array("status" => 0, "message" => "Successfully queried chia overall data.", "data" => $sqdata[0]);
          }else{
            return $this->logging_api->getErrormessage("001");
          }
        }else{
          //Return historical data
        }
      }catch(\Exception $e){
        return $this->logging_api->getErrormessage("002", $e);
      }
    }

    /**
     * Queryies latest data from the external api using the link(s) stored in the config.ini.php.
     * @see https://api.chiaprofitability.com/netspace
     * @see https://api.chiaprofitability.com/market
     * @see https://xchscan.com/api/blocks?limit=10&offset=0
     * @return array Returns {"status": [0|>0], "message": [Status message], "data": {[Found queried external data]}}.
     */
    private function getDataFromExtApi(): array
    {
      $overall = true;

      //XCHSCAN API Netspace
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_URL, "{$this->ini["xchscan_api"]}/netspace");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT_MS, 2000);
      $xch_netspace_result = json_decode(curl_exec($curl), true);
      curl_close($curl);
      //XCHSCAN API Chia Price
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_URL, "{$this->ini["xchscan_api"]}/chia-price");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT_MS, 2000);
      $xch_chiaprice_result = json_decode(curl_exec($curl), true);
      curl_close($curl);
      //XCHSCAN API Blockheight
      $curl = curl_init();
      curl_setopt($curl, CURLOPT_POST, 1);
      curl_setopt($curl, CURLOPT_URL, "{$this->ini["xchscan_api"]}/blocks?limit=10&offset=0");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      curl_setopt($curl, CURLOPT_TIMEOUT_MS, 2000);
      $xch_height_result = json_decode(curl_exec($curl), true);
      curl_close($curl);
      //Chia Github Versionfile
      $curl = curl_init();
      $chiaversionspath = "https://api.github.com/repos/Chia-Network/chia-blockchain/releases";
      curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
      curl_setopt($curl, CURLOPT_URL, $chiaversionspath);
      //We need to use curl, because Amazon AWS wants a user Agent set to be able to download the chia release file
      curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.2; WOW64; rv:17.0) Gecko/20100101 Firefox/17.0');
      $chia_version_result = json_decode(curl_exec($curl), true);
      curl_close($curl);

      if(is_null($xch_netspace_result) || !array_key_exists("netspace", $xch_netspace_result)){
        $overall = false;
        $this->logging_api->getErrormessage("001", "The external api {$this->ini["xchscan_api"]} returned an error on netspace query. Message: " . json_encode($xch_netspace_result));
      }

      if(is_null($xch_chiaprice_result) || !array_key_exists("usd", $xch_chiaprice_result)){
        $overall = false;
        $this->logging_api->getErrormessage("002", "The external api {$this->ini["xchscan_api"]} returned an error on price query. Message: " . json_encode($xch_chiaprice_result));
      }

      if(is_null($xch_height_result) || !array_key_exists("blocks", $xch_height_result) && is_null($xch_height_result[0]) || !array_key_exists(0, $xch_height_result["blocks"])){
        $overall = false;
        $this->logging_api->getErrormessage("003", "The external api {$this->ini["xchscan_api"]} returned an empty output on block query. Message: " . json_encode($xch_height_result));
      }

      if(is_null($chia_version_result) || !array_key_exists(0, $chia_version_result) || !array_key_exists("name", $chia_version_result[0])){
        $overall = false;
        $this->logging_api->getErrormessage("004", "The chia github version file ({$chiaversionspath}) could not be loaded. Message: " . json_encode($chia_version_result));
      }

      if($overall){
        $base = log($xch_netspace_result["netspace"]) / log(1024);
        $suffix = array("", " kiB", " MiB", " GiB", " TiB", " PiB", " EiB")[floor($base)];
        $xch_netspace_result["netspace"] = number_format(pow(1024, $base - floor($base)),2) . $suffix;
        $xch_height_result = $xch_height_result["blocks"][0]["height"];
        $chia_blockchain_version = $chia_version_result[0]["name"];
        $nowdate = new \DateTime("now");

        return array("status" => 0, "message" => "Data from external api queried successfully.", "data" => array("netspace" => $xch_netspace_result["netspace"], "chia_price" => $xch_chiaprice_result, "xch_blockheight" => $xch_height_result, "blockchain_version" => $chia_blockchain_version, "timestamp" => $nowdate->format("Y-m-d H:i:s")));
      }else{
        return $this->logging_api->getErrormessage("005");
      }
    }
  }

?>
