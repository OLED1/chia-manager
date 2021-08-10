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
          $sql = $this->db_api->execute("SELECT updatedate FROM exchangertates LIMIT 1", array($currency_code));
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

            foreach ($codes_result as $currency_code => $currency_description) {
              if(array_key_exists("usd", $rates_result) && array_key_exists($currency_code, $rates_result["usd"])){
                $sql = $this->db_api->execute("REPLACE INTO exchangertates (currency_code, currency_desc, currency_rate, updatedate) VALUES (?, ?, ?, ?)",
                array($currency_code, $currency_description, $rates_result["usd"][$currency_code], $rates_result["date"]));
              }
            }
          }
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
        $sql = $this->db_api->execute("SELECT currency_code, currency_desc FROM exchangertates", array());

        return array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        print_r($e);
        return array("status" => 1, "message" => "An error occured.");
      }
    }

    public function getUserDefaultCurrency(){
      
    }

    public function setUserDefaultCurrency(array $data, array $loginData = NULL){
      if(array_key_exists("currencie", $data)){

      }else{
        //TODO Implement correct status code
        return array("status" => 1, "message" => "Not all data stated.");
      }
    }

    private function getExchangerate(string $currency_code){

    }


  }

?>
