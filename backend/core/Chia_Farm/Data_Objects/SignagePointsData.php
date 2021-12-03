<?php
namespace ChiaMgmt\Chia_Farm\Data_Objects;

class SignagePointsData{
    /** @var array */
    private $proofs;
    /** @var int */
    private $proofcount;
    /** @var string */
    private $challenge_chain_sp;
    /** @var string */
    private $challenge_hash;
    /** @var int */
    private $difficulty;
    /** @var string */
    private $reward_chain_sp;
    /** @var int */
    private $signage_point_index;
    /** @var int */
    private $sub_slot_iters;

    public function __construct(array $reportedSignagePoints){
        if(!array_key_exists("proofs", $reportedSignagePoints) || !is_array($reportedSignagePoints["proofs"])){
            throw new \InvalidArgumentException("The reported data for the key 'proofs' are not fully set. Expected: 'proofs': <array>, but got {" . json_encode($reportedSignagePoints["proofs"]) . "}");
        }
        if(
            !array_key_exists("signage_point", $reportedSignagePoints) || !is_array($reportedSignagePoints["signage_point"]) ||
            !array_key_exists("challenge_chain_sp", $reportedSignagePoints["signage_point"]) || !is_string($reportedSignagePoints["signage_point"]["challenge_chain_sp"]) ||
            !array_key_exists("challenge_hash", $reportedSignagePoints["signage_point"]) || !is_string($reportedSignagePoints["signage_point"]["challenge_hash"]) ||
            !array_key_exists("difficulty", $reportedSignagePoints["signage_point"]) || !is_int($reportedSignagePoints["signage_point"]["difficulty"]) ||
            !array_key_exists("reward_chain_sp", $reportedSignagePoints["signage_point"]) || !is_string($reportedSignagePoints["signage_point"]["reward_chain_sp"]) ||
            !array_key_exists("signage_point_index", $reportedSignagePoints["signage_point"]) || !is_int($reportedSignagePoints["signage_point"]["signage_point_index"]) ||
            !array_key_exists("sub_slot_iters", $reportedSignagePoints["signage_point"]) || !is_int($reportedSignagePoints["signage_point"]["sub_slot_iters"])
        ){
            throw new \InvalidArgumentException("The reported data for the key 'signage_point' are not fully set. Expected: 'signage_point': 
                {'challenge_chain_sp' : <string>, 'challenge_hash' : <string>, 'difficulty' : <int>, 'reward_chain_sp' : <string>, 'signage_point_index' : <int>, 'sub_slot_iters' : <int>}}, but got {" . json_encode($reportedSignagePoints["proofs"]) . "}");
        }

        $this->proofs = json_encode($reportedSignagePoints["proofs"]);
        $this->proofcount = count($reportedSignagePoints["proofs"]);
        $this->challenge_chain_sp = $reportedSignagePoints["signage_point"]["challenge_chain_sp"];
        $this->challenge_hash = $reportedSignagePoints["signage_point"]["challenge_hash"];
        $this->difficulty = $reportedSignagePoints["signage_point"]["difficulty"];
        $this->reward_chain_sp = $reportedSignagePoints["signage_point"]["reward_chain_sp"];
        $this->signage_point_index = $reportedSignagePoints["signage_point"]["signage_point_index"];
        $this->sub_slot_iters = $reportedSignagePoints["signage_point"]["sub_slot_iters"];
    }

    public function get_proofs(): string
    {
        return $this->proofs;
    }

    public function get_proofcount(): int
    {
        return $this->proofcount;
    }

    public function get_challenge_chain_sp(): string
    {
        return $this->challenge_chain_sp;
    }

    public function get_challenge_hash(): string
    {
        return $this->challenge_hash;
    }

    public function get_difficulty(): int
    {
        return $this->difficulty;
    }

    public function get_reward_chain_sp(): string
    {
        return $this->reward_chain_sp;
    }

    public function get_signage_point_index(): int
    {
        return $this->signage_point_index;
    }

    public function get_sub_slot_iters(): int
    {
        return $this->sub_slot_iters;
    }
}