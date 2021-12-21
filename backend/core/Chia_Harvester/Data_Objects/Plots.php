<?php
namespace ChiaMgmt\Chia_Harvester\Data_Objects;

class Plots{
    /** @var int */
    private $file_size; //in bytes
    /** @var string */
    private $filename;
    /** @var string */
    private $plot_seed;
    /** @var string */
    private $plot_id;
    /** @var string */
    private $plot_public_key;
    /** @var string */
    private $pool_contract_puzzle_hash; //in byte
    /** @var string */
    private $pool_public_key;
    /** @var int */
    private $k_size;
    /** @var DateTime */
    private $time_modified; //UNIX Timestamp

    public function __construct(array $reportedfarmdata){
        if(!array_key_exists("file_size", $reportedfarmdata) || !is_int($reportedfarmdata["file_size"]) || is_null($reportedfarmdata["file_size"])){
            throw new \InvalidArgumentException("The reported data for the key 'file_size' are not fully set. Expected: 'file_size': <int>, but got {" . json_encode($reportedfarmdata["file_size"]) . "}");
        }

        if(!array_key_exists("filename", $reportedfarmdata) || !is_string($reportedfarmdata["filename"]) || is_null($reportedfarmdata["filename"])){
            throw new \InvalidArgumentException("The reported data for the key 'filename' are not fully set. Expected: 'filename': <string>, but got {" . json_encode($reportedfarmdata["filename"]) . "}");
        }

        if(!array_key_exists("plot-seed", $reportedfarmdata) || !is_string($reportedfarmdata["plot-seed"]) || is_null($reportedfarmdata["plot-seed"])){
            throw new \InvalidArgumentException("The reported data for the key 'plot_seed' are not fully set. Expected: 'plot-seed': <string>, but got {" . json_encode($reportedfarmdata["plot-seed"]) . "}");
        }

        if(!array_key_exists("plot_id", $reportedfarmdata) || !is_string($reportedfarmdata["plot_id"]) || is_null($reportedfarmdata["plot_id"])){
            throw new \InvalidArgumentException("The reported data for the key 'plot_id' are not fully set. Expected: 'plot_id': <string>, but got {" . json_encode($reportedfarmdata["plot_id"]) . "}");
        }

        if(!array_key_exists("plot_public_key", $reportedfarmdata) || !is_string($reportedfarmdata["plot_public_key"]) || is_null($reportedfarmdata["plot_public_key"])){
            throw new \InvalidArgumentException("The reported data for the key 'plot_public_key' are not fully set. Expected: 'plot_public_key': <string>, but got {" . json_encode($reportedfarmdata["plot_public_key"]) . "}");
        }

        if(!array_key_exists("pool_contract_puzzle_hash", $reportedfarmdata) || !is_string($reportedfarmdata["pool_contract_puzzle_hash"]) || is_null($reportedfarmdata["pool_contract_puzzle_hash"])){
            throw new \InvalidArgumentException("The reported data for the key 'pool_contract_puzzle_hash' are not fully set. Expected: 'pool_contract_puzzle_hash': <string>, but got {" . json_encode($reportedfarmdata["pool_contract_puzzle_hash"]) . "}");
        }

        if(!array_key_exists("size", $reportedfarmdata) || !is_int($reportedfarmdata["size"]) || is_null($reportedfarmdata["size"])){
            throw new \InvalidArgumentException("The reported data for the key 'size' are not fully set. Expected: 'size': <int>, but got {" . json_encode($reportedfarmdata["size"]) . "}");
        }

        if(!array_key_exists("time_modified", $reportedfarmdata) || !is_float($reportedfarmdata["time_modified"]) || is_null($reportedfarmdata["time_modified"])){
            throw new \InvalidArgumentException("The reported data for the key 'time_modified' are not fully set. Expected: 'time_modified': <flot>, but got {" . json_encode($reportedfarmdata["time_modified"]) . "}");
        }

        $this->file_size = $reportedfarmdata["file_size"];
        $this->filename = $reportedfarmdata["filename"];
        $this->plot_seed = $reportedfarmdata["plot-seed"];
        $this->plot_id = $reportedfarmdata["plot_id"];
        $this->plot_public_key = $reportedfarmdata["plot_public_key"];
        $this->pool_contract_puzzle_hash = $reportedfarmdata["pool_contract_puzzle_hash"];
        $this->pool_public_key = (is_null($reportedfarmdata["pool_public_key"]) ? "" : $reportedfarmdata["pool_public_key"]);
        $this->k_size = $reportedfarmdata["size"];
        $this->time_modified = date("Y-m-d H:i:s",$reportedfarmdata["time_modified"]);
    }


    public function get_file_size(): int
    {
        return $this->file_size;
    }

    public function get_filename(): string
    {
        return $this->filename;
    }

    public function get_plot_seed(): string
    {
        return $this->plot_seed;
    }

    public function get_plot_id(): string
    {
        return $this->plot_id;
    }

    public function get_plot_public_key(): string
    {
        return $this->plot_public_key;
    }

    public function get_pool_contract_puzzle_hash(): string
    {
        return $this->pool_contract_puzzle_hash;
    }

    public function get_pool_public_key(): string
    {
        return $this->pool_public_key;
    }

    public function get_k_size(): int
    {
        return $this->k_size;
    }

    public function get_time_modified(): string
    {
        return $this->time_modified;
    }
}