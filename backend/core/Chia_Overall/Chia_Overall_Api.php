<?php
  namespace ChiaMgmt\Chia_Overall;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Chia_Overall_Api{
    private $db_api, $logging_api;

    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');
    }

    public function queryOverallData(DateTime $fromtime = NULL){
      if(array_key_exists("netspace_api", $this->ini) && array_key_exists("market_api", $this->ini)){
        $netspace_api = $this->ini["netspace_api"];
        $market_api = $this->ini["market_api"];

        $sql = $this->db_api->execute("SELECT querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        $now = new \DateTime("now");
        $lastquerytime = new \DateTime($sqdata[0]["querydate"]);
        $lastquerytime->modify("+5 minutes");

        try{
          if(count($sqdata) == 0 || (count($sqdata) == 1 && ($lastquerytime <= $now))){
            $extapidata = $this->getDataFromExtApi();
            if($extapidata["status"] == 0){
              $netspacedata = $extapidata["data"]["netspace"];
              $marketdata = $extapidata["data"]["market"];

              $sql = $this->db_api->execute("INSERT INTO chia_overall (id, daychange_percent, netspace, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate) VALUES(NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
                                            array($netspacedata["daychange"], $netspacedata["netspace"], $netspacedata["timestamp"],
                                                  $marketdata["price"], $marketdata["daymin"], $marketdata["daymax"], $marketdata["daychange"], $marketdata["timestamp"],
                                                  $now->format("Y-m-d H:i:s"))
                                            );
            }else{
              return $extapidata;
            }
          }

          return $this->getOverallChiaData($fromtime);
        }catch(Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "API values not set in configuration file");
      }
    }

    private function getOverallChiaData(DateTime $fromtime = NULL){
      try{
        if(is_null($fromtime)){
          $sql = $this->db_api->execute("SELECT daychange_percent, netspace, netspace_timestamp, price_usd, daymin_24h_usd, daymax_24h_usd, daychange_24h_percent, market_timestamp, querydate FROM chia_overall WHERE querydate = (SELECT MAX(querydate) FROM chia_overall)", array());
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(array_key_exists("0", $sqdata)){
            return array("status" => 0, "message" => "Successfully queried chia overall data.", "data" => $sqdata[0]);
          }else{
            //TODO Implement correct status code
            return array("status" => 1, "message" => "No rows returned from database. Something does not work correctly");
          }
        }else{
          //Return historical data
        }
      }catch(Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

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

      curl_close($curl);

      if(!$netspace_result["success"]){
        $overall = false;
        //TODO Implement correct status code and log to file
        print_r(array("status" => 1, "message" => "The external api {$this->ini["netspace_api"]} returned an error."));
      }

      if(!$market_result["success"]){
        $overall = false;
        //TODO Implement correct status code and log to file
        print_r(array("status" => 1, "message" => "The external api {$this->ini["market_api"]} returned an error."));
      }

      if($overall){
        $base = log($netspace_result["netspace"]) / log(1024);
        $suffix = array("", " kiB", " MiB", " GiB", " TiB", " PiB", " EiB")[floor($base)];
        $netspace_result["netspace"] = pow(1024, $base - floor($base)) . $suffix;

        $netspace_date = new \DateTime("@" . $netspace_result["timestamp"]);
        $market_date = new \DateTime("@" . $market_result["timestamp"]);

        $netspace_result["timestamp"] = $netspace_date->format("Y-m-d H:i:s");
        $market_result["timestamp"] = $market_date->format("Y-m-d H:i:s");

        return array("status" => 0, "message" => "Data from external api queried successfully.", "data" => array("netspace" => $netspace_result, "market" => $market_result));
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Could not query data from external api please check the log for more information.");
      }
    }
  }
?>
