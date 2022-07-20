<?php
  namespace ChiaMgmt\Chia_Overall;
  use React\Promise;
  use React\Http\Browser;
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
    public function queryOverallData(array $data = NULL, array $loginData = NULL, $server = NULL, DateTime $fromtime = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $fromtime){
        if(array_key_exists("xchscan_api", $this->ini)){
          $last_querydate = Promise\resolve((new DB_Api())->execute("SELECT querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array()));
          $last_querydate->then(function($last_querydate_returned) use(&$resolve, $fromtime){
            $now = new \DateTime("now");
            $lastquerytime = new \DateTime($last_querydate_returned->resultRows[0]["querydate"]);
            $lastquerytime->modify("+2 minutes");

            if(count($last_querydate_returned->resultRows) == 0 || $lastquerytime <= $now){
              $extapidata = Promise\resolve($this->getDataFromExtApi());
              $extapidata->then(function($extapidata_returned) use(&$resolve, $fromtime){
                if(!is_null($extapidata_returned) && array_key_exists("data", $extapidata_returned) && 
                    array_key_exists("netspace", $extapidata_returned["data"]) && array_key_exists("chia_price", $extapidata_returned["data"]) && 
                    array_key_exists("xch_blockheight", $extapidata_returned["data"]) && array_key_exists("blockchain_version", $extapidata_returned["data"]))
                {
                  $netspacedata = $extapidata_returned["data"]["netspace"];
                  $chia_price_usd = $extapidata_returned["data"]["chia_price"]["usd"];
                  $blockheightdata = $extapidata_returned["data"]["xch_blockheight"];
                  $blockchainversion = $extapidata_returned["data"]["blockchain_version"];
                  
                  if($extapidata_returned["status"] == 0){
                    $historynetspace = Promise\resolve((new DB_Api())->execute("SELECT netspace FROM chia_overall WHERE querydate > DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY querydate ASC LIMIT 1", array()));
                    $historyprice = Promise\resolve((new DB_Api())->execute("SELECT MIN(price_usd) AS daymin_24h_usd, MAX(price_usd) AS daymax_24h_usd FROM chia_overall WHERE querydate > DATE_SUB(NOW(), INTERVAL 24 HOUR)", array()));

                    Promise\all([$historynetspace, $historyprice])->then(function($all_returned) use(&$resolve, $extapidata_returned, $fromtime, $blockchainversion, $netspacedata, $blockheightdata, $chia_price_usd){
                      $historynetspace = $all_returned[0]->resultRows;
                      $historyprice = $all_returned[1]->resultRows;

                      if(count($historynetspace) == 1 && count($historyprice) == 1){
                        $daychange_percent = explode(" ",$extapidata_returned["data"]["netspace"])[0] / explode(" ",$historynetspace[0]["netspace"])[0] * 100 - 100;
                        $daymin_24h_usd = $historyprice[0]["daymin_24h_usd"];
                        $daymax_24h_usd = $historyprice[0]["daymax_24h_usd"];
                        $daychange_24h_percent = $chia_price_usd / $historyprice[0]["daymax_24h_usd"] * 100 - 100;
                      }else{
                        $daychange_percent = 0;
                        $daymin_24h_usd = $chia_price_usd;
                        $daymax_24h_usd = $chia_price_usd;
                        $daychange_24h_percent = 0;
                      }

                      $set_new_overall = Promise\resolve((new DB_Api())->execute("INSERT INTO chia_overall (id, blockchain_version, daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate) VALUES(NULL, ?, ?, ?, ?, NOW(), ?, ?, ?, ?, NOW(), NOW())",
                                                                                  array($blockchainversion, number_format($daychange_percent,4), $netspacedata, $blockheightdata,
                                                                                        $chia_price_usd, $daymin_24h_usd, $daymax_24h_usd, $daychange_24h_percent)));

                      $set_new_overall->otherwise(function (\Exception $e) use(&$resolve){
                        return $resolve($this->logging_api->getErrormessage("queryOverallData", "006", $e));
                      });

                      $resolve($this->getOverallChiaData($fromtime));
                    })->otherwise(function (\Exception $e) use(&$resolve){
                      return $resolve($this->logging_api->getErrormessage("queryOverallData", "005", $e));
                    });
                  }else{
                    return $resolve($this->logging_api->getErrormessage("queryOverallData", "001"));
                  }

                }else{
                  return $resolve($extapidata_returned);
                }
              });
            }else{
              $resolve($this->getOverallChiaData($fromtime));
            } 
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("queryOverallData", "004", $e));
          });
          
        }else{
          $resolve($this->logging_api->getErrormessage("queryOverallData", "003"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the newest overall informaiton stored in the db.
     * @throws Exception $e        Throws an exception on db errors.
     * @todo                       Implement historical data loading.
     * @param  DateTime $fromtime  { NULL } On NULL only the last queryied data is returned. If a DateTime is given it will return historical data newer than. (Currently not implemented)
     * @return array               Returns {"status": [0|>0], "message": [Status message], "data": {[Saved DB Values]}}.
     */
    public function getOverallChiaData(DateTime $fromtime = NULL): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($fromtime){
        if(is_null($fromtime)){
          $chia_overall = Promise\resolve((new DB_Api())->execute("SELECT blockchain_version, daychange_percent, netspace, xch_blockheight, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array()));
          $chia_overall->then(function($chia_overall_returned) use(&$resolve){
            if(array_key_exists("0", $chia_overall_returned->resultRows)){
              $resolve(array("status" => 0, "message" => "Successfully queried chia overall data.", "data" => $chia_overall_returned->resultRows[0]));
            }else{
              return $resolve($this->logging_api->getErrormessage("getOverallChiaData", "001"));
            }
          })->otherwise(function (\Exception $e) use(&$resolve){
            return $resolve($this->logging_api->getErrormessage("getOverallChiaData", "002", $e));
          });
        }else{
          //Return historical data
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Queryies latest data from the external api using the link(s) stored in the config.ini.php.
     * @see https://api.chiaprofitability.com/netspace
     * @see https://api.chiaprofitability.com/market
     * @see https://xchscan.com/api/blocks?limit=10&offset=0
     * @return array Returns {"status": [0|>0], "message": [Status message], "data": {[Found queried external data]}}.
     */
    private function getDataFromExtApi(): object
    {     
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $browser = new Browser();
        $netspace_promise = $browser->get("{$this->ini["xchscan_api"]}/netspace")->then(
          function($netspace_returned){
            return json_decode($netspace_returned->getBody(), true);
          },
          function (\Exception $e) use(&$resolve){
            return $resolve($this->logging_api->getErrormessage("getDataFromExtApi", "001", "The external api {$this->ini["xchscan_api"]} returned an error on netspace query. Message: " . json_encode($e->getMessage())));
          }
        );

        $price_promise = $browser->get("{$this->ini["xchscan_api"]}/chia-price")->then(
          function($price_returned){
            return json_decode($price_returned->getBody(), true);
          },
          function (\Exception $e) use(&$resolve){
            return $resolve($this->logging_api->getErrormessage("002", "The external api {$this->ini["xchscan_api"]} returned an error on price query. Message: " . json_encode($e->getMessage())));
          }
        );

        $blocks_promise = $browser->get("{$this->ini["xchscan_api"]}/blocks?limit=10&offset=0")->then(
          function($blocks_returned){
            return json_decode($blocks_returned->getBody(), true);
          },
          function (\Exception $e) use(&$resolve){
            return $resolve($this->logging_api->getErrormessage("003", "The external api {$this->ini["xchscan_api"]} returned an empty output on block query. Message: " . json_encode($e->getMessage())));
          }
        );

        $chiaversionspath = "https://api.github.com/repos/Chia-Network/chia-blockchain/releases";
        $version_promise = $browser->get($chiaversionspath)->then(
          function($version_returned){
            return json_decode($version_returned->getBody(), true);
          },
          function (\Exception $e) use(&$resolve, $chiaversionspath){
            return $resolve($this->logging_api->getErrormessage("004", "The chia github version file ({$chiaversionspath}) could not be loaded. Message: " . json_encode($e->getMessage())));
          }
        );

        $codes_rates_promise = Promise\all([$netspace_promise, $price_promise, $blocks_promise, $version_promise])->then(function($all_returned) use(&$resolve){
          $xch_netspace_result = $all_returned[0];
          $xch_chiaprice_result = $all_returned[1];
          $xch_height_result = $all_returned[2];
          $chia_version_result = $all_returned[3];

          $base = log($xch_netspace_result["netspace"]) / log(1024);
          $suffix = array("", " kiB", " MiB", " GiB", " TiB", " PiB", " EiB")[floor($base)];
          $xch_netspace_result["netspace"] = number_format(pow(1024, $base - floor($base)),2) . $suffix;
          $xch_height_result = $xch_height_result["blocks"][0]["height"];
          $chia_blockchain_version = $chia_version_result[0]["name"];
          $nowdate = new \DateTime("now");

          $resolve(array("status" => 0, "message" => "Data from external api queried successfully.", "data" => array("netspace" => $xch_netspace_result["netspace"], "chia_price" => $xch_chiaprice_result, "xch_blockheight" => $xch_height_result, "blockchain_version" => $chia_blockchain_version, "timestamp" => $nowdate->format("Y-m-d H:i:s"))));  
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }

?>
