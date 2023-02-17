<?php
  namespace ChiaMgmt\Logging;
  use React\Promise;
  use React\ChildProcess;
  use React\EventLoop\Loop;
  use React\Filesystem\Factory;
  use React\Filesystem\Node\FileInterface;
  use React\Filesystem\Stat;

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
        Factory::create()->detect($this->logpath)->then(function (FileInterface $file) use(&$resolve, $loglevel, $errorcode, $text){
          $text = str_replace(PHP_EOL, '', $text);
          $errortext = date("Y/m/d H:i:s") . ";" . $loglevel . ";" . $errorcode . ";". str_replace(";","--",$text) . ";\n";
          $file->putContents($errortext, \FILE_APPEND)->then(function (string $contents) use(&$resolve){
            Promise\resolve($this->server->messageFrontendClients(array("siteID" => 11), array("logsChanged" => $this->getMessagesFromFile(["fromline" => 0, "toline" => 0]))));
            $resolve(array("status" => 0, "message" => "Message has been logged on " . date("Y-m-d H:i:s") . "."));
          }, function (\Throwable $e) use(&$resolve){
            $resolve("Message could not be logged. Logging Error: " . $e->getMessage() . ".");
          });
        });
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
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
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
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
    public function getMessagesFromFile(array $data): object
    {
      $resolver = function (callable $resolve, callable $reject, callable $notify) use($data){
        if(array_key_exists("fromline", $data) && array_key_exists("toline", $data) && is_numeric($data["fromline"]) && is_numeric($data["toline"]) && $data["toline"] >= $data["fromline"]){
          $filesize = 0;
          $fromline = $data["fromline"];
          $toline = $data["toline"];
          
          Factory::create()->detect($this->logpath)->then(function (FileInterface $file){
            return $file->stat();
          })->then(static function (Stat $stat) use(&$filesize){
            $filesize = $stat->size();
          }, function (\Throwable $throwable) {
            $resolve("Message could not be logged. Logging Error: " . $e->getMessage() . ".");
          });

          Factory::create()->detect($this->logpath)->then(function (FileInterface $file) use(&$resolve, $filesize, $fromline, $toline){
            $line_delimiter_count = 4;
            $logs_to_return = [];
            
            $byte_buffer = ceil((($toline - $fromline) >= 50 ? ($toline - $fromline) / 5 : 2));
            $chunks_to_load = $byte_buffer*1024;
            $current_chunk = $filesize;
            $removed_offset = 0;
            
            $loop = Loop::get();
            $loop->addPeriodicTimer(0, function($timer) use(&$resolve, $file, $loop, $fromline, $toline, &$chunks_to_load, &$logs_to_return, &$current_chunk, &$removed_offset, $line_delimiter_count){              
              $file->getContents(($current_chunk-$chunks_to_load), ($chunks_to_load+$removed_offset))->then(function (string $contents) use(&$chunks_to_load, &$logs_to_return, &$current_chunk, &$removed_offset, $line_delimiter_count){
                $removed_offset = 0;
                $current_chunk -= $chunks_to_load;
 
                $csv_array = str_getcsv(str_replace(PHP_EOL, '', $contents),";");
                foreach($csv_array AS $arrkey => $value){
                  if (\DateTime::createFromFormat('Y/m/d H:i:s', $value) !== false){
                    break;
                  }else{
                    $removed_offset += strlen($value)+1;
                    unset($csv_array[$arrkey]);
                  }
                }
  
                $filtered_csv_array = array_filter($csv_array, (function ($var){ return $var !== NULL && $var !== FALSE && $var !== ""; } ));
                $chunked_csv_array = array_chunk($filtered_csv_array, $line_delimiter_count);
                $reversed_csv_array = array_reverse($chunked_csv_array);

                if(count($logs_to_return) == 0){
                  $logs_to_return = $reversed_csv_array;
                }else{ 
                  $logs_to_return = array_merge($logs_to_return, $reversed_csv_array);
                }
              }, function (\Throwable $throwable) {
                $resolve("Message could not be logged. Logging Error: " . $e->getMessage() . ".");
              });
                            
              if((array_key_exists($fromline, $logs_to_return) && array_key_exists($toline, $logs_to_return)) || ($current_chunk+$chunks_to_load+$removed_offset) < 0){
                $loop->cancelTimer($timer);
                foreach($logs_to_return AS $key => $value){
                  if($key < $fromline || $key > $toline) unset($logs_to_return[$key]);
                }
                $resolve(array("status" => 0, "message" => "Logs successfully loaded.","data" => $logs_to_return));
              }
            });
          });
        }
      };

      $canceller = function () {
        throw new \Exception('Promise cancelled');
      };

      return new Promise\Promise($resolver, $canceller);
    }
  }
?>
