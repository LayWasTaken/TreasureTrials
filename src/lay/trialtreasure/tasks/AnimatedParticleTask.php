<?php

namespace Lay\TrialTreasure\tasks;

use Lay\TrialTreasure\particles\AnimatedParticle;
use pocketmine\scheduler\Task;
use pocketmine\world\Position;

class AnimatedParticleTask extends Task {

    private int $ticks = 0;
    private bool $finish = false;
    private ?\Generator $animation = null;
    private int $ticksToYield = 1;

    /**
     * @param class-string<\Lay\TrialTreasure\particles\AnimatedParticle> $class 
     */
    public function __construct(string $class, Position $origin){
        $this->finish = !(class_exists($class) && in_array(AnimatedParticle::class, class_implements($class)));
        $this->animation = $this->finish ? null : $class::getParticles($origin);
        $this->ticksToYield = $this->finish ? 0 : $class::ticksToYield();
    }

    public function onRun(): void{
        if($this->finish) {$this->getHandler()->cancel(); return ;}
        $this->ticks++;
        /**@var int $current */
        $current = $this->animation->current();
        if(!is_int($current)){
            if(is_bool($current) || !$this->animation->valid()) {$this->getHandler()->cancel(); return ;}
            if($this->ticksToYield >= $this->ticks){
                $this->ticks = 0;
                $this->animation->next();
                return;
            }
        }
        if(($current + $this->ticksToYield) > $this->ticks) return;
        $this->animation->next();
        $this->ticks = 0;
    }
    
}