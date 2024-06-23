<?php

namespace Lay\TrialTreasure\entity\enemies;

use Lay\TrialTreasure\entity\TreasureEnemy;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Skeleton extends TreasureEnemy {

    public static function getNetworkTypeId() : string{ return EntityIds::SKELETON; }
 
    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6); //TODO: eye height ??
    }
 
    public function getName() : string{
        return "Skeleton";
    }

}