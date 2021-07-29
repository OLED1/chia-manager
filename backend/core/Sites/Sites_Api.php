<?php
  namespace ChiaMgmt\Sites;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class Sites_Api{
    private $db_api, $logging_api, $ini;

    public function __construct(){
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini');

      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }

    public function getSiteInfos(array $data, array $loginData = NULL){
      if(array_key_exists("siteid", $data)){
        $siteid = $data["siteid"];
        $returndata = [];

        try{
          if(is_null($siteid)){
            $sql = $this->db_api->execute("SELECT id, namespace FROM sites", array());
          }else if($siteid > 0){
            $sql = $this->db_api->execute("SELECT id, namespace FROM sites WHERE id = ?", array($siteid));
          }else{
            //return array("status" => 1, "message" => "Value siteid must be NULL or greater 0.");
            return $this->logging->getErrormessage("001");
          }

          $sqdata = $sql->fetchAll(\PDO::FETCH_ASSOC);
          foreach ($sqdata as $arrkey => $sitesvalues) {
            $returndata["by-id"][$sitesvalues["id"]] = $sitesvalues;
            $returndata["by-namespace"][$sitesvalues["namespace"]] = $sitesvalues;
          }

          return array("status" => 0, "message" => "Successfully loaded site(s) information.", "data" => $returndata);
        }catch(Exception $e){
          /*print_r($e);
          return array("status" => 1, "message" => "An error occured.");*/
          return $this->logging->getErrormessage("002", $e);
        }
      }else{
        //return array("status" => 1, "message" => "No all data stated.");
        return $this->logging->getErrormessage("003");
      }
    }
  }
?>
