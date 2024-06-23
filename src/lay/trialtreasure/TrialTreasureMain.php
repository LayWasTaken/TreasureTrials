<?php

declare(strict_types=1);

namespace Lay\TrialTreasure;

use cosmicpe\blockdata\BlockDataFactory;
use cosmicpe\blockdata\world\BlockDataWorldManager;
use Lay\TrialTreasure\entity\enemies\Skeleton;
use Lay\TrialTreasure\entity\enemies\Zombie;
use Lay\TrialTreasure\entity\FloatingTextEntity;
use Lay\TrialTreasure\events\TreasureEvent;
use Lay\TrialTreasure\tasks\TrialReactivationTask;
use Lay\TrialTreasure\trials\AmethystTrials;
use pocketmine\entity\EntityDataHelper;
use pocketmine\entity\EntityFactory;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;

class TrialTreasureMain extends PluginBase{

    private static self $instance;

    public static function getInstance(){
        return self::$instance;
    }

    private BlockDataWorldManager $manager;

    public function getManager(){
        return $this->manager;
    }

    public function onLoad():void {
        $this->registerEntities();
    }

    public function onEnable():void {
        $this->registerTrials();
        self::$instance = $this;
        $this->manager = BlockDataWorldManager::create($this);
        $this->getServer()->getPluginManager()->registerEvents(new TreasureEvent($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new TrialReactivationTask, 20);
    }
    
    private function registerTrials(){
        BlockDataFactory::register("AmethystTrial", AmethystTrials::class);
    }

    private function registerEntities(){
        (EntityFactory::getInstance())->register(Skeleton::class, function (World $world, CompoundTag $nbt):Skeleton {
            return new Skeleton(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['skeleton', 'minecraft:skeleton']);
        (EntityFactory::getInstance())->register(Zombie::class, function (World $world, CompoundTag $nbt):Zombie {
            return new Zombie(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        }, ['zombie', 'minecraft:zombie']);
        (EntityFactory::getInstance())->register(FloatingTextEntity::class, function (World $world, CompoundTag $nbt):FloatingTextEntity {
            return new FloatingTextEntity(EntityDataHelper::parseLocation($nbt, $world));
        }, ['zombie', 'minecraft:zombie']);
    }
}
