<?php

namespace Lay\TrialTreasure\entity;

use pocketmine\block\VanillaBlocks;
use pocketmine\entity\Entity;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;
use pocketmine\entity\Zombie;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataCollection;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

class FloatingTextEntity extends Zombie {

    public static function getNetworkTypeId(): string{
        return EntityIds::FALLING_BLOCK;
    }

    private string $text;

	private int $timerSeconds = 0;
	private bool $showTimer = false;
	private bool $showTimerOnFinish = false;
	private ?\Closure $onFinish = null;

	// Only be used for timer and if the timer is set to 0 then it will bound to be set to 0
	private int $currentTicks = 0;

    protected function getInitialSizeInfo(): EntitySizeInfo{
        return new EntitySizeInfo(0.01, 0.01);
    }

    public function __construct(Position $pos, string $text = ""){
		$this->setCanSaveWithChunk(false);
		$this->text = $text;
		$this->keepMovement = true;
		$this->gravity = 0.0;
		$this->gravityEnabled = false;
		$this->drag = 0.0;
		$this->noClientPredictions = true;
		$this->nameTagVisible = true;
		parent::__construct(Location::fromObject($pos, $pos->world));
	}

	protected function initEntity(CompoundTag $nbt) : void{
		parent::initEntity($nbt);
		$this->setNameTag($this->text);
		$this->setNameTagAlwaysVisible();
	}

    public function isFireProof() : bool{
		return true;
	}

	public function canBeCollidedWith() : bool{
		return false;
	}

	protected function checkBlockIntersections() : void {}

	public function canCollideWith(Entity $entity) : bool{
		return false;
	}

	public function canBeMovedByCurrents() : bool{
		return false;
	}

	protected function getInitialDragMultiplier() : float{
		return 0.0;
	}

	protected function getInitialGravity() : float{
		return 0.0;
	}

	public function attack(EntityDamageEvent $source) : void{
		$source->cancel();
	}

	public function setText(string $text){
		$this->text = $text;
		return $this;
	}

	public function getText(){
		return $this->text;
	}

	public function updateNameTag(){
		$text = $this->showTimer ? $this->text . "\n" . TextFormat::GOLD . "Time Left: " . TextFormat::GREEN . gmdate("i:s", $this->timerSeconds) : $this->text;
		$this->setNameTag($text);
	}

	public function showTimer(bool $showTimer = true){  
		$this->showTimer = $showTimer;
		return $this;
	}

	public function setTimerSeconds(int $time){
		$this->timerSeconds = $time;
		return $this;
	}

	public function startTimer(int $time, ?\Closure $onFinish = null, bool $showTimer = true, bool $showTimerOnExactFinish = false){
		$this->timerSeconds = $time;
		$this->onFinish = $onFinish;
		$this->showTimer = $showTimer;
		$this->showTimerOnFinish = $showTimerOnExactFinish;
		return $this;
	}

	public function getCurrentTimerSeconds(){ return $this->timerSeconds; }

	public function finishTimer(){
		$this->showTimer = $this->showTimerOnFinish;
		$this->onFinish?->call($this);
		$this->onFinish = null;
	}

	protected function entityBaseTick(int $tickDiff = 1): bool{
		parent::entityBaseTick($tickDiff);
		if($this->currentTicks % 10) $this->updateNameTag();
		if($this->currentTicks >= 20){
			$this->currentTicks = 0;
			$this->timerSeconds--;
			$this->updateNameTag();
			if($this->timerSeconds < 0) $this->finishTimer();
			return true;
		}
		$this->currentTicks++;
		return true;
	}
	
	public function setNameTag(string $name) : void{
		parent::setNameTag($name);
		$this->sendData($this->hasSpawned, $this->getDirtyNetworkData());
		$this->getNetworkProperties()->clearDirtyProperties();
	}

	protected function syncNetworkData(EntityMetadataCollection $properties): void{
		parent::syncNetworkData($properties);
        $properties->setInt(EntityMetadataProperties::VARIANT, TypeConverter::getInstance()->getBlockTranslator()->internalIdToNetworkId(VanillaBlocks::AIR()->getStateId()));
		$properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_HEIGHT, 0.0);	
		$properties->setFloat(EntityMetadataProperties::BOUNDING_BOX_WIDTH, 0.0);	
	}
	
}