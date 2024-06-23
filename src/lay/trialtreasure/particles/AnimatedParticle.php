<?php

namespace Lay\TrialTreasure\particles;

use Generator;
use pocketmine\world\Position;

interface AnimatedParticle {

    /**
     * Each yield will be called every x ticks(given by AnimatedParticle::ticksToYield())
     * Yield int 0|null to skip that much ticks
     * Yield true to finish the animation or the generator is not valid anymore
     */
    public static function getParticles(Position $origin): Generator;

    /**
     * @return int The amount of ticks required to call it
     */
    public static function ticksToYield(): int;

}