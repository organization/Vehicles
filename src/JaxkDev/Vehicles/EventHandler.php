<?php
/*
 * Vehicles, PocketMine-MP Plugin.
 *
 * Licensed under the Open Software License version 3.0 (OSL-3.0)
 * Copyright (C) 2019 JaxkDev
 *
 * Twitter :: @JaxkDev
 * Discord :: Jackthehaxk21#8860
 * Email   :: JaxkDev@gmail.com
 */

declare(strict_types=1);

namespace JaxkDev\Vehicles;

use JaxkDev\Vehicles\Vehicle\Vehicle;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\network\mcpe\protocol\InteractPacket;
use pocketmine\network\mcpe\protocol\InventoryTransactionPacket;
use pocketmine\network\mcpe\protocol\PlayerInputPacket;
use pocketmine\network\mcpe\protocol\types\inventory\UseItemOnEntityTransactionData;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;

//Only 3 'vehicle's in one namespace *HAHA*

class EventHandler implements Listener {
	/** @var Main */
	public $plugin;


	public function __construct(Main $plugin) {
		$this->plugin = $plugin;
	}

	public function onPlayerLeaveEvent(PlayerQuitEvent $event) {
		$player = $event->getPlayer();
		if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
			Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
			$this->plugin->getLogger()->debug($player->getName() . "님이 교통수단을 타고있다가 게임을 종료하였습니다, 교통수단에서 내려졌습니다.");
		}
	}

	public function onPlayerChangeLevelEvent(EntityTeleportEvent $event) {
		if ($event->getFrom()->getWorld()->getFolderName() === $event->getTo()->getWorld()->getFolderName()) {
			/** @var Player $player */
			$player = $event->getEntity();
			if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
				Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
				$player->sendMessage(C::RED . "교통수단과 함께 이동할 수 없으므로, 교통수단에서 내려졌습니다.");
				$this->plugin->getLogger()->debug($player->getName() . "님이 교통수단을 타고있다가 이동하였습니다, 교통수단에서 내려졌습니다.");
			}
			return;
		}
		if ($event->getEntity() instanceof Player) {
			/** @var Player $player */
			$player = $event->getEntity();
			if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
				Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
				$player->sendMessage(C::RED . "교통수단과 함께 월드를 이동할 수 없으므로, 교통수단에서 내려졌습니다.");
				$this->plugin->getLogger()->debug($player->getName() . "님이 교통수단을 타고있다가 월드를 이동하였습니다, 교통수단에서 내려졌습니다.");
			}
		}
	}

	public function onPlayerDeathEvent(PlayerDeathEvent $event) {
		$player = $event->getPlayer();
		if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
			Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
			$player->sendMessage(C::RED . "교통수단을 타고있다가 죽었기 때문에, 교통수단에서 내려졌습니다.");
			$this->plugin->getLogger()->debug($player->getName() . "님이 교통수단을 타고있다가 죽었습니다, 교통수단에서 내려졌습니다.");
		}
	}

	/**
	 * @param EntityDamageByEntityEvent $event
	 * @priority Lowest
	 * Some interruption by MultiWorld
	 */
	public function onEntityDamageEvent(EntityDamageByEntityEvent $event) {
		if ($event->getEntity() instanceof Vehicle) {
			$event->setCancelled(); //stops the ability to 'kill' a object/vehicle. (In long future, add vehicle condition *shrug*
			if (!($event->getDamager() instanceof Player)) return;
			/** @var Player $attacker */
			$attacker = $event->getDamager();
			/** @var Vehicle $entity */
			$entity = $event->getEntity();
			if (($index = array_search(strtolower($attacker->getName()), array_keys($this->plugin->interactCommands))) !== false) {
				$command = $this->plugin->interactCommands[array_keys($this->plugin->interactCommands)[$index]][0];
				$args = $this->plugin->interactCommands[array_keys($this->plugin->interactCommands)[$index]][1];
				switch ($command) {
					case 'lock':
						if ($entity instanceof Vehicle) {
							if ($entity->getOwner() === null) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "이 교통수단은 탑승자가 없습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								if ($entity->isLocked()) {
									$attacker->sendMessage($this->plugin->prefix . C::RED . "이 교통수단은 이미 잠금되어 있습니다.");
									unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
									break;
								}
								$entity->setLocked(true);
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "교통수단을 잠궜습니다. 탑승 상태를 변경할 수 없습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "현재 교통수단의 탑승자가 아닙니다.");
						}
						break;
					case 'un-lock':
						if ($entity instanceof Vehicle) {
							if ($entity->getOwner() === null) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "이 교통수단은 탑승자가 없습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								if (!$entity->isLocked()) {
									$attacker->sendMessage($this->plugin->prefix . C::RED . "이 교통수단은 이미 잠금이 해제되어 있습니다.");
									unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
									break;
								}
								$entity->setLocked(false);
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "교통수단 잠금을 해제했습니다. 탑승 상태를 변경할 수 있습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "현재 교통수단의 탑승자가 아닙니다.");
						}
						break;
					case 'giveaway':
						if ($entity instanceof Vehicle) {
							if ($entity->getOwner() === null) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "이 교통수단은 탑승자가 없습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								$entity->removeOwner();
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "이 교통수단을 버렸습니다.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "현재 교통수단의 탑승자가 아닙니다.");
						}
						break;
					case 'remove':
						if ($entity instanceof Vehicle) {
							if (!$entity->isEmpty()) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "탑승자가 있는 교통수단은 제거할 수 없습니다.");
							} else {
								$entity->close();
								$attacker->sendMessage($this->plugin->prefix . "'" . $entity->getName() . "' (을)를 제거했습니다.");
							}
						}
						unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
						break;
					default:
						$this->plugin->getLogger()->warning("Unknown interact command '{$command}'");
				}
			} else {
				if ($entity instanceof Vehicle) {
					if (!$attacker->hasPermission("vehicles.drive")) {
						$attacker->sendMessage(C::RED . "교통수단을 탑승할 권한이 없습니다..");
						return;
					}
					if ($entity->getDriver() === null) $entity->setDriver($attacker);
					else $entity->setPassenger($attacker);
				}
			}
		}
	}

	/**
	 * @param DataPacketReceiveEvent $event
	 */
	public function onDataPacketEvent(DataPacketReceiveEvent $event) {
		$packet = $event->getPacket();
		$pid = $packet->pid();
		switch ($pid) {
			case InteractPacket::NETWORK_ID:
				$this->onInteractPacket($event);
				break;
			case InventoryTransactionPacket::NETWORK_ID:
				$this->onInventoryTransactionPacket($event);
				break;
			case PlayerInputPacket::NETWORK_ID:
				$this->onPlayerInputPacket($event);
				break;
		}
	}

	/**
	 * Handle a players interact.
	 * @param DataPacketReceiveEvent $event
	 */
	public function onInteractPacket($event) {
		/** @var InteractPacket $packet */
		$packet = $event->getPacket();

		if ($packet->action === InteractPacket::ACTION_LEAVE_VEHICLE) {
			$player = $event->getOrigin()->getPlayer();
			$vehicle = $player->getWorld()->getEntity($packet->target);
			if ($vehicle instanceof Vehicle) {
				$vehicle->removePlayer($player);
				$event->setCancelled();
			}
		}
	}

	/**
	 * Handle InventoryTransaction.
	 * @param DataPacketReceiveEvent $event
	 */
	public function onInventoryTransactionPacket($event) {
		/** @var InventoryTransactionPacket $packet */
		$packet = $event->getPacket();

		$player = $event->getOrigin()->getPlayer();
		if ($packet->trData instanceof UseItemOnEntityTransactionData) {
			$vehicle = $player->getWorld()->getEntity($packet->trData->getEntityRuntimeId());
			if ($vehicle instanceof Vehicle) {
				if ($vehicle->hasDriver()) $vehicle->setPassenger($player);
				else $vehicle->setDriver($player);
				$event->setCancelled();
			}
		}

	}

	/**
	 * Handle a players motion when driving.
	 * @param DataPacketReceiveEvent $event
	 */
	public function onPlayerInputPacket($event) {
		/** @var PlayerInputPacket $packet */
		$packet = $event->getPacket();
		$player = $event->getOrigin()->getPlayer();

		if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
			$event->setCancelled();
			if ($packet->motionX === 0.0 and $packet->motionY === 0.0) {
				return;
			} //MCPE Likes to send a lot of useless packets, this cuts down the ones we handle.
			/** @var Vehicle $vehicle */
			$vehicle = Main::$inVehicle[$player->getUniqueId()->toString()];
			if ($vehicle->getDriver() === null) return;
			if ($vehicle->getDriver()->getUniqueId()->equals($player->getUniqueId())) $vehicle->updateMotion($packet->motionX, $packet->motionY);
		}
	}
}