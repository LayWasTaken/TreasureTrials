<?php

namespace Lay\TrialTreasure\trials;

use Generator;
use Lay\TrialTreasure\EnemyWaves;
use Lay\TrialTreasure\entity\enemies\Skeleton;
use Lay\TrialTreasure\entity\enemies\Zombie;
use Lay\TrialTreasure\TrialTreasure;
use pocketmine\item\VanillaItems;

class AmethystTrials extends TrialTreasure {

    public function getWaves(): Generator{
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Skeleton::create($this->getRandomSafeSpawn(), $this);
        yield Zombie::create($this->getRandomSafeSpawn(), $this);
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