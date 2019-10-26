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

namespace JaxkDev\Vehicles\External;

use JaxkDev\Vehicles\Main;
use JaxkDev\Vehicles\Vehicle\Vehicle;
use pocketmine\entity\Skin;
use pocketmine\math\Vector3;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\world\World;

class BLANK extends Vehicle {
	public $width = 0;
	public $height = 0;

	protected $baseOffset = 1.615;

	public function __construct(World $world, CompoundTag $nbt) {
		$this->driverPosition = new Vector3(0, 0, 0); //X,Y,Z
		$this->passengerPositions[] = new Vector3(0, 0, 0); //X,Y,Z
		//Repeat the same line above to make multiple passenger seats.
		parent::__construct($world, $nbt);

		$this->setScale(1); //If you had to scale down/up while modelling you can set it to real size here.
	}

	static function getName(): string {
		return "BLANK";
	}

	static function getDesign(): Skin {
		return Main::getInstance()->designFactory->getDesign("BLANK-DESIGN");
	}

	/**
	 * Handle player input.
	 * @param float $x
	 * @param float $y
	 */
	function updateMotion(float $x, float $y): void {
		//Feel free to play around but this is the basics.

		//				(1 if only one button, 0.7 if two)
		//+y = forward. (+1/+0.7)
		//-y = backward. (-1/-0.7)
		//+x = left (+1/+0.7)
		//-x = right (-1/-0.7)
		if ($x !== 0) {
			$this->location->yaw -= $x * 6;
			$this->motion = $this->getDirectionVector();
		}

		if ($y > 0) {
			//forward
			$this->motion = $this->getDirectionVector()->multiply($y * 2.5);
			$this->location->yaw = $this->driver->getLocation()->getYaw();// - turn based on players rotation
		} elseif ($y < 0) {
			//reverse
			$this->motion = $this->getDirectionVector()->multiply($y * 1.5);
		}
	}

	protected function sendSpawnPacket(Player $player): void {
		parent::sendInitPacket($player, $this);
		//Leave this bit alone.
	}
}