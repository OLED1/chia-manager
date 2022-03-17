<?php
    namespace ChiaMgmt\Alerting\Additional_Functions\Alerting_Services;
    use ChiaMgmt\DB\DB_Api;

    class Alerting_Mail{
        /**
         * Holds an instance to the Database Class.
         * @var DB_Api
         */
        private $db_api;

        public function __construct(){
            $this->db_api = new DB_Api();
        }

        
    }