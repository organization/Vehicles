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

namespace JaxkDev\Vehicles\Vehicle;

use JaxkDev\Vehicles\Main;
use LogicException;
use pocketmine\entity\Entity;
use pocketmine\entity\Skin;
use pocketmine\item\ItemFactory;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\protocol\AddPlayerPacket;
use pocketmine\network\mcpe\protocol\PlayerListPacket;
use pocketmine\network\mcpe\protocol\SetActorLinkPacket;
use pocketmine\network\mcpe\protocol\types\entity\EntityLegacyIds;
use pocketmine\network\mcpe\protocol\types\entity\EntityLink;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataFlags;
use pocketmine\network\mcpe\protocol\types\entity\EntityMetadataProperties;
use pocketmine\network\mcpe\protocol\types\PlayerListEntry;
use pocketmine\player\Player;
use pocketmine\utils\TextFormat as C;
use pocketmine\utils\UUID;
use pocketmine\world\World;

abstract class Vehicle extends Entity {
	public const NETWORK_ID = EntityLegacyIds::HORSE;

	protected $gravity = 1; //todo find best value. (remember not to make negative...)
	protected $drag = 0.5;

	/** @var null|UUID */
	protected $owner = null;

	/** @var null|Player */
	protected $driver = null;

	/** @var Player[] */
	protected $passengers = [];

	/** @var null|Vector3 */
	protected $driverPosition = null;

	/** @var Vector3[] */
	protected $passengerPositions = [];

	/** @var bool */
	protected $locked = false;   //Todo think about moving to 'status'

	/** @var UUID Used for spawning and handling in terms of reference to the entity */
	protected $uuid;

	/** @var Main */
	private $plugin;

	/**
	 * Vehicle constructor.
	 * @param World $world
	 * @param CompoundTag $nbt
	 */
	public function __construct(World $world, CompoundTag $nbt) {
		$this->uuid = UUID::fromRandom();
		$this->plugin = Main::getInstance();
		$owner = $nbt->getString("ownerUUID", "NA");
		//$this->plugin->getLogger()->debug("ownerUUID: ".$owner);
		if ($owner !== "NA") {
			$this->owner = UUID::fromString($owner);
		}
		$locked = $nbt->getByte("locked", 0);
		//$this->plugin->getLogger()->debug("locked: ".($locked === 0 ? "un-locked" : "locked"));
		if ($locked === 1) $this->locked = true;
		if ($this->owner === null) {
			$this->locked = false;
		}

		parent::__construct($world, $nbt);

		$this->setNameTag(C::RED . "[Vehicle] " . C::GOLD . $this->getName());
		$this->setNameTagAlwaysVisible($this->plugin->cfg["vehicles"]["show-nametags"]);
		$this->setCanSaveWithChunk(true);
		$this->updateNBT();
	}

	/**
	 * Should return the vehicle name shown in-game.
	 * @return string
	 */
	abstract static function getName(): string;

	public function updateNBT(): void {
		$this->owner = $this->owner !== null ? $this->owner->toString() : "NA";
		$this->locked = $this->locked ? 1 : 0;
		$this->saveNBT();
	}

	public function saveNBT(): CompoundTag {
		$nbt = parent::saveNBT();
		$nbt->setString("ownerUUID", $this->owner->toString());
		$nbt->setByte("locked", (int) $this->locked);

		return $nbt;
	}

	public function isEmpty(): bool {
		if (count($this->passengers) === 0 and $this->driver === null) return true;
		return false;
	}

	/**
	 * Handle player input.
	 * @param float $x
	 * @param float $y
	 */
	abstract function updateMotion(float $x, float $y): void;

	/**
	 * Remove the given player from the vehicle
	 * @param Player $player
	 * @return bool
	 */
	public function removePlayer(Player $player): bool {
		if ($this->driver !== null) {
			if ($this->driver->getUniqueId()->equals($player->getUniqueId())) return $this->removeDriver();
		}
		return $this->removePassengerByUUID($player->getUniqueId());
	}

