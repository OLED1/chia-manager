<?php
  namespace ChiaMgmt\MainOverview;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Chia_Overall\Chia_Overall_Api;
  use ChiaMgmt\Exchangerates\Exchangerates_Api;
  use ChiaMgmt\Chia_Wallet\Chia_Wallet_Api;
  use ChiaMgmt\Chia_Farm\Chia_Farm_Api;
  use ChiaMgmt\Chia_Harvester\Chia_Harvester_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class MainOverview_Api{
    private $db_api, $logging_api, $chia_overall_api, $exchangerates_api, $chia_wallet_api, $chia_farm_api, $chia_harvester_api;

    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);

      $this->chia_overall_api = new Chia_Overall_Api();
      $this->exchangerates_api = new Exchangerates_Api();
      $this->chia_wallet_api = new Chia_Wallet_Api();
      $this->chia_farm_api = new Chia_Farm_Api();
      $this->chia_harvester_api = new Chia_Harvester_Api();
    }

    public function getAllOverviewData(){
      $returndata = [];

      //Exchangerateinfos
      $returndata["currency"] = $this->getExchangeData();

      //Chia Overall Infos
      $chiaoverall = $this->chia_overall_api->queryOverallData();
      if($chiaoverall["status"] == 0) $returndata["chia-overall"] = $chiaoverall["data"];
      else $returndata["chia-overall"] = [];

      //Walletinfos
      $walletData = $this->chia_wallet_api->getWalletData();
      if($walletData["status"] == 0) $returndata["walletinfos"] = $walletData["data"];
      else $returndata["walletinfos"] = [];

      //Farminfos
      $farmData = $this->chia_farm_api->getFarmData();
      if($farmData["status"] == 0) $returndata["farminfos"] = $farmData["data"];
      else $returndata["farminfos"] = [];

      //Harvesterinfos
      $harvesterData = $this->chia_harvester_api->getHarvesterData();
      if($harvesterData["status"] == 0) $returndata["harvesterinfos"] = $harvesterData["data"];
      else $returndata["harvesterinfos"] = [];

      return array("status" => 0, "message" => "Successfully loaded all overview data.", "data" => $returndata);
    }

    private function getExchangeData(){
      $defaultCurrency = $this->exchangerates_api->getUserDefaultCurrency($_COOKIE["user_id"]);
      if($defaultCurrency["status"] == 0) $defaultCurrency = $defaultCurrency["data"]["currency_code"];
      else $defaultCurrency = "usd";

      $exchangerate = $this->exchangerates_api->queryExchangeRatesData($defaultCurrency);
      if($exchangerate["status"] == 0 && array_key_exists($defaultCurrency, $exchangerate["data"])){
        $exchangerate = $exchangerate["data"][$defaultCurrency]["currency_rate"];
      }else{
        $defaultCurrency = "usd";
        $exchangerate = 1;
      }

      return array("defaultCurrency" => $defaultCurrency, "exchangerate" => $exchangerate);
    }
  }
?>
