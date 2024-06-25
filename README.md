# Treasure Trials
- My version of a Trial Spawner with multiple spawners support


### Treasure Trial Class Example
```php
<?php

namespace Lay\TrialTreasure\trials;

use Lay\TrialTreasure\entity\enemies\Skeleton;
use Lay\TrialTreasure\entity\enemies\Zombie;
use Lay\TrialTreasure\TrialTreasure;
use pocketmine\item\VanillaItems;

class AmethystTrials extends TrialTreasure {

    public function getWaves(): \Generator{
        for ($i=0; $i < 20; $i++) { 
            if(mt_rand(0, 1)) yield Skeleton::create($this->getRandomSafeSpawn(), $this);
            else yield Zombie::create($this->getRandomSafeSpawn(), $this);
        }
    }

    protected function getRewards(): array{
        return [
            VanillaItems::GOLD_INGOT()->setCount(9),
            VanillaItems::DIAMOND()->setCount(6)
        ];
    }

    protected function getMaxActiveEnemies(): int{
        return 3;
    }

}
```
Each yield will require a Treasure Enemy Entity object

### Treasure Enemy Class Example
```php 
<?php
namespace Lay\TrialTreasure\entity\enemies;

use Lay\TrialTreasure\entity\TreasureEnemy;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

final class Skeleton extends TreasureEnemy {

    public static function getNetworkTypeId() : string{ return EntityIds::SKELETON; }
 
    protected function getInitialSizeInfo() : EntitySizeInfo{
        return new EntitySizeInfo(1.8, 0.6);
    }
 
    public function getName() : string{
        return "Skeleton";
    }

}
```