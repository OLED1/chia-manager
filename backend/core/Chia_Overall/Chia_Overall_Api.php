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
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
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
    public function queryOverallData(array $data = NULL, array $loginData = NULL, $server = NULL, DateTime $fromtime = NULL){
      if(array_key_exists("netspace_api", $this->ini) && array_key_exists("market_api", $this->ini) && array_key_exists("xchscan_api", $this->ini)){
        $sql = $this->db_api->execute("SELECT querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $now = new \DateTime("now");
        $lastquerytime = new \DateTime($sqdata[0]["querydate"]);
        $lastquerytime->modify("+5 minutes");

        try{
          if(count($sqdata) == 0 || $lastquerytime <= $now){
            $extapidata = $this->getDataFromExtApi();

            if($extapidata["status"] == 0){
              $netspacedata = $extapidata["data"]["netspace"];
              $marketdata = $extapidata["data"]["market"];
              $blockheightdata = $extapidata["data"]["xch_blockheight"];

              $sql = $this->db_api->execute("INSERT INTO chia_overall (id, daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                            array($netspacedata["daychange"], $netspacedata["netspace"], $blockheightdata, $netspacedata["timestamp"],
                                                  $marketdata["price"], $marketdata["daymin"], $marketdata["daymax"], $marketdata["daychange"], $marketdata["timestamp"],
                                                  $now->format("Y-m-d H:i:s"))
                                            );
            }else{
              return $extapidata;
            }
          }

          return $this->getOverallChiaData($fromtime);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Returns the newest overall informaiton stored in the db.
     * @throws Exception $e        Throws an exception on db errors.
     * @todo                       Implement historical data loading.
     * @param  DateTime $fromtime  { NULL } On NULL only the last queryied data is returned. If a DateTime is given it will return historical data newer than. (Currently not implemented)
     * @return array               Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}.
     */
    private function getOverallChiaData(DateTime $fromtime = NULL){
      try{
        if(is_null($fromtime)){
          $sql = $this->db_api->execute("SELECT daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(array_key_exists("0", $sqdata)){
            return array("status" => 0, "message" => "Successfully queried chia overall data.", "data" => $sqdata[0]);
          }else{
            return $this->logging->getErrormessage("001");
          }
        }else{
          //Return historical data
        }
      }catch(Exception $e){
        return $this->logging->getErrormessage("002", $e);
      }
    }

    /**
     * Queryies latest data from the external api using the link(s) stored in the config.ini.php.
     * @see https://api.chiaprofitability.com/netspace
     * @see https://api.chiaprofitability.com/market
     * @see https://xchscan.com/api/blocks?limit=10&offset=0
     * @return array Returns {"status": [0|>0], "message": [Status message], "data": {[Found queried external data]}}.
     */
    private function getDataFromExtApi(){
      $overall = true;

      $curl = curl_init();
      curl_setopt($curl, CURLOPT_POST, 1);

      //Netspace Api
      curl_setopt($curl, CURLOPT_URL, $this->ini["netspace_api"]);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $netspace_result = json_decode(curl_exec($curl), true);
      //Market Api
      curl_setopt($curl, CURLOPT_URL, $this->ini["market_api"]);
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $market_result = json_decode(curl_exec($curl), true);
      //XCHSCAN API
      curl_setopt($curl, CURLOPT_URL, "{$this->ini["xchscan_api"]}/blocks?limit=10&offset=0");
      curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
      $xch_height_result = json_decode(curl_exec($curl), true);

      curl_close($curl);

      if(!$netspace_result["success"]){
        $overall = false;
        $this->logging->getErrormessage("001", "The external api {$this->ini["netspace_api"]} returned an error.");
      }

      if(!$market_result["success"]){
        $overall = false;
        $this->logging->getErrormessage("002", "The external api {$this->ini["market_api"]} returned an error.");
      }

      if(!array_key_exists("blocks", $xch_height_result) && !array_key_exists(0, $xch_height_result["blocks"])){
        $overall = false;
        $this->logging->getErrormessage("003", "The external api {$this->ini["xchscan_api"]} returned an empty output.");
      }

      if($overall){
        $base = log($netspace_result["netspace"]) / log(1024);
        $suffix = array("", " kiB", " MiB", " GiB", " TiB", " PiB", " EiB")[floor($base)];
        $netspace_result["netspace"] = number_format(pow(1024, $base - floor($base)),2) . $suffix;
        $blockheight_result = $xch_height_result["blocks"][0]["height"];

        $netspace_date = new \DateTime("@" . $netspace_result["timestamp"]);
        $market_date = new \DateTime("@" . $market_result["timestamp"]);

        $netspace_result["timestamp"] = $netspace_date->format("Y-m-d H:i:s");
        $market_result["timestamp"] = $market_date->format("Y-m-d H:i:s");

        return array("status" => 0, "message" => "Data from external api queried successfully.", "data" => array("netspace" => $netspace_result, "market" => $market_result, "xch_blockheight" => $blockheight_result));
      }else{
        $this->logging->getErrormessage("004");
      }
    }
  }
?>
