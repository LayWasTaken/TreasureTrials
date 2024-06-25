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
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
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

    private const DEFAULT_AREA = [-1, 1];

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
    private Position $position;
    private bool $valid = true;

    /**@var TreasureEnemy[] */
    private array $currentEnemies = [];
    
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

    public function enemyKill(int $id){
        $this->removeEnemy($id);
        $this->spawnEnemies();
        if(!empty($this->currentEnemies)) return;
        if(!$this->challenger->isOnline()) $this->challenger = Server::getInstance()->getPlayerByUUID($this->challenger->getUniqueId());
        $top = $this->position->add(0.5, 1.5, 0.5);
        $world = $this->position->getWorld();
        foreach ($this->getRewards() as $item) { $world->dropItem($top, $item); }
        if($this->challenger) $this->challenger->sendMessage(TextFormat::GREEN . "Trial Finished");
        $this->deactivate();
        $this->scheduleReactivation();
        $this->setFloatingTextInfo($this->position);
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
        $this->spawnEnemies();
        if(count($this->currentEnemies) <= 0){
            var_dump($this->currentEnemies);
            $this->deactivate();
            $this->setFloatingTextInfo();
            return false;
        }
        $this->startEntityTimer();
        return true;
    }

    private function removeEnemy(int $id){
        if(!array_key_exists($id, $this->currentEnemies)) return false;
        $enemy = $this->currentEnemies[$id];
        if(!$enemy instanceof TreasureEnemy) return false;
        if($enemy->isAlive()) $enemy->flagForDespawn();
        unset($this->currentEnemies[$id]);
        return true;
    }

    private function spawnEnemies(){
        if(!$this->waves) $this->waves = $this->getWaves();
        if(!$this->waves->valid()) return false;
        $this->waves->next();
        $enemy = $this->waves->current();
        if(!$enemy instanceof TreasureEnemy) return false;
        $this->currentEnemies[$enemy->getId()] = $enemy;
        $pos = $enemy->getPosition();
        TrialTreasureMain::getInstance()->getScheduler()->scheduleRepeatingTask(new AnimatedParticleTask(EntitySpawnParticle::class, $pos), 2);
        $enemy->spawnToAll(); 
        if(count($this->currentEnemies) < $this->getMaxActiveEnemies()) 
            if(!$this->spawnEnemies()) return false;
        return true;
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

    protected function getRandomSafeSpawn(): Vector3{
        $x = mt_rand(-1, 1);
        $z = $x == 0 ? self::DEFAULT_AREA[array_rand(self::DEFAULT_AREA)] : mt_rand(-1, 1);
        return Location::fromObject($this->position->add($x + 0.5, 0, $z + 0.5), $this->position->getWorld(), mt_rand(0, 35) * 100);
    }

    public function getPosition(){ return $this->position->asPosition(); }
    
    public function getChallenger(){ return $this->challenger; }

    protected function onEasy(){ }

    protected function onNormal(){ }

    protected function onHard(){ }

    /**Total max active enemies before spawning new ones*/
    protected abstract function getMaxActiveEnemies(): int;

    /**
     * @return Item[]
     */
    protected abstract function getRewards(): array;

    public abstract function getWaves(): Generator;
    
}