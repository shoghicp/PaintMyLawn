<?php

/*
 * PaintMyLawn plugin for PocketMine-MP
 * Copyright (C) 2014 shoghicp <https://github.com/shoghicp/PaintMyLawn>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 */

namespace shoghicp\PaintMyLawn;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\level\Level;
use pocketmine\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\Cache;
use pocketmine\utils\TextFormat;

class Main extends PluginBase{

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){
		if($sender instanceof Player){
			switch($command->getName()){
				case "paint":
					if(count($args) === 1){
						if(strlen($args[0]) !== 6){
							$sender->sendMessage(TextFormat::RED . "Usage: ".$command->getUsage());
							return true;
						}
						$colors = array_map("hexdec", str_split($args[0], 2));
					}elseif(count($args) === 3){
						$colors = [(int) $args[0], (int) $args[1], (int) $args[2]];
					}else{
						$sender->sendMessage(TextFormat::RED . "Usage: ".$command->getUsage());
						return true;
					}


					$x = (int) ($sender->x - 0.5);
					$z = (int) ($sender->z - 0.5);
					$this->setGrassColor($sender->getLevel(), $x, $z, $colors[0], $colors[1], $colors[2]);
					$sender->sendMessage("Grass color on $x, $z set to RGB #{$args[0]}");
					return true;
					break;
			}
		}else{
			$sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
			return true;
		}
	}

	private function setGrassColor(Level $level, $x, $z, $r, $g, $b){
		$level->setBiomeColor($x, $z, $r, $g, $b);
		$index = Level::chunkHash($x, $z);
		Cache::remove("world:".($level->getName()).":" . $index);
		foreach($level->getUsingChunk($x, $z) as $player){
			$player->setChunkIndex($index, 0xff);
		}
	}

}