	/**
	 * Removes the driver if possible.
	 * @return bool
	 */
	public function removeDriver(): bool {
		if ($this->driver === null) return false;
		$this->driver->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
		$this->driver->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, false);
		$this->driver->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::WASD_CONTROLLED, false);

		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, false);

		$this->driver->sendMessage(C::GREEN . "You are no longer driving this vehicle.");
		$this->broadcastLink($this->driver, EntityLink::TYPE_REMOVE);
		unset(Main::$inVehicle[$this->driver->getUniqueId()->toString()]);
		$this->driver = null;
		return true;
	}

	protected function broadcastLink(Player $player, int $type = EntityLink::TYPE_RIDER): void {
		foreach ($this->getViewers() as $viewer) {
			if (!isset($viewer->getViewers()[spl_object_id($player)])) {
				$player->spawnTo($viewer);
			}
			$pk = new SetActorLinkPacket();
			$pk->link = new EntityLink($this->getId(), $player->getId(), $type);
			$viewer->sendDataPacket($pk);
		}
	}

	public function removePassengerByUUID(UUID $id): bool {
		foreach (array_keys($this->passengers) as $i) {
			if ($this->passengers[$i]->getUniqueId() === $id) {
				return $this->removePassenger($i);
			}
		}
		return false;
	}

	/**
	 * Remove passenger by seat number.
	 * @param int $seat
	 * @return bool
	 */
	public function removePassenger($seat): bool {
		if (isset($this->passengers[$seat])) {
			$player = $this->passengers[$seat];
			unset($this->passengers[$seat]);
			unset(Main::$inVehicle[$player->getUniqueId()->toString()]);
			$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, false);
			$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, false);
			$this->broadcastLink($player, EntityLink::TYPE_REMOVE);
			$player->sendMessage(C::GREEN . "You are no longer in this vehicle.");
			return true;
		}
		return false;
	}

	public function setPassenger(Player $player, ?int $seat = null): bool {
		if ($this->isLocked() && !$player->getUniqueId()->equals($this->getOwner())) {
			$player->sendMessage(C::RED . "This vehicle is locked.");
			return false;
		}
		if ($seat !== null) {
			if (isset($this->passengers[$seat])) return false;
		} else {
			$seat = $this->getNextAvailableSeat();
			if ($seat === null) return false;
		}
		$this->passengers[$seat] = $player;
		Main::$inVehicle[$player->getUniqueId()->toString()] = $this;
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, true);
		$player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getPassengerSeatPosition($seat));
		$this->broadcastLink($player, EntityLink::TYPE_PASSENGER);
		$player->sendMessage(C::GREEN . "You are now a passenger in this vehicle.");
		return true;
	}

	/**
	 * Check if vehicle is locked.
	 * @return bool
	 */
	public function isLocked(): bool {
		return $this->locked;
	}

	/**
	 * Set the vehicle as locked/unlocked.
	 * @param bool $var
	 */
	public function setLocked(bool $var): void {
		$this->locked = $var;
		$this->saveNBT();
	}

	/**
	 * Get the vehicles owner.
	 * @return UUID|null
	 */
	public function getOwner(): ?UUID {
		return $this->owner;
	}

	public function setOwner(Player $player): void {
		$this->owner = $player->getUniqueId();
		$this->updateNBT();
	}

	public function getNextAvailableSeat(): ?int {
		$max = count($this->passengerPositions);
		$current = count($this->passengers);
		if ($max === $current) return null;
		for ($i = 0; $i < $max; $i++) {
			if (!isset($this->passengers[$i])) return $i;
		}
		throw new LogicException("No seat found when max seats doesnt match currently used seats.");
	}

	public function getPassengerSeatPosition(int $seatNumber): ?Vector3 {
		if (isset($this->passengerPositions[$seatNumber])) return $this->passengerPositions[$seatNumber];
		return null;
	}

	/**
	 * Returns the driver if there is one.
	 * @return Player|null
	 */
	public function getDriver(): ?Player {
		return $this->driver;
	}

	/**
	 * Sets the driver to the given player if possible.
	 * @param Player $player
	 * @return bool
	 */
	public function setDriver(Player $player): bool {
		if ($this->isLocked() && !$player->getUniqueId()->equals($this->getOwner())) {
			$player->sendMessage(C::RED . "This vehicle is locked, you must be the owner to enter.");
			return false;
		}
		if ($this->driver !== null) {
			if ($this->driver->getUniqueId()->equals($player->getUniqueId())) {
				$player->sendMessage(C::RED . "You are already driving this vehicle.");
				return false;
			}
			$player->sendMessage(C::RED . $this->driver->getName() . " is driving this vehicle.");
			return false;
		}
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::RIDING, true);
		$player->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SITTING, true);
		$player->getNetworkProperties()->setVector3(EntityMetadataProperties::RIDER_SEAT_POSITION, $this->getDriverSeatPosition());

		$this->getNetworkProperties()->setGenericFlag(EntityMetadataFlags::SADDLED, true);
		$this->driver = $player;
		Main::$inVehicle[$this->driver->getUniqueId()->toString()] = $this;
		$player->sendMessage(C::GREEN . "You are now driving this vehicle.");
		$this->broadcastLink($this->driver);
		$player->sendTip(C::GREEN . "Sneak/Jump to leave the vehicle.");

		if ($this->owner === null) {
			$this->setOwner($player);
			$player->sendMessage(C::GREEN . "You have claimed this vehicle, you are now its owner.");
		}
		return true;
	}

	public function getDriverSeatPosition(): ?Vector3 {
		if ($this->driverPosition === null) return new Vector3(0, $this->height, 0);
		else return $this->driverPosition;
	}

	/**
	 * Checks if the vehicle as a driver.
	 * @return bool
	 */
	public function hasDriver(): bool {
		return $this->driver !== null;
	}

	public function removeOwner(): void {
		$this->owner = null;
		$this->locked = false; //Cant be locked and no owner, causes endless loop.
		$this->updateNBT();
	}

	public function isFireProof(): bool {
		return true;
	}

	//Without this the player will not do the things it should be (driving, sitting etc)

	protected function sendInitPacket(Player $player, Vehicle $obj): void {
		//Below adds the entity ID + skin to the list to be used in the AddPlayerPacket (WITHOUT THIS DEFAULT/NO SKIN WILL BE USED).
		$pk = new PlayerListPacket();
		$pk->type = PlayerListPacket::TYPE_ADD;
		$pk->entries[] = PlayerListEntry::createAdditionEntry($obj->uuid, $obj->id, $obj::getName(), $obj::getDesign());
		$player->getNetworkSession()->sendDataPacket($pk);

		//Below adds the actual entity and puts the pieces together.
		$pk = new AddPlayerPacket();
		$pk->uuid = $obj->uuid;
		$pk->item = ItemFactory::air();
		$pk->motion = $obj->getMotion();
		$pk->position = $obj->getLocation();
		$pk->entityRuntimeId = $obj->getId();
		$pk->metadata = $obj->getNetworkProperties()->getAll();
		$pk->username = $obj::getName() . "-" . $obj->id; //Unique.
		$player->getNetworkSession()->sendDataPacket($pk);

		//Dont want to keep a fake person there...
		$pk = new PlayerListPacket();
		$pk->type = $pk::TYPE_REMOVE;
		$pk->entries = [PlayerListEntry::createRemovalEntry($obj->uuid)];
		$player->getNetworkSession()->sendDataPacket($pk);
	}

	/**
	 * Returns the Design of the vehicle.
	 * @return Skin|null
	 */
	abstract static function getDesign(): ?Skin;
}