<?php

namespace Lay\TrialTreasure\entity\enemies;

use Lay\TrialTreasure\entity\TreasureEnemy;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Zombie extends TreasureEnemy {

    public static function getNetworkTypeId() : string{ return EntityIds::ZOMBIE; }
 
    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6); //TODO: eye height ??
    }
 
    public function getName() : string{
        return "Zombie";
    }

}