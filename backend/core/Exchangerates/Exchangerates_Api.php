<?php
  namespace ChiaMgmt\Exchangerates;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Exchangerates_Api{

    public function __construct(){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    //Base Currency is always USD
    public function queryExchangeRatesData(string $currency_code){
      if(array_key_exists("exchangerate_api_codes", $this->ini) && array_key_exists("exchangerate_api_rates", $this->ini)){
        try{
          $sql = $this->db_api->execute("SELECT updatedate FROM exchangerates LIMIT 1", array($currency_code));
          $sqreturn = $sql->fetchAll(\PDO::FETCH_ASSOC);

          $now_date = new \DateTime();
          if(array_key_exists("0", $sqreturn)){
            $updatedate = new \DateTime($sqreturn[0]["updatedate"]);
          }else{
            $updatedate = new \DateTime();
            $updatedate->modify("-1 day");
          }
          $updatedate->setTime(00, 00, 00);
          $now_date->setTime(00, 00, 00);

          if($now_date > $updatedate){
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            //Get Codes
            curl_setopt($ch, CURLOPT_URL, $this->ini["exchangerate_api_codes"]);
            $codes_result = json_decode(curl_exec($ch), 1);

            //Get Rates
            curl_setopt($ch, CURLOPT_URL, $this->ini["exchangerate_api_rates"]);
            $rates_result = json_decode(curl_exec($ch), 1);

            curl_close($ch);

            foreach ($codes_result as $currency_code_res => $currency_description) {
              if(array_key_exists("usd", $rates_result) && array_key_exists($currency_code_res, $rates_result["usd"])){
                $sql = $this->db_api->execute("REPLACE INTO exchangerates (currency_code, currency_desc, currency_rate, updatedate) VALUES (?, ?, ?, ?)",
                array($currency_code_res, $currency_description, $rates_result["usd"][$currency_code_res], $rates_result["date"]));
              }
            }
          }

          return $this->getExchangerate($currency_code);
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

    public function getAllCurrencies(){
      try{
        $sql = $this->db_api->execute("SELECT currency_code, currency_desc FROM exchangerates", array());

        return array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function getUserDefaultCurrency(int $userid){
      if($userid > 0){
        try{
          $sql = $this->db_api->execute("SELECT currency_code FROM users_settings WHERE userid = ?", array($userid));
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqdata) == 0){
            $defaulCurrencyStatus = $this->setUserDefaultCurrency(array("currency_code" => "usd"), array("userid" => $userid));
            if($defaulCurrencyStatus["status"] == 0){
              $returndata = array("currency_code" => "usd");
            }else{
              return $defaulCurrencyStatus;
            }
          }else{
            $returndata = $sqdata[0];
          }

          return array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $returndata);
        }catch(Exception $e){
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Wrong userid.");
      }
    }

    public function setUserDefaultCurrency(array $data, array $loginData = NULL){
      if(array_key_exists("currency_code", $data) && array_key_exists("userid", $loginData)){
        try{
          $sql = $this->db_api->execute("SELECT currency_code FROM users_settings WHERE userid = ?", array($loginData["userid"]));
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqdata) == 0){
            $sql = $this->db_api->execute("INSERT INTO users_settings (id, userid, currency_code) VALUES (NULL, ?, ?)", array($loginData["userid"], $data["currency_code"]));
          }else{
            $sql = $this->db_api->execute("UPDATE users_settings SET currency_code = ? WHERE userid = ?", array($data["currency_code"], $loginData["userid"]));
          }

          return array("status" => 0, "message" => "Successfully set default currency to {$data["currency_code"]}.", "data" => $data["currency_code"]);
        }catch(Exception $e){
          //TODO Implement correct status code
          print_r($e);
          return array("status" => 1, "message" => "An error occured.");
        }
      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    private function getExchangerate(string $currency_code){
      try{
        $sql = $this->db_api->execute("SELECT currency_rate FROM exchangerates WHERE currency_code = ?", array($currency_code));
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(array_key_exists("0", $sqdata)){
          return array("status" => 0, "message" => "Successfully loaded exchangerate from usd to {$currency_code}.", "data" => array($currency_code => $sqdata[0]));
        }else{
          return array("status" => 1, "message" => "Currency {$currency_code} not found or not existing.");
        }
      }catch(Exception $e){
        //TODO Implement correct status code
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }
  }
?>
