<?php

namespace Lay\TrialTreasure;

use cosmicpe\blockdata\BlockData;
use Generator;
use Lay\TrialTreasure\entity\FloatingTextEntity;
use Lay\TrialTreasure\entity\TreasureEnemy;
use Lay\TrialTreasure\particles\AnimatedParticle;
use Lay\TrialTreasure\particles\EntitySpawnParticle;
use Lay\TrialTreasure\tasks\AnimatedParticleTask;
use Lay\TrialTreasure\tasks\TrialReactivationTask;
use pocketmine\block\VanillaBlocks;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\item\Item;
use pocketmine\nbt\tag\IntTag;
use pocketmine\player\Player;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

abstract class TrialTreasure implements BlockData {

    protected const AREA_RADIUS = 8;

    protected const EASY = 0;
    protected const NORMAL = 1;
    protected const HARD = 2;
    
    public const REACTIVATION_TIME = 20;
    protected const DEFAULT_TIME_LIMIT = 120;

    // Tag Names
    protected const DIFFICULTY = "difficulty";
    protected const ACTIVE = "active";
    protected const LAST_DEACTIVATION = "last_deactivation";
    protected const CHALLENGER = "challenger";
    protected const VECTOR = "vector";
    protected const WORLD = "world";

    // Saved Data
    private int $difficulty = 0;
    private bool $active = false;
    private ?Player $challenger = null;
    private ?Generator $waves = null;
    private ?FloatingTextEntity $textEntity = null;
    private int $lastDeactivation = 0;
    private array $currentEnemies = [];
    private Position $position;
    private bool $valid = true;
    
    // Temporary Data
    private int $enemyCount = 0;

    public function __construct(Position $position){
        $this->position = $position;
    }

    public static function nbtDeserialize(CompoundTag $nbt): BlockData {
        TrialTreasureMain::getInstance()->getLogger()->info("NBT Deserialized");
        $posTag = $nbt->getListTag(self::VECTOR);
        $worldName = $nbt->getString(self::WORLD, "");
        if(!($posTag && $worldName)){
            $treasure = new static(new Position(0, 0, 0, null));
            $treasure->valid = false;
            return $treasure;
        }
        $positionValues = $posTag->getAllValues();
        $worldManager = Server::getInstance()->getWorldManager();
        if(!$worldManager->isWorldLoaded($worldName)) $worldManager->loadWorld($worldName);
        $world = $worldManager->getWorldByName($worldName);
        $treasure = new static(new Position($positionValues[0], $positionValues[1], $positionValues[2], $world));
        $treasure->lastDeactivation = $nbt->getInt(self::LAST_DEACTIVATION, 0);
        $treasure->active = (bool) $nbt->getByte(self::ACTIVE, 0);
        $treasure->difficulty = $nbt->getByte(self::DIFFICULTY, 0);
        if(!$treasure->active) {
            $treasure->activate();
            $treasure->setFloatingTextInfo();
            return $treasure;
        }
        $treasure->getFloatingTextEntity();
        $treasure->setFloatingTextInfo();
        return $treasure;
    }

    public function nbtSerialize(): CompoundTag {
        TrialTreasureMain::getInstance()->getLogger()->info("NBT Serialized");
        return CompoundTag::create()
            ->setByte(self::ACTIVE, $this->active)
            ->setByte(self::DIFFICULTY, $this->difficulty)
            ->setInt(self::LAST_DEACTIVATION, $this->lastDeactivation)
            ->setTag(self::VECTOR, new ListTag([
                new IntTag($this->position->x), 
                new IntTag($this->position->y), 
                new IntTag($this->position->z)
            ]))
            ->setString(self::WORLD, $this->position->getWorld()->getFolderName());
    }

    public function getDifficulty(){
        return $this->difficulty;
    }

    public function isActive(){
        return $this->active;
    }

    public function isValid(){
        return $this->valid;
    }

    public function getLastDeactivationTimestamp(){
        return $this->lastDeactivation;
    }

