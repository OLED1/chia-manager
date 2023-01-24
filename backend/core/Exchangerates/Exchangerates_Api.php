<?php
  namespace ChiaMgmt\Exchangerates;
  use React\Promise;
  use React\Http\Browser;
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
     * Holds an instance to the Logging Class.
     * @var Logging_Api
     */
    private $logging_api;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * Initialises the needed and above stated private variables.
     */
    public function __construct(object $server = NULL){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->logging_api = new Logging_Api($this, $server);
      $this->server = $server;
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
    public function queryExchangeRatesData(string $currency_code = "usd"): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        if(array_key_exists("exchangerate_api_codes", $this->ini) && array_key_exists("exchangerate_api_rates", $this->ini)){
          $last_updated = Promise\resolve((new DB_Api())->execute("SELECT updatedate FROM exchangerates LIMIT 1", array()));
          $last_updated->then(function($last_updated_returned) use(&$resolve){
            $last_updated_returned = $last_updated_returned->resultRows;

            $now_date = new \DateTime();
            if(array_key_exists("0", $last_updated_returned)){
              $updatedate = new \DateTime($last_updated_returned[0]["updatedate"]);
            }else{
              $updatedate = new \DateTime();
              $updatedate->modify("-1 day");
            }
            $updatedate->setTime(00, 00, 00);
            $now_date->setTime(00, 00, 00);

            if($now_date > $updatedate){
              $browser = new Browser();
              $api_codes_promise = $browser->get($this->ini["exchangerate_api_codes"])->then(
                function($exchangerate_api_codes){
                  return json_decode((string)$exchangerate_api_codes->getBody(), true);
                },
                function (\Exception $e) use(&$resolve){
                  return $resolve($this->logging_api->getErrormessage("queryExchangeRatesData", "003", $e));
                }
              );
              $api_rates_promise = $browser->get($this->ini["exchangerate_api_rates"])->then(
                function($exchangerate_api_rates){
                  return json_decode((string)$exchangerate_api_rates->getBody(), true);
                },
                function (\Exception $e) use(&$resolve){
                  return $resolve($this->logging_api->getErrormessage("queryExchangeRatesData", "004", $e));
                }
              );
              
              $codes_rates_promise = Promise\all([$api_codes_promise, $api_rates_promise])->then(function($all_returned) use(&$resolve){
                $codes_result = $all_returned[0];
                $rates_result = $all_returned[1];
                
                foreach ($codes_result as $currency_code_res => $currency_description){ 
                  if(array_key_exists("usd", $rates_result) && array_key_exists($currency_code_res, $rates_result["usd"])){
                    $last_updated = Promise\resolve((new DB_Api())->execute("INSERT INTO exchangerates (currency_code, currency_desc, currency_rate, updatedate) VALUES (?, ?, ?, ?)", 
                                                      array($currency_code_res, $currency_description, $rates_result["usd"][$currency_code_res], $rates_result["date"])));
                    $last_updated->otherwise(function (\Exception $e) use(&$resolve){
                      return $resolve($this->logging_api->getErrormessage("queryExchangeRatesData", "005", $e));
                    });                
                  }
                }
              });
            }else{
              $codes_rates_promise = Promise\resolve($currency_code);
            }

            $codes_rates_promise->then(function($currency_code) use(&$resolve){
              $new_exchangerates = Promise\resolve($this->getExchangerate($currency_code));
              $new_exchangerates->then(function($new_exchangerates_returned) use(&$resolve){
                $resolve($new_exchangerates_returned);
              });
            });
            $resolve(array("status" => 0, "message" => "Successfully queried new exchangerates from external api."));
          })->otherwise(function (\Exception $e) use(&$resolve){
            print_r($e);
            $resolve($this->logging_api->getErrormessage("queryExchangeRatesData", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("queryExchangeRatesData", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Get all available currencies found in the db.
     * Function made for: Only WebClient (PHP)
     * @todo Make this function compatible for a future App
     * @throws Exception $e Throws an exception on db errors.
     * @return array        {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB found currencies]}}
     */
    public function getAllCurrencies(): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify){
        $all_currencies = Promise\resolve((new DB_Api())->execute("SELECT currency_code, currency_desc FROM exchangerates  ORDER BY currency_code ASC", array()));
        
        $all_currencies->then(function($all_currencies_returned) use(&$resolve){
        $resolve(array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $all_currencies_returned->resultRows));
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getAllCurrencies", "001", $e));
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the current default user set-up currency in which the Chia price should be converted.
     * Function made for: Only WebClient (PHP)
     * @todo Make this function compatible for a future App
     * @throws Exception $e       Throws an exception on db errors.
     * @param  int    $userid     The userid of a certain user for which the values should be returned.
     * @return array              {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[DB found default user currency]}}
     */
    public function getUserDefaultCurrency(int $userid): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($userid){
        if($userid > 0){
          $currency_code = Promise\resolve((new DB_Api())->execute("SELECT currency_code FROM users_settings WHERE userid = ?", array($userid)));
          $currency_code->then(function($currency_code_returned) use(&$resolve, $currency_code){

            if(count($currency_code_returned->resultRows) == 0){
              $set_currency_code = Promise\resolve($this->setUserDefaultCurrency(array("currency_code" => "usd"), array("userid" => $userid)));
              $set_currency_code->then(function ($set_currency_code_returned) use(&$resolve){
                if($set_currency_code_returned["status"] != 0){
                  return $resolve($set_currency_code_returned);
                }
              });

              $returndata = array("currency_code" => "usd");
            }else{
              $returndata = $currency_code_returned->resultRows[0];
            }
            
            $resolve(array("status" => 0, "message" => "Successfully loaded all available currencies.", "data" => $returndata));
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("getUserDefaultCurrency", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("getUserDefaultCurrency", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Sets the current default user currency in which the Chia price should be converted.
     * Function made for: Web Client/App
     * @throws Exception $e       Throws an exception on db errors.
     * @param  array    $data       { "currency_code" : "[Currency 3-4 digits code, e.g. usd]" }
     * @param  array    $loginData  { "userid" : [userid] }
     * @return array                { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Newly set default currency]}}
     */
    public function setUserDefaultCurrency(array $data, array $loginData): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data, $loginData){
        if(array_key_exists("currency_code", $data) && array_key_exists("userid", $loginData)){
          $currency_code = Promise\resolve((new DB_Api())->execute("SELECT currency_code FROM users_settings WHERE userid = ?", array($loginData["userid"])));
          $currency_code->then(function($currency_code_returned) use(&$resolve, $currency_code, $loginData, $data){

            if(count($currency_code_returned->resultRows) == 0){
              $set_currency_code = Promise\resolve((new DB_Api())->execute("INSERT INTO users_settings (id, userid, currency_code) VALUES (NULL, ?, ?)", array($loginData["userid"], $data["currency_code"])));
            }else{
              $set_currency_code = Promise\resolve((new DB_Api())->execute("UPDATE users_settings SET currency_code = ? WHERE userid = ?", array($data["currency_code"], $loginData["userid"])));
            }

            $set_currency_code->then(function($set_currency_code_returned) use(&$resolve, $data){
              $resolve(array("status" => 0, "message" => "Successfully set default currency to {$data["currency_code"]}.", "data" => $data["currency_code"]));
            })->otherwise(function (\Exception $e) use(&$resolve){
              $resolve($this->logging_api->getErrormessage("setUserDefaultCurrency", "003", $e));
            });
          })->otherwise(function (\Exception $e) use(&$resolve){
            $resolve($this->logging_api->getErrormessage("setUserDefaultCurrency", "001", $e));
          });
        }else{
          $resolve($this->logging_api->getErrormessage("setUserDefaultCurrency", "002"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the currency associated exchangerate with a given 3-4 digits currency-code. Base is USD.
     * Function made for: Backend
     * @throws Exception $e Throws an exception on db errors.
     * @param  string $currency_code  Currency 3-4 digits code, e.g. usd
     * @return array                  { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Currency asocciated exchangerate]}}
     */
    private function getExchangerate(string $currency_code): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($currency_code){
        $exchage_rate = Promise\resolve((new DB_Api())->execute("SELECT currency_rate FROM exchangerates WHERE currency_code = ?", array($currency_code)));
        $exchage_rate->then(function($exchage_rate_returned) use(&$resolve, $currency_code){
          if(array_key_exists("0", $exchage_rate_returned->resultRows)){
            $resolve(array("status" => 0, "message" => "Successfully loaded exchangerate from usd to {$currency_code}.", "data" => array($currency_code => $exchage_rate_returned->resultRows[0])));
          }else{
            $resolve($this->logging_api->getErrormessage("getExchangerate", "001", "Currency {$currency_code} not found or not existing."));
          }
        })->otherwise(function (\Exception $e) use(&$resolve){
          $resolve($this->logging_api->getErrormessage("getExchangerate", "002", $e));
        });

        return;
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Returns the exchangerate associaated with the configured default exchangerate oof a given user.
     * Function made for: Web Client / App
     * @param  array  $data      { "userid" : [userid] }
     * @param  array $loginData  { NULL } No logindata is needed to query this function.
     * @return array             { "status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Currency asocciated exchangerate]}}
     */
    public function getUserExchangeData(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("userid", $data)){
          $defaultCurrency = Promise\resolve($this->getUserDefaultCurrency($data["userid"]));
          $defaultCurrency->then(function($defaultCurrency_returned) use(&$resolve){
            if($defaultCurrency_returned["status"] == 0) $defaultCurrency = $defaultCurrency_returned["data"]["currency_code"];
            else $defaultCurrency = "usd";

            $currentExchangeRate = Promise\resolve($this->getExchangerate($defaultCurrency));
            $currentExchangeRate->then(function($currentExchangeRate_returned) use(&$resolve, $defaultCurrency){
              $exchangerate = 1;
              if(array_key_exists("data", $currentExchangeRate_returned)){
                $exchangerate = $currentExchangeRate_returned["data"][$defaultCurrency]["currency_rate"];
              }
              $resolve(array("status" => 0, "message" => "Successfully loaded exchange data for user.", "data" => array("defaultCurrency" => $defaultCurrency, "exchangerate" => $exchangerate)));
            });
          });
        }else{
          $resolve($this->logging_api->getErrormessage("getUserExchangeData", "001"));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
