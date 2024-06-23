<?php

namespace Lay\TrialTreasure\particles;

use Generator;
use pocketmine\world\particle\FlameParticle;
use pocketmine\world\Position;

class EntitySpawnParticle implements AnimatedParticle {

    public static function getParticles(Position $origin): Generator{
        $world = $origin->getWorld();
        $mid = $origin->add(0, 0.5, 0);
        $flameParticle = new FlameParticle;
        yield 0;
        $world->addParticle($mid->add(0.3, 0, 0.2), $flameParticle);
        yield 0;
        $world->addParticle($mid->add(0.2, 0, 0.3), $flameParticle);
        yield 0;
        $world->addParticle($mid->add(0.2, 0.5, 0.4), $flameParticle);
        yield 0;
        $world->addParticle($mid->add(0.3, 0, 0.2), $flameParticle);
        yield 0;
        $world->addParticle($mid->add(0.4, 0.5, 0.2), $flameParticle);
        yield 0;
        $world->addParticle($mid->add(0.2, 0, 0.1), $flameParticle);
        yield;
    }

    public static function ticksToYield(): int{
        return 1;
    }

}