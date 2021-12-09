<?php
  namespace ChiaMgmt\DB;
  /**
   * The universal project db connector class.
   */
  class DB_Api{
    /**
     * Holds an instance to the database.
     * @var PDO
     */
    private $con;

    /**
     * The constructur initialises the databse instance with the parameters stated in the config file.
     */
    public function __construct(){
      $config_file = __DIR__.'/../../config/config.ini.php';
      if(file_exists($config_file)){
        $ini = parse_ini_file($config_file);

        if(array_key_exists("db_name", $ini)){
          try{
            $this->con = new \PDO("mysql:dbname={$ini["db_name"]};host={$ini['db_host']}", $ini['db_user'], $ini['db_password']);
            $this->con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
          }catch(\Exception $e){
            throw new \Exception($e);
          }
        }
      }
    }

    /**
     * Tests if a database connection can be established using stated parameters.
     * @param  string $db_name       The database name where the connection should be made.
     * @param  string $db_host       The database host where the connection should be made.
     * @param  string $db_user       The database user which should be used for the connection.
     * @param  string $db_password   The database password which should be used for the connection.
     * @return array                 An positive status code array or an exception.
     */
    public function testConnection(string $db_name, string $db_host, string $db_user, string $db_password): array
    {
      try{
        $this->con = new \PDO("mysql:dbname={$db_name};host={$db_host}", $db_user, $db_password);
        $this->con->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return array("status" => 0, "message" => "Database connection successfull.");
      }catch(\Exception $e){
        throw new \Exception($e);
      }
    }

    /**
     * The excute function takes the statement and the parameter provided and executes the requested database command.
     * @param  string $statement The Select, Update, Insert command.
     * @param  array  $parameter The parameters if they are needed. They will be inserted in statement where "?" is stated. The values in the array must be sorted to be inserted correctly.
     * @return array  Returns the all data stated by the database command. E.g. Select return values.
     */
    public function execute(string $statement, array $parameter): object
    {
      try{
        $sql = $this->con->prepare($statement);
        $sql->execute($this->removeHTMLEntities($parameter));

        return $sql;
      }catch(\Exception $e){
        throw new \Exception($e);
      }
    }

    /**
     * Returns the id of the last inserted command execute.
     *
     * @return integer Returns the last insert id.
     */
    public function lastInsertId(): int{
      return $this->con->lastInsertId();
    }

    /**
     * This method prevents JavaScript injection before statements are put to database.
     * @param  array $parameter The mysql parameters list.
     * @return array            Returns the cleaned up parameters list.
     */
    private function removeHTMLEntities(array $parameter): array
    {
      $cleanedup = [];
      foreach($parameter AS $arrkey => $parameter){
        $cleanedup[$arrkey] = htmlentities($parameter, ENT_NOQUOTES);
      }

      return $cleanedup;
    }
  }
?>
