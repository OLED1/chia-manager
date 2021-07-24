<?php
  namespace ChiaMgmt\MainOverview;
  use ChiaMgmt\DB\DB_Api;
  use ChiaMgmt\Logging\Logging_Api;

  class MainOverview_Api{
    private $db_api, $logging_api;

    public function __construct(){
      $this->db_api = new DB_Api();
      $this->logging_api = new Logging_Api($this);
    }
  }
?>
