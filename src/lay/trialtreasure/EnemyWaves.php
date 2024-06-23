<?php

namespace Lay\TrialTreasure;

use Generator;
use pocketmine\player\Player;
use pocketmine\world\Position;

interface EnemyWaves {

    public static function getWaves(Player $player, Position $origin, int $difficulty): Generator;

}
