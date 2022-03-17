<?php
namespace ChiaMgmt\Chia_Farm\Data_Objects;

class Farmdata{
    /** @var int */
    private $total_chia_farmed; //farmed_amount in mojo
    /** @var int */
    private $block_rewards; //farmer_reward_amount in mojo
    /** @var int */
    private $user_transaction_fees; //fee_amount in mojo
    /** @var int */
    private $last_height_farmed; //last_height_farmed
    /** @var int */
    private $expected_time_to_win; //in minutes
    /** @var int */
    private $total_size_of_plots; //in byte
    /** @var int */
    private $farming_status;
    /** @var int */
    private $plot_count;
    /** @var int */
    private $estimated_network_space; //in byte

    public function __construct(array $reportedfarmdata){
        if(!array_key_exists("expected_time_to_win", $reportedfarmdata) || !is_int($reportedfarmdata["expected_time_to_win"]) || is_null($reportedfarmdata["expected_time_to_win"])){
            throw new \InvalidArgumentException("The reported data for the key 'expected_time_to_win' are not fully set. Expected: 'expected_time_to_win': <int>, but got {" . json_encode($reportedfarmdata["expected_time_to_win"]) . "}");
        }
        if(!array_key_exists("total_size_of_plots", $reportedfarmdata) || !is_int($reportedfarmdata["total_size_of_plots"]) || is_null($reportedfarmdata["total_size_of_plots"])){
            throw new \InvalidArgumentException("The reported data for the key 'total_size_of_plots' are not fully set. Expected: 'total_size_of_plots': <int>, but got {" . json_encode($reportedfarmdata["total_size_of_plots"]) . "}");
        }
        if(!array_key_exists("plot_count", $reportedfarmdata) || !is_int($reportedfarmdata["plot_count"])){
            throw new \InvalidArgumentException("The reported data for the key 'plot_count' are not fully set. Expected: 'plot_count': <int>");
        }
        if(!array_key_exists("estimated_network_space", $reportedfarmdata) || (!is_double($reportedfarmdata["estimated_network_space"]) && !is_int($reportedfarmdata["estimated_network_space"]))){
            throw new \InvalidArgumentException("The reported data for the key 'estimated_network_space' are not fully set. Expected: 'estimated_network_space': <double>, but got {" . json_encode($reportedfarmdata["estimated_network_space"]) . "}");
        }

        $this->expected_time_to_win = $reportedfarmdata["expected_time_to_win"];
        $this->total_size_of_plots = $reportedfarmdata["total_size_of_plots"];
        $this->plot_count = $reportedfarmdata["plot_count"];

        if(array_key_exists("total_chia_farmed", $reportedfarmdata) && !is_array($reportedfarmdata["total_chia_farmed"])){
            $this->total_chia_farmed = $reportedfarmdata["farmed_amount"]["farmed_amount"];
            $this->user_transaction_fees = $reportedfarmdata["farmed_amount"]["fee_amount"];
            $this->block_rewards = $reportedfarmdata["farmed_amount"]["farmer_reward_amount"] + $reportedfarmdata["farmed_amount"]["pool_reward_amount"];
            $this->last_height_farmed = $reportedfarmdata["farmed_amount"]["last_height_farmed"];
          }else{
            $this->total_chia_farmed = 0;
            $this->user_transaction_fees = 0;
            $this->block_rewards = 0;
            $this->last_height_farmed = 0;
          }

        if(is_null($reportedfarmdata["farming_status"])) $this->farming_status = 1; //Not synced or not connected to peers
        else if($reportedfarmdata["farming_status"]["sync_mode"]) $this->farming_status = 0; //Syncing  
        else if(!$reportedfarmdata["farming_status"]["synced"]) $this->farming_status = 1; //Not synced or not connected to peers
        else $this->farming_status = 2; //Farming

        $this->estimated_network_space = $reportedfarmdata["estimated_network_space"];
    }

    public function get_total_chia_farmed(): int
    {
        return $this->total_chia_farmed;
    }

    public function get_block_rewards(): int
    {
        return $this->block_rewards;
    }

    public function get_user_transaction_fees(): int
    {
        return $this->user_transaction_fees;
    }

    public function get_last_height_farmed(): int
    {
        return $this->last_height_farmed;
    }

    public function get_expected_time_to_win(): int
    {
        return $this->expected_time_to_win;
    }

    public function get_total_size_of_plots(): int
    {
        return $this->total_size_of_plots;
    }

    public function get_farming_status(): int
    {
        return $this->farming_status;
    }

    public function get_plot_count(): int
    {
        return $this->plot_count;
    }

    public function get_estimated_network_space(): float
    {
        return $this->estimated_network_space;
    }
}