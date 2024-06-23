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

    protected ?TrialTreasure $trialOrigin = null;
    protected ?Position $trialOriginPosition = null;
    protected int $originCheck = 0;

    public static function create(Location $location, ?Position $origin = null, ?CompoundTag $nbt = null){
        return new static($location, $nbt, $origin);
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null, ?Position $origin = null){
        $manager = TrialTreasureMain::getInstance()->getManager();
        if(!$origin) {
            parent::__construct($location, $nbt);
            $this->flagForDespawn();
        }
        else{
            $blockData = $manager->get($origin->getWorld())->getBlockDataAt($origin->x, $origin->y, $origin->z);
            $this->trialOrigin = $blockData;
            $this->trialOriginPosition = $origin;
            parent::__construct($location, $nbt);
        }
    }

    /**@return TrialTreasure */
    public function getTrialOrigin(){
        return $this->trialOrigin;
    }

    protected function onDeath(): void {
        if(!$this->trialOrigin instanceof TrialTreasure) {parent::onDeath(); return;}
        $this->trialOrigin->enemyKill();
        parent::onDeath();
    }

    
}