    public function enemyKill(){
        if($this->enemyCount-- <= 0 || !$this->enemyCount == 0) return;
        $entities = $this->nextWave();
        if($entities) return TrialTreasureMain::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($entities){
            foreach ($entities as $entity) {
                $pos = $entity->getPosition();
                TrialTreasureMain::getInstance()->getScheduler()->scheduleRepeatingTask(new AnimatedParticleTask(EntitySpawnParticle::class, $pos), 2);
                $entity->spawnToAll(); 
            }
        }), 20);
        if(!$this->challenger->isOnline()) $this->challenger = Server::getInstance()->getPlayerByUUID($this->challenger->getUniqueId());
        $top = $this->position->add(0.5, 1.5, 0.5);
        $world = $this->position->getWorld();
        foreach ($this->getRewards() as $item) { $world->dropItem($top, $item); }
        if($this->challenger) $this->challenger->sendMessage(TextFormat::GREEN . "Trial Finished");
        $this->deactivate();
        $this->scheduleReactivation();
        $this->setFloatingTextInfo($this->position);
    }

    public function getEnemyCount(){
        return $this->enemyCount;
    }

    public function activate():bool {
        if($this->active) return false;
        $roll = mt_rand(0, 50);
        if($roll <= 10){
            $this->difficulty = self::HARD;
        }elseif($roll <= 25){
            $this->difficulty = self::NORMAL;
        }else{
            $this->difficulty = self::EASY;
        }
        $this->active = true;
        $this->setFloatingTextInfo();
        return true;
    }

    public function deactivate():bool {
        if(!$this->active) return false;
        $this->active = false;
        $this->challenger = null;
        $this->waves = null;
        $this->enemyCount = 0;
        $this->lastDeactivation = time();
        $this->textEntity?->finishTimer();
        return true;
    }

    public function scheduleReactivation(){
        if($this->active) return false;
        TrialReactivationTask::addTrialReactivation($this->position, $this->lastDeactivation + self::REACTIVATION_TIME);
    }

    public function start(Player $challenger):bool {
        if($this->challenger && $this->waves) return false;
        if(!$this->active) return false;
        $this->challenger = $challenger;
        switch ($this->difficulty) {
            case self::EASY:
                $this->onEasy();
                break;

            case self::NORMAL:
                $this->onNormal();
                break;

            case self::HARD:
                $this->onHard();
                break;

            default:
                $this->challenger = null;
                return false;
        }
        /**@var EnemyWaves */
        $class = $this->getWaveClass();
        $this->waves = $class::getWaves($this->challenger, $this->position, $this->difficulty);
        $entities = $this->waves->current();
        if(!$entities){
            $this->deactivate();
            $this->setFloatingTextInfo();
            return false;
        }
        $this->enemyCount = count($entities);
        $this->currentEnemies = $entities;
        $this->startEntityTimer();
        TrialTreasureMain::getInstance()->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($entities){
            foreach ($entities as $entity) {
                $pos = $entity->getPosition();
                TrialTreasureMain::getInstance()->getScheduler()->scheduleRepeatingTask(new AnimatedParticleTask(EntitySpawnParticle::class, $pos), 2);
                $entity->spawnToAll(); 
            }
        }), 20);
        return true;
    }

    protected function nextWave(){
        $this->waves->next();
        $current = $this->waves->current();
        if(!is_array($current)) {
            return [];
        }
        $this->enemyCount = count($current);
        $this->currentEnemies = $current;
        return $current;
    }

    public function getCurrentWave(){
        return $this->waves ? $this->waves->key() : 0;
    }

    public function setFloatingTextInfo(){
        $entity = $this->getFloatingTextEntity();
        if((!$entity->isAlive()) || $entity->isClosed()) return;
        $info = $this->challenger ? TextFormat::BLUE . "Challenged" : $this->createInfo();
        $entity->setText($info)->updateNameTag();
    }

    /**@return FloatingTextEntity makes a new one if it doesnt exists */
    private function getFloatingTextEntity(): FloatingTextEntity{
        if($this->textEntity) return $this->textEntity;
        $world = $this->position->getWorld();
        $top = $this->position->add(0.5, 1, 0.5);
        $entity = $world->getNearestEntity($top, 1, FloatingTextEntity::class);
        if(!$entity || !$entity instanceof FloatingTextEntity){
            $entity = new FloatingTextEntity(Position::fromObject($top, $world));
            $entity->setCanSaveWithChunk(true);
            $entity->spawnToAll();
        }
        $this->textEntity = $entity;
        return $entity;
    }

    private function startEntityTimer(int $seconds = self::DEFAULT_TIME_LIMIT){
        $this->setFloatingTextInfo();
        $blockdata = $this;
        $this->textEntity->startTimer($seconds, function() use ($blockdata){
            if(!$blockdata instanceof TrialTreasure) return;
            if(($challenger = $blockdata->getChallenger()) && $challenger->isOnline()) $challenger->sendMessage(TextFormat::RESET . TextFormat::RED . " Times up! you failed the trial");
            $blockdata->stop();
            $blockdata->setFloatingTextInfo();
        });
    }

    private function createInfo(){
        return $this->active ? 
        (TextFormat::GREEN . "Active\n" . TextFormat::WHITE . "Difficulty: " . 
        ($this->difficulty == self::HARD ? TextFormat::RED . "HARD" : 
        ($this->difficulty == self::NORMAL ? TextFormat::YELLOW . "NORMAL" : 
        TextFormat::GREEN . "EASY") )) 
        : TextFormat::DARK_RED . "Dormant";
    }

    public function stop(){
        foreach ($this->currentEnemies as $entity) {
            if(!$entity instanceof TreasureEnemy) continue;
            if(!$entity->isAlive()) continue;
            if($entity->isClosed() || $entity->isFlaggedForDespawn()) continue;
            $entity->flagForDespawn();
        }
        $this->deactivate();
        $this->scheduleReactivation();
    }

    public function destroy(){
        $this->stop();
        $world = $this->position->getWorld();
        if($this->textEntity){
            $this->textEntity->flagForDespawn();
            $this->textEntity->finishTimer();
        }
        $pos = $this->position;
        $world->setBlockAt($pos->x, $pos->y, $pos->z, VanillaBlocks::AIR());
    }

    /**@return TreasureEnemy[] */
    public function getCurrentEnemies(){
        return $this->currentEnemies;
    }

    public function getChallenger(){ return $this->challenger; }

    protected function onEasy(){ }

    protected function onNormal(){ }

    protected function onHard(){ }

    protected abstract static function getWaveClass(): string;

    /**
     * @return Item[]
     */
    protected abstract function getRewards(): array;

}