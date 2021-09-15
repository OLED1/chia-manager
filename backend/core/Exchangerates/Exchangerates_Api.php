<?php
  namespace ChiaMgmt\Exchangerates;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  /**
   * The Exchangerates_Api class contains every needed methods to get/set currency and exchangerates values.
   * The chia overall api and CHIA itself queries the current chia price in USD, so the base currency for converting is USD.
   * @version 0.1.1
   * @author OLED1 - Oliver Edtmair
   * @since 0.1.0
   * @copyright Copyright (c) 2021, Oliver Edtmair (OLED1), Luca Austelat (lucaust)
   */
  class Exchangerates_Api{
    /**
     * Holds a system config json array.
     * @var array
     */
    private $ini;
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
     * Initialises the needed and above stated private variables.
     */
    public function __construct(){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    //Base Currency is always USD
    /**
     * Queryies current exchangerates from an external api using stated config values from the config.ini.php and returns the exchangerate in the given currency code.
     * This function has a query cap from one query per day.
     * Default currency is USD (usd).
     * Function made for: Only WebClient (PHP)
     * @todo Make this function compatible for a future App
     * @throws Exception $e          Throws an exception on db errors.
     * @see https://cdn.jsdelivr.net/gh/fawazahmed0/currency-api@1/latest/currencies.json
     * @see https://cdn.jsdelivr.net/gh/fawazahmed0/currency-api@1/latest/currencies/usd.json
     * @param  string $currency_code The currency code in which the exchangerate should be converted. E.g. eur, to get the exchangerates in Euro.
     * @return array                 {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB found exchangerates]}}
     */
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
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Get all available currencies found in the db.
     * Function made for: Only WebClient (PHP)
     * @todo Make this function compatible for a future App
     * @throws Exception $e Throws an exception on db errors.
     * @return array        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB found currencies]}}
     */
    public function getAllCurrencies(){
      try{
        $sql = $this->db_api->execute("SELECT currency_code, currency_desc FROM exchangerates", array());

        return array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $sql->fetchAll(\PDO::FETCH_ASSOC));
      }catch(Exception $e){
        return $this->logging->getErrormessage("001", $e);
      }
    }

    /**
     * Returns the current default user set-up currency in which the Chia price should be converted.
     * Function made for: Only WebClient (PHP)
     * @todo Make this function compatible for a future App
     * @throws Exception $e       Throws an exception on db errors.
     * @param  int    $userid     The userid of a certain user for which the values should be returned.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB found default user currency]}}
     */
    public function getUserDefaultCurrency(int $userid){
      if($userid > 0 && array_key_exists("user_id", $_COOKIE) && $_COOKIE["user_id"] == $userid){
        try{
          $sql = $this->db_api->execute("SELECT currency_code FROM users_settings WHERE userid = ?", array($userid));
          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

          if(count($sqdata) == 0){
            $defaultCurrencyStatus = $this->setUserDefaultCurrency(array("currency_code" => "usd"), array("userid" => $userid));
            if($defaultCurrencyStatus["status"] == 0){
              $returndata = array("currency_code" => "usd");
            }else{
              return $defaultCurrencyStatus;
            }
          }else{
            $returndata = $sqdata[0];
          }

          return array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $returndata);
        }catch(Exception $e){
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Sets the current default user currency in which the Chia price should be converted.
     * Function made for: Web Client/App
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array    $data       { "currency_code" : "[Currency 3-4 digits code, e.g. usd]" }
     * @param  array    $loginData  { "userid" : [userid] }
     * @return array                { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Newly set default currency]}}
     */
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
          return $this->logging->getErrormessage("001", $e);
        }
      }else{
        return $this->logging->getErrormessage("002");
      }
    }

    /**
     * Returns the currency associated exchangerate with a given 3-4 digits currency-code. Base is USD.
     * Function made for: Backend
     * @throws Exception $e Throws an exception on db errors.
     * @param  string $currency_code  Currency 3-4 digits code, e.g. usd
     * @return array                  { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Currency asocciated exchangerate]}}
     */
    private function getExchangerate(string $currency_code){
      try{
        $sql = $this->db_api->execute("SELECT currency_rate FROM exchangerates WHERE currency_code = ?", array($currency_code));
        $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);

        if(array_key_exists("0", $sqdata)){
          return array("status" => 0, "message" => "Successfully loaded exchangerate from usd to {$currency_code}.", "data" => array($currency_code => $sqdata[0]));
        }else{
          return $this->logging->getErrormessage("001", "Currency {$currency_code} not found or not existing.");
        }
      }catch(Exception $e){
        return $this->logging->getErrormessage("002", $e);
      }
    }

    /**
     * Returns the exchangerate associaated with the configured default exchangerate oof a given user.
     * Function made for: Web Client / App
     * @param  array  $data      { "userid" : [userid] }
     * @param  array $loginData  { NULL } No logindata is needed to query this function.
     * @return array             { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Currency asocciated exchangerate]}}
     */
    public function getUserExchangeData(array $data, array $loginData = NULL){
      if(array_key_exists("userid", $data)){
        $defaultCurrency = $this->getUserDefaultCurrency($data["userid"]);
        if($defaultCurrency["status"] == 0) $defaultCurrency = $defaultCurrency["data"]["currency_code"];
        else $defaultCurrency = "usd";

        $exchangerate = $this->queryExchangeRatesData($defaultCurrency);
        if($exchangerate["status"] == 0 && array_key_exists($defaultCurrency, $exchangerate["data"])){
          $exchangerate = $exchangerate["data"][$defaultCurrency]["currency_rate"];
        }else{
          $defaultCurrency = "usd";
          $exchangerate = 1;
        }

        return array("defaultCurrency" => $defaultCurrency, "exchangerate" => $exchangerate);
      }else{
        return $this->logging->getErrormessage("001");
      }
    }
  }
?>
