<?php
  namespace ChiaMgmt\Logging;
  use React\Promise;
  use React\ChildProcess;
  use ChiaMgmt\WebSocketClient\WebSocketClient_Api;

  /**
   * The logging api is the core of the project's logging.
   * This class uses defined error messages in the errorcode folder and returns the given messages correcty with log levels formatted and so on.
   * Furthermore it writes extended output into the logfile defaultly stored in project-root/logs/api.log
   */
  class Logging_Api{
    /**
     * Holds the $this-instance from the calling class.
     * @var Object
     */
    private $callerClass;
    /**
     * Holds a system config json array.
     * @var array
     */
    private $ini;
    /**
     * Holds the path where log messages for the api are written and stored locally (not in DB).
     * @var string
     */
    private $logpath;
    /**
     * Holds a path where the sitecodes translation file for logging-enabled classes are stored.
     * @var string
     */
    private $codefilepath;
    /**
     * Holds the path where function translation and message files for logging-enabled classes are stored.
     * @var string
     */
    private $errorfilepath;
    /**
     * Holds an instance to the Webocket Server Class.
     * @var WebSocketServer
     */
    private $server;

    /**
     * The constructor initializes all paths which are needed to work properly.
     * @param object $caller The caller class ($this)
     */
    public function __construct(Object $caller = NULL, object $server = NULL){
      if($caller != NULL) $this->callerClass = explode('\\', get_class($caller))[1];
      else $this->callerClass = explode('\\', get_class($this))[1];
      $this->ini = parse_ini_file(__DIR__.'/../../config/config.ini.php');
      $this->server = $server;

      $projectroot = realpath(__DIR__."/../../../");
      $this->logpath = "{$projectroot}/logs/api.log";
      if(array_key_exists("log_root", $this->ini)){
        $this->logpath = "{$projectroot}{$this->ini["log_root"]}/api.log";
      }

      if(!is_dir(dirname($this->logpath))){
        mkdir(dirname($this->logpath), 0755, true);
      }

      $this->codefilepath = __DIR__."/errorcodes/sitecodes.json";
      $this->errorfilepath = __DIR__."/errorcodes/";
    }

    /**
     * Logs messages to the api.log file.
     * @param  int $loglevel     The loglevel of the message (0 Info, 1 Warning/Fatal)
     * @param  string $errorcode The errorcode 0 for success or a generated errorcode (e.g. 001002003 -> 001 = Class ID, 002 = Function ID, 003 = Message Order ID in particular function)
     * @param  string $text      The messagetext
     */
    public function logtofile(int $loglevel, string $errorcode, string $text){
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($loglevel, $errorcode, $text){
        $cmd = "php " . __DIR__ . "/Logging_Background.php '{$loglevel}' '{$errorcode}' '{$text}'";
      
        $process = new ChildProcess\Process($cmd);
        $process->start();
  
        $process->stdout->on('data', function ($chunk) use($resolve){
          $returned_message = (!is_null($chunk) ? json_decode($chunk, true) : array());

          if(!is_null($returned_message) && array_key_exists("status", $returned_message) && $returned_message["status"] == 0){
            $resolve(array("status" => 0, "message" => "Message has been logged on " . date("Y-m-d H:i:s") . "."));
            if(!is_null($this->server)){
              Promise\resolve($this->server->messageFrontendClients(array("siteID" => 11), array("logsChanged" => $this->getMessagesFromFile(["fromline" => 0, "toline" => 1]))));
            }
          }else if(!is_null($returned_message) && array_key_exists("data", $returned_message) && $returned_message["status"] != 0){
            $resolve("Message could not be logged. Logging Error: " . $returned_message["data"] . ".");
          }else{
            $resolve("Message could not be logged. UNKNOWN Error.");
          }
        });
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }

    /**
     * Loads the errorcode files, generates a errorcode from it and return a complete error message for debugging.
     * @param  string $functioncode The functions order ID socalled functioncode
     * @param  string $additional   Additional Info which should only logged to the file (if it contains 'false' this message will not be logged)
     * @return array                {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]"}
     */
    public function getErrormessage(string $methodname, string $functioncode, string $additional = "", bool $logtofile = true): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($methodname, $functioncode, $additional, $logtofile){
        $sitecodefile = @file_get_contents($this->codefilepath);
        $sitecoderarray = json_decode($sitecodefile, true);
  
        $errorfile = file_get_contents($this->errorfilepath . $this->callerClass . "_codes.json");
        $errorarray = json_decode($errorfile, true);
  
        $sitecode = $sitecoderarray[$this->callerClass];
  
        if(isset($errorarray["functioncodes"]) && isset($errorarray["errormessages"]) &&
           isset($errorarray["functioncodes"][$methodname]) && isset($errorarray["errormessages"][$methodname]) &&
           isset($errorarray["errormessages"][$methodname][$functioncode])
          ){
          $functionID = $errorarray["functioncodes"][$methodname];
          $errormessage = $errorarray["errormessages"][$methodname][$functioncode];
          $errorcode = "$sitecode$functionID$functioncode";
  
          if(is_array($errormessage)){
            $loglevel = $errormessage[0];
            $messagetoshow = $errorcode . ": " . $this->getHReadableLoglevel($loglevel) . " " . $errormessage[1];
            $messagetolog = (strlen($additional) > 0 ? $additional : $errormessage[1]);
          }else{
            $loglevel = 3;
            $messagetoshow = $errorcode . ": " . $this->getHReadableLoglevel($loglevel) . " " . $errormessage;
            $messagetolog = (strlen($additional) > 0 ? $additional : $errormessage);
          }
  
          if($logtofile){
            Promise\resolve($this->logtofile($loglevel,$errorcode,$messagetolog));
          }
  
          $resolve(array("status" => $errorcode, "loglevel" => $loglevel, "message" => $messagetoshow));
        }else{
          $resolve(array("status" => "999999999", "message" => "No errormessage found. Please check the corresponding errorcodefile or caller method function parameters."));
        }
      };

      $canceller = function () {
        throw new Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
      
      $sitecodefile = @file_get_contents($this->codefilepath);
      $sitecoderarray = json_decode($sitecodefile, true);

      $errorfile = file_get_contents($this->errorfilepath . $this->callerClass . "_codes.json");
      $errorarray = json_decode($errorfile, true);

      $sitecode = $sitecoderarray[$this->callerClass];

      if(isset($errorarray["functioncodes"]) && isset($errorarray["errormessages"]) &&
         isset($errorarray["functioncodes"][$methodname]) && isset($errorarray["errormessages"][$methodname]) &&
         isset($errorarray["errormessages"][$methodname][$functioncode])
        ){
        $functionID = $errorarray["functioncodes"][$methodname];
        $errormessage = $errorarray["errormessages"][$methodname][$functioncode];
        $errorcode = "$sitecode$functionID$functioncode";

        if(is_array($errormessage)){
          $loglevel = $errormessage[0];
          $messagetoshow = $errorcode . ": " . $this->getHReadableLoglevel($loglevel) . " " . $errormessage[1];
          $messagetolog = (strlen($additional) > 0 ? $additional : $errormessage[1]);
        }else{
          $loglevel = 3;
          $messagetoshow = $errorcode . ": " . $this->getHReadableLoglevel($loglevel) . " " . $errormessage;
          $messagetolog = (strlen($additional) > 0 ? $additional : $errormessage);
        }

        if($logtofile) $this->logtofile($loglevel,$errorcode,$messagetolog.";");

        return array("status" => $errorcode, "loglevel" => $loglevel, "message" => $messagetoshow);
      }else{
        return array("status" => "999999999", "message" => "No errormessage found. Please check the corresponding errorcodefile or caller method function parameters.");
      }
    }

    /**
     * Converts a numeric loglevel into a string.
     * @param  int    $loglevel  Target loglevel as int.
     * @return string            The converted loglevel as string.
     */
    private function getHReadableLoglevel(int $loglevel): string
    {
      switch ($loglevel) {
        case 0:
          return "[Info]";
        case 1:
          return "[Warning]";
        case 2:
          return "[Fatal]";
        case 3:
          return "[Unkown]";
        default:
          return "[Unkown]";
      }
    }

    /**
     * Loads all messages from the api.log file.
     * @param  int    $fromline Which line should be the first one (If i don't need the whole files content)
     * @param  int    $toline   Which line should be the last one (If i don't need the whole files content)
     * @return array            A status code array with the needed data
     */
     //public function getMessagesFromFile(int $fromline, int $toline){
    public function getMessagesFromFile(array $data): array
    {
      if(array_key_exists("fromline", $data) && array_key_exists("toline", $data) && is_numeric($data["fromline"]) && is_numeric($data["toline"])){
        $logarray = [];
        $fromline = $data["fromline"];
        $toline = $data["toline"];

        $loggingfile = new \SplFileObject($this->logpath);

        if($loggingfile) {
          $loggingfile->seek(PHP_INT_MAX); // cheap trick to seek to EoF
          $total_lines = $loggingfile->key(); // last line number

          if($toline <= $total_lines || $fromline <= $total_lines){
            if($toline > $total_lines) $toline = $total_lines;

            $i = $toline;
            $reader = new \LimitIterator($loggingfile, $total_lines - $toline);
            foreach ($reader as $line) {
              $splittedline = explode(";", $line);
              if(count($splittedline) > 1){
                //echo $line; // includes newlines
                $logarray[$i] = $splittedline;
                if($i == $fromline) break;
                $i--;
              }
            }
          }
        }else{
          return array("status" => 1, "message" => "An error occured.");
        }
        return array("status" => 0, "message" => "Logs successfully loaded.","data" => $logarray);
      }else{

      }
      //$loggingfile = fopen($this->logpath, 'r') or die("Unable to open file!");
    }

    /**
     * Returns all logs newer than an given timestamp.
     * @return array {"status": [0|>0], "message": "[Success-/Warning-/Errormessage]", "data": {[Locally found log-messages]}}
     */
    public function getNewerLogsFromTimestamp(DateTime $lastData): array
    {
      $logarray = [];
      $loggingfile = new SplFileObject($this->logpath);

      if($loggingfile){
        $loggingfile->seek(PHP_INT_MAX); // cheap trick to seek to EoF
        $total_lines = $loggingfile->key(); // last line number

        $reader = new LimitIterator($loggingfile, 0);
        foreach ($reader as $line) {
          $splittedline = explode(";", $line);
          if(count($splittedline) > 1){
            $logdate = new DateTime($splittedline[0]);

            if($logdate >= $lastData){
              array_push($logarray, $splittedline);
            }
          }
        }

        return array("status" => 0, "message" => "Successfully loaded new logs.", "data" => $logarray);
      }else{
        return array("status" => 1, "message" => "An error occured.");
      }
    }
  }
?>
