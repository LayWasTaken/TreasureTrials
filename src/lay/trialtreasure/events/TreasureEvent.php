<?php

namespace Lay\TrialTreasure\events;

use Lay\TrialTreasure\entity\FloatingTextEntity;
use Lay\TrialTreasure\particles\CoolAnimatedParticle;
use Lay\TrialTreasure\tasks\AnimatedParticleTask;
use Lay\TrialTreasure\tasks\DelayedDataSave;
use Lay\TrialTreasure\tasks\TrialReactivationTask;
use Lay\TrialTreasure\trials\AmethystTrials;
use Lay\TrialTreasure\TrialTreasure;
use Lay\TrialTreasure\TrialTreasureMain;
use pocketmine\block\MonsterSpawner;
use pocketmine\block\tile\MonsterSpawner as TileMonsterSpawner;
use pocketmine\block\VanillaBlocks;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\item\GoldenApple;
use pocketmine\item\Stick;
use pocketmine\math\AxisAlignedBB;
use pocketmine\scheduler\ClosureTask;

final class TreasureEvent implements Listener {

    public function __construct(private TrialTreasureMain $plugin){}

    public function onPlayerInteract(PlayerInteractEvent $event){
        if(!$event->getAction() == PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
        $block = $event->getBlock();
        if(!$block instanceof MonsterSpawner) return;
        $pos = $block->getPosition();
        $blockData = $this->plugin->getManager()->get($pos->getWorld())->getBlockDataAt($pos->x, $pos->y, $pos->z);
        if(!$blockData instanceof TrialTreasure) return;
        if(!$blockData->isValid()){
            $pos->getWorld()->setBlockAt($pos->x, $pos->y, $pos->z, VanillaBlocks::AIR());
            $this->plugin->getLogger()->warning("INVALID TRIAL SPAWNER at [x=" . $pos->x . " y=" . $pos->y . " z=" . $pos->z . "]");
            return;
        }
        $player = $event->getPlayer();
        $inventory = $player->getInventory();
        $itemHand = $inventory->getItemInHand();
        if($itemHand instanceof GoldenApple){
            if(!$blockData->activate()) return $player->sendMessage("Trial is already activated");
            $player->sendMessage("Trial activated");
        }elseif ($itemHand instanceof Stick) {
            if(!$blockData->deactivate()) return $player->sendMessage("Trial is already deactivated");
            $player->sendMessage("Trial deactivated");
        }else {
            if($blockData->start($player)) $player->sendMessage("Trial started");
            else $player->sendMessage("Something went wrong");
        }
    }

    public function onBlockPlace(BlockPlaceEvent $event){
        $block = $event->getTransaction()->getBlocks()->current()[3];
        if(!$block instanceof MonsterSpawner) return;
        $pos = $block->getPosition();
        $trials = new AmethystTrials($pos);
        $this->plugin->getManager()->get($pos->getWorld())->setBlockDataAt($pos->x, $pos->y, $pos->z, $trials);
        $trials->activate();
    }

    public function onBlockBreak(BlockBreakEvent $event){
        $block = $event->getBlock();
        if(!$block instanceof MonsterSpawner) return;
        $blockPos = $block->getPosition();
        $blockData = $this->plugin->getManager()->get($blockPos->getWorld())->getBlockDataAt($blockPos->x, $blockPos->y, $blockPos->z);
        if(!$blockData instanceof TrialTreasure) return;
        if($blockData->isValid()) $blockData->destroy();
    }

    public function onChunkLoad(ChunkLoadEvent $e){
        $chunk = $e->getChunk();
        foreach ($chunk->getTiles() as $tile) {
            if(!$tile instanceof TileMonsterSpawner) continue;
            $pos = $tile->getPosition();
            $dataWorld = $this->plugin->getManager()->get($tile->getPosition()->getWorld());
            $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($pos, $dataWorld, $e){
                $dataWorld->loadChunk($e->getChunkX(), $e->getChunkX());
                $blockData = $dataWorld->getBlockDataAt($pos->x, $pos->y, $pos->z);
                if(!$blockData instanceof TrialTreasure) return;
                if(!$blockData->isValid()){
                    $pos->getWorld()->setBlockAt($pos->x, $pos->y, $pos->z, VanillaBlocks::AIR());
                    TrialTreasureMain::getInstance()->getLogger()->warning("INVALID TRIAL SPAWNER at [x=" . $pos->x . " y=" . $pos->y . " z=" . $pos->z . "]");
                    return;
                }
                $blockData->setFloatingTextInfo();
            }), 20);
        }
    }

}