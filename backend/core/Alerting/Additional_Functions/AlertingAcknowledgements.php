<?php
    namespace ChiaMgmt\Alerting\Additional_Functions;
    use ChiaMgmt\DB\DB_Api;

    class AlertingAcknowledgements{
        /**
         * Holds an instance to the Database Class.
         * @var DB_Api
         */
        private $db_api;

        public function __construct(){
            $this->db_api = new DB_Api();
        }
    }
?>