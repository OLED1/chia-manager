<?php
  namespace ChiaMgmt\DB;
  /**
   * The universal project db connector class.
   */
  class DB_Api{
    private $con;

    /**
     * The constructur initialises the databse instance with the parameters stated in the config file.
     */
    public function __construct(){
      $ini = parse_ini_file(__DIR__.'/../../config/config.ini');

      try{
        $this->con = new \PDO('mysql:dbname='. $ini["db_name"] .';host='. $ini['db_host'], $ini['db_user'], $ini['db_password']);
        $this->con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
      }catch(Exception $e){
        throw new Exception();
      }
    }

    /**
     * The excute function takes the statement and the parameter provided and executes the requested database command.
     * @param  string $statement The Select, Update, Insert command.
     * @param  array  $parameter The parameters if they are needed. They will be inserted in statement where "?" is stated. The values in the array must be sorted to be inserted correctly.
     * @return array  Returns the all data stated by the database command. E.g. Select return values.
     */
    public function execute(string $statement, array $parameter){
      try{
        $con = $this->con;
        $sql=$con->prepare($statement);
        $sql->execute($parameter);

        return $sql;
      }catch(Exception $e){
        throw new Exception($e);
      }
    }
  }
?>