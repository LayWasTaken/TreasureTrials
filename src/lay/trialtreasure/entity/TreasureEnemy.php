<?php

namespace Lay\TrialTreasure\entity;

use Lay\TrialTreasure\TrialTreasure;
use Lay\TrialTreasure\TrialTreasureMain;
use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\Position;

abstract class TreasureEnemy extends Living {

    const TRIAL_ORIGIN = "origin";

    protected int $originCheck = 0;

    public static function create(Location $location, ?TrialTreasure $origin = null, ?CompoundTag $nbt = null){
        return new static($location, $nbt, $origin);
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null, protected ?TrialTreasure $origin = null){
        parent::__construct($location, $nbt);
        if(!$origin) {
            $this->flagForDespawn();
        }
    }

    /**@return TrialTreasure */
    public function getTrialOrigin(){
        return $this->origin;
    }

    protected function onDeath(): void {
        if(!$this->origin instanceof TrialTreasure) {parent::onDeath(); return;}
        $this->origin->enemyKill($this->getId());
        parent::onDeath();
    }

    
}