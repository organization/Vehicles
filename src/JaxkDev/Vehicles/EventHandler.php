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
			$this->plugin->getLogger()->debug($player->getName() . " Has left the server while in a vehicle, they have been kicked from the vehicle.");
		}
	}

	public function onPlayerChangeLevelEvent(EntityTeleportEvent $event) {
		if ($event->getFrom()->getWorld()->getFolderName() === $event->getTo()->getWorld()->getFolderName()) {
			/** @var Player $player */
			$player = $event->getEntity();
			if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
				Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
				$player->sendMessage(C::RED . "You cannot teleport with a vehicle, you have been kicked from your vehicle.");
				$this->plugin->getLogger()->debug($player->getName() . " Has teleported while in a vehicle, they have been kicked from their vehicle.");
			}
			return;
		}
		if ($event->getEntity() instanceof Player) {
			/** @var Player $player */
			$player = $event->getEntity();
			if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
				Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
				$player->sendMessage(C::RED . "You cannot change level with a vehicle, you have been kicked from your vehicle.");
				$this->plugin->getLogger()->debug($player->getName() . " Has changed level while in a vehicle, they have been kicked from the vehicle.");
			}
		}
	}

	public function onPlayerDeathEvent(PlayerDeathEvent $event) {
		$player = $event->getPlayer();
		if (isset(Main::$inVehicle[$player->getUniqueId()->toString()])) {
			Main::$inVehicle[$player->getUniqueId()->toString()]->removePlayer($player);
			$player->sendMessage(C::RED . "You were killed so you have been kicked from your vehicle.");
			$this->plugin->getLogger()->debug($player->getName() . " Has died while in a vehicle, they have been kicked from the vehicle.");
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
								$attacker->sendMessage($this->plugin->prefix . C::RED . "This vehicle has no owner.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								if ($entity->isLocked()) {
									$attacker->sendMessage($this->plugin->prefix . C::RED . "This vehicle is already locked.");
									unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
									break;
								}
								$entity->setLocked(true);
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "This vehicle has been locked, no one may drive/get in the vehicle.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "You are not the owner of this vehicle.");
						}
						break;
					case 'un-lock':
						if ($entity instanceof Vehicle) {
							if ($entity->getOwner() === null) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "This vehicle has no owner.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								if (!$entity->isLocked()) {
									$attacker->sendMessage($this->plugin->prefix . C::RED . "This vehicle is already un-locked.");
									unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
									break;
								}
								$entity->setLocked(false);
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "This vehicle has been un-locked, anyone may now drive/get in the vehicle.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "You are not the owner of this vehicle.");
						}
						break;
					case 'giveaway':
						if ($entity instanceof Vehicle) {
							if ($entity->getOwner() === null) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "This vehicle has no owner.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							if ($attacker->getUniqueId()->equals($entity->getOwner())) {
								$entity->removeOwner();
								$attacker->sendMessage($this->plugin->prefix . C::GOLD . "This vehicle has been given away, next person to drive it will become owner.");
								unset($this->plugin->interactCommands[strtolower($attacker->getName())]);
								break;
							}
							$attacker->sendMessage($this->plugin->prefix . C::RED . "You are not the owner of this vehicle.");
						}
						break;
					case 'remove':
						if ($entity instanceof Vehicle) {
							if (!$entity->isEmpty()) {
								$attacker->sendMessage($this->plugin->prefix . C::RED . "You cannot remove a vehicle with players in it.");
							} else {
								$entity->close();
								$attacker->sendMessage($this->plugin->prefix . "'" . $entity->getName() . "' has been removed.");
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
						$attacker->sendMessage(C::RED . "You do not have permission to drive vehicles.");
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

		if ($packet->transactionType === InventoryTransactionPacket::TYPE_USE_ITEM_ON_ENTITY) {
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