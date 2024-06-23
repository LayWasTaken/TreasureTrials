<?php

namespace Lay\TrialTreasure\tasks;

use Lay\TrialTreasure\TrialTreasure;
use Lay\TrialTreasure\TrialTreasureMain;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;

class TrialReactivationTask extends Task {

    private static array $trials = [];

    public static function addTrialReactivation(Position $position, int $timestamp){
        self::$trials[$timestamp][] = $position;
        ksort(self::$trials);
    }
    
    public static function removeTrialReactivation(int $timestamp):bool{
        if(!array_key_exists($timestamp, self::$trials)) return false;
        unset(self::$trials[$timestamp]);
        ksort(self::$trials);
    }

    public static function trialExists(int $timestamp){
        return array_key_exists($timestamp, self::$trials);
    }
    
    public function onRun(): void{
        $current = time();
        foreach (self::$trials as $k => $trials) {
            if($k > $current) return;
            $this->activateTrials($trials);
            unset(self::$trials[$k]);
        }
    }

    public function onCancel(): void{
        foreach (self::$trials as $k => $trials) {
            try {
                $this->activateTrials($trials);
            } catch (\Throwable $th) {}
            unset(self::$trials[$k]);
        }
    }

    /**@param Position[] $trials */
    private function activateTrials(array $trials){
        $manager = TrialTreasureMain::getInstance()->getManager();
        foreach ($trials as $pos) {
            if(!$pos->world) continue;
            $blockData = $manager->get($pos->world)->getBlockDataAt($pos->x, $pos->y, $pos->z);
            if(!$blockData instanceof TrialTreasure) continue;
            if($blockData->isActive()) continue;
            $blockData->activate();
        }
    }

}