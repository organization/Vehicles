<?php
/*
 * Vehicles, PocketMine-MP Plugin.
 *
 * Licensed under the Open Software License version 3.0 (OSL-3.0)
 * Copyright (C) 2019 Jackthehack21 (Jackthehaxk21/JaxkDev)
 *
 * Twitter :: @JaxkDev
 * Discord :: Jackthehaxk21#8860
 * Email   :: gangnam253@gmail.com
 */

declare(strict_types=1);

namespace Jackthehack21\Vehicles;

use pocketmine\Player;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\utils\TextFormat as C;

class CommandHandler
{
	/** @var Main */
	private $plugin;
	
	/** @var string */
	private $prefix;

	public function __construct(Main $plugin)
	{
		$this->plugin = $plugin;
		$this->prefix = $this->plugin->prefix;
	}

	/**
	 * @internal Used directly from pmmp, no other plugins should be passing commands here (if really needed, dispatch command from server).
	 *
	 * @param CommandSender|Player $sender
	 * @param Command $command
	 * @param array $args
	 */
	function handleCommand(CommandSender $sender, Command $command, array $args): void{
		if($sender instanceof ConsoleCommandSender){
			$sender->sendMessage($this->prefix.C::RED."Commands for Vehicles cannot be run from console.");
			return;
		}
		if(strtolower($command->getName()) !== "vehicles"){
			return; //Does pmmp do this for us, if only registering one command ?
		}
		if(count($args) == 0){
			$sender->sendMessage($this->prefix.C::RED."Usage: /vehicles help");
			return;
		}
		$subCommand = $args[0];
		array_shift($args);
		switch($subCommand){
			case 'help':
				$sender->sendMessage($this->prefix.C::RED."Help coming soon.");
				break;
			case 'spawn':
			case 'create':
			case 'new':
				if(!$sender->hasPermission("vehicles.command.spawn")){
					$sender->sendMessage($this->prefix.C::RED."You do not have permission to use that command.");
				}
				if(count($args) === 0){
					$sender->sendMessage($this->prefix.C::RED."Usage: /vehicles spawn (Type)");
					$sender->sendMessage($this->prefix.C::AQUA."Vehicle Types Available:\n- ".join("\n- ", array_keys($this->plugin->vehicleFactory->getTypes())).C::AQUA."\nObject Types Available:\n- ".join("\n- ", array_keys($this->plugin->objectFactory->getTypes())));
					return;
				}
				if(!$this->plugin->vehicleFactory->spawnVehicle($args[0], $sender->getLevel(), $sender->asVector3()) and !$this->plugin->objectFactory->spawnObject($args[0], $sender->getLevel(), $sender->asVector3())){
					$sender->sendMessage($this->prefix.C::RED."\"".$args[0]."\" does not exist.");
					return;
				};
				$sender->sendMessage($this->prefix.C::GOLD."\"".$args[0]."\" spawned.");
				break;
			case 'del':
			case 'rem':
			case 'delete':
			case 'remove':
				if(!$sender->hasPermission("vehicles.command.remove")){
					$sender->sendMessage($this->prefix.C::RED."You do not have permission to use that command.");
				}
				$this->plugin->interactCommands[strtolower($sender->getName())] = ["remove", [$args]];
				$sender->sendMessage($this->prefix.C::GREEN."Tap the vehicle/object you wish to remove.");
				break;
			default:
				$sender->sendMessage($this->prefix.C::RED."Unknown command, please check ".C::GREEN."/vehicles help".C::RED." For all available commands.");
		}
	}
}