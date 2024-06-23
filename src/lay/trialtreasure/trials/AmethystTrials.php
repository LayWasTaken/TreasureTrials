<?php

namespace Lay\TrialTreasure\trials;

use Generator;
use Lay\TrialTreasure\EnemyWaves;
use Lay\TrialTreasure\entity\enemies\Skeleton;
use Lay\TrialTreasure\entity\enemies\Zombie;
use Lay\TrialTreasure\TrialTreasure;
use pocketmine\entity\Location;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\world\Position;

class AmethystTrials extends TrialTreasure implements EnemyWaves {

    public static function getWaves(Player $player, Position $origin, int $difficulty): Generator{
        $location1 = Location::fromObject($origin->add(0, 1, 3), $origin->world, mt_rand(0, 35) * 100, 0);
        $location2 = Location::fromObject($origin->add(0, 1, -3), $origin->world, mt_rand(0, 35) * 100, 0);
        $location3 = Location::fromObject($origin->add(3, 1, 0), $origin->world, mt_rand(0, 35) * 100, 0);
        $location4 = Location::fromObject($origin->add(-3, 1, 0), $origin->world, mt_rand(0, 35) * 100, 0);
        $location5 = Location::fromObject($origin->add(3, 1, 1), $origin->world, mt_rand(0, 35) * 100, 0);
        yield [
            Skeleton::create($location1, $origin),
            Skeleton::create($location2, $origin),
            Zombie::create($location3, $origin),
            Zombie::create($location4, $origin),
            Zombie::create($location5, $origin)
        ];
        yield [
            Zombie::create($location1, $origin),
            Zombie::create($location2, $origin),
            Skeleton::create($location3, $origin),
            Skeleton::create($location4, $origin),
            Skeleton::create($location5, $origin)
        ];
        yield [
            Skeleton::create($location1, $origin),
            Skeleton::create($location2, $origin),
            Skeleton::create($location3, $origin),
            Skeleton::create($location4, $origin),
            Skeleton::create($location5, $origin)
        ];
        yield [
            Zombie::create($location1, $origin),
            Zombie::create($location2, $origin),
            Zombie::create($location3, $origin),
            Zombie::create($location4, $origin),
            Zombie::create($location5, $origin)
        ];
    }

    protected function getRewards(): array{
        return [
            VanillaItems::GOLD_INGOT()->setCount(9),
            VanillaItems::DIAMOND()->setCount(6)
        ];
    }

    protected static function getWaveClass(): string{
        return self::class;
    }

}