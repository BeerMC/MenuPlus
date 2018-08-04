<?php

namespace MenuPlus;

use pocketmine\item\Item;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;

class MenuItem extends Item{
	public function __construct($id, $meta, $count, $args){
		$plugin = MenuPlus::getInstance();
		foreach(MenuPlus::DATA_FORMAT as $k => $v){
			if(!isset($args[$k])){
				$args[$k] = $v;
			}
		}
		$money = $args["price"];
		$cost_item = $args["cost_item"];
		$give_item = $args["give_item"];
		$nametag = $args["nametag"];
		if($money != 0){
			$nametag = $nametag . "\n§r" . ($money > 0 ? "§7> ".$plugin->config["花费货币前缀"]."§c".$plugin->config["货币昵称"]."§7: §e" . abs($money) : $plugin->config["获得货币前缀"]."§a".$plugin->config["货币昵称"]."§7: §e" . abs($money));
		}
		if($cost_item !== null){
			$str = "\n§r".$plugin->config["花费物品前缀"].$plugin->config["物品昵称"]."§7:";
			$cost_item = str_replace(" ","",$cost_item);
			$items = explode("++",$cost_item);
			$i = 1;
			foreach($items as $array){
				$array = explode(":",$array);
				if(count($array) == 3){
					$str .= " §f".$plugin->getItemName($array)."§7X§e".$array[2]. ($i % 3 == 0 ? "\n      ":"");
					$i += 1;
				}
			}

			$nametag .= $str;
		}
		if($give_item !== null){
			$str = "\n§r".$plugin->config["获得物品前缀"].$plugin->config["物品昵称"]."§7:";
			$give_item = str_replace(" ","",$give_item);
			$items = explode("++",$give_item);
			$i = 1;
			foreach($items as $array){
				$array = explode(":",$array);
				if(count($array) == 3){
					$str .= " §f".$plugin->getItemName($array)."§7X§a".$array[2]. ($i % 3 == 0 ? "\n      ":"");
					$i += 1;
				}
			}

			$nametag .= $str;
		}
		$nametag = $nametag."\n§r";
		$nametag = str_replace(["\n",'\n'], "\n", $nametag);
		$nbt=new CompoundTag("", [
			"display"=> new CompoundTag("display", [
			"Name" => new StringTag("Name", $nametag)
			]),
			"menudata" => new ListTag("menudata", $args)
		]);
		parent::__construct($id, $meta, $count);
		$this->setNamedTag($nbt);
		return $this;
	}
}
?>
