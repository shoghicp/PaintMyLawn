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
use pocketmine\utils\Utils;

class Main extends PluginBase{

	public function onCommand(CommandSender $sender, Command $command, $label, array $args){

		switch($command->getName()){
			case "paint":
				if(!($sender instanceof Player)){
					$sender->sendMessage(TextFormat::RED . "This command can only be used in-game.");
					return true;
				}
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
				$this->paintColor($sender->getLevel(), $x, $z, $colors[0], $colors[1], $colors[2]);
				$sender->sendMessage("Grass color on $x, $z set to RGB #{$args[0]}");
				return true;
				break;
			case "paintimage":
				if(count($args) > 2){
					$sender->sendMessage(TextFormat::RED . "Usage: ".$command->getUsage());
					return true;
				}
				if(count($args) === 2){
					$target = $this->getServer()->getPlayer($args[0]);
					if(!($target instanceof Player)){
						$sender->sendMessage(TextFormat::RED . "Usage: ".$command->getUsage());
						return true;
					}
					$url = $args[1];
				}elseif($sender instanceof Player){
					$target = $sender;
					$url = $args[0];
				}else{
					$sender->sendMessage(TextFormat::RED . "Usage: ".$command->getUsage());
					return true;
				}

				$sender->sendMessage("Painting image over grass...");
				$this->paintImage($sender, $target, $url);
				return true;
				break;
		}

	}

	private function paintImage(CommandSender $sender, Player $player, $url){
		//This method will lock while painting lots of chunks.
		//That's why it doesn't matter to use a locking HTTP request to get the file
		$ppm = Utils::getURL($url, 10);
		$type = substr($ppm, 0, 2);
		//P3: ASCII, P6: binary
		if($type !== "P6"){
			$sender->sendMessage(TextFormat::RED . "Invalid PPM image. Only binary PPM accepted.");
			return;
		}

		$stream = fopen("php://memory", "r+");
		fwrite($stream, $ppm);
		rewind($stream);

		fgets($stream); //Remove type

		$width = null;
		$height = null;
		$white = null;

		$linen = 0;
		while(!feof($stream)){
			$line = fgets($stream);
			if($line{0} === "#"){
				continue;
			}
			if($linen === 0){
				$data = explode(" ", $line);
				$width = (int) $data[0];
				$height = (int) $data[1];
			}elseif($linen === 1){
				$white = (int) $line;
				break;
			}
			++$linen;
		}

		$ppm = stream_get_contents($stream);
		fclose($stream);
		if(strlen($ppm) !== ($width * $height * 3)){
			$sender->sendMessage(TextFormat::RED . "Invalid PPM file");
			return;
		}

		$centerX = (int) ($player->x - 0.5);
		$centerZ = (int) ($player->z - 0.5);
		$startX = $centerX - floor($width / 2);
		$endX = $centerX + ceil($width / 2);
		$startZ = $centerZ - floor($height / 2);
		$endZ = $centerZ + ceil($height / 2);

		$offset = 0;
		$level = $player->getLevel();
		for($x = $startX; $x <= $endX; ++$x){
			for($z = $startZ; $z <= $endZ and isset($ppm{$offset}); ++$z){
				$r = ord($ppm{$offset++});
				$g = ord($ppm{$offset++});
				$b = ord($ppm{$offset++});
				$level->setBiomeColor($x, $z, $r, $g, $b);
			}
		}

		$sender->sendMessage("Image painted!");
	}

	private function paintColor(Level $level, $x, $z, $r, $g, $b){
		$level->setBiomeColor($x, $z, $r, $g, $b);
		$index = Level::chunkHash($x, $z);
		Cache::remove("world:".($level->getName()).":" . $index);
		foreach($level->getUsingChunk($x, $z) as $player){
			$player->setChunkIndex($index, 0xff);
		}
	}

}