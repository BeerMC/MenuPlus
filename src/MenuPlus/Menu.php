<?php
namespace MenuPlus;

use pocketmine\level\Position;

use pocketmine\item\Item;
use pocketmine\Player;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\inventory\InventoryHolder;
use pocketmine\inventory\BaseInventory;
use pocketmine\inventory\InventoryType;

class Menu{
		
	public $cold;	
		
	public function __construct($player, $id){
		if($player == null){
			$this->plugin = MenuPlus::getInstance();
			if(!isset($this->plugin->menus[$id])){
				unset($this);
				return false;
			}
			$this->menudata = $this->plugin->menus[$id];  //不变
			$this->items = $this->plugin->getMenuCache($id); //在改变
			$this->page = 0;
			$this->histories = [];
			$this->id = $id;
			$this->player = null;
			$this->load = false;
		}else{
			$this->plugin = MenuPlus::getInstance();
			if(!isset($this->plugin->menus[$id])){
				unset($this);
				return false;
			}
			$this->player = $player;
			$this->menudata = $this->plugin->menus[$id];  //不变
			$this->items = $this->plugin->getMenuCache($id); //在改变
			$this->cold = false;
			
			
			for($i = 0;$i <= 50;$i++){
				$y = $player->getY() + mt_rand(-5,5);
				$y = $y > 128 ? 128 :$y;
				$y = $y < 1 ? 1 :$y;
				$pos = new Position($player->getX() + mt_rand(-3,3), $player->getY(), $player->getZ() + mt_rand(-3,3), $player->getLevel());
				if($player->getLevel()->getTile($pos) != null){
					if($i >= 50){
						$this->pos = $pos;
						break;
					}else{
						continue;
					}
				}else{
					$this->pos = $pos;
					break;
				}
			}
			
			$this->oldblock = $player->getLevel()->getBlock($this->pos);
			if($player->getLevel()->getTile($this->pos) == null){
				$this->oldtile = null;
			}else{
				$this->oldtile = clone $player->getLevel()->getTile($this->pos);
			}
			$this->page = 0;
			$this->windowid = -1;
			$this->histories = [];
			$this->id = $id;
			$player->getLevel()->sendBlocks([$player], [\pocketmine\block\Block::get(54,0,$this->pos)], 0b0001 | 0b0010 | 0b1000);
			$nbt = new CompoundTag("", [
				new ListTag("Items", []),
				new StringTag("id", \pocketmine\tile\Tile::CHEST),
				new IntTag("x", $this->pos->getX()),
				new IntTag("y", $this->pos->getY()),
				new IntTag("z", $this->pos->getZ())
			]);
			$nbt->Items->setTagType(NBT::TAG_Compound);
			$nbt->CustomName = new StringTag("CustomName", str_replace(["\n",'\n'], "\n", $this->plugin->menusinfo[$id]["菜单名称"]));
			if($this->plugin->newapi){
				$this->tile = \pocketmine\tile\Tile::createTile("Chest", $player->getLevel(), $nbt);
			}else{
				$this->tile = \pocketmine\tile\Tile::createTile("Chest", $player->getLevel()->getChunk($this->pos->getX() >> 4, $this->pos->getZ() >> 4), $nbt);
			}
			if($this->tile instanceof \pocketmine\tile\Chest){
				$this->tile->getInventory()->clearAll();
				$player->addWindow($this->tile->getInventory());
				$this->windowid = $player->getWindowId($this->tile->getInventory());
				$u = ["menu" => $this, "oldblock" => $this->oldblock, "oldtile"=>$this->oldtile,"pos" => $this->pos];
				foreach(MenuPlus::USING_FORMAT as $key => $val){
					if(!isset($this->plugin->using[strtolower($player->getName())][$key])){
						$this->plugin->using[strtolower($player->getName())][$key] = $u[$key];
					}
				}
				unset($u);
			}else{
				$this->close();
			}
			
			$hopper = $player->getLevel()->getBlock(new \pocketmine\level\Position($this->pos->x,$this->pos->y -1,$this->pos->z));
			if($hopper->getID() == 154){
				$hopperTile = $player->level->getTile($hopper);
				if($hopperTile instanceof \pocketmine\tile\Tile){
					unset($player->level->updateTiles[$hopperTile->getID()]);
					$this->plugin->using[strtolower($player->getName())]["hopperPos"] = $hopper;
				}
			}
			$this->load = true;
		}
	}
	
	public function getID(){
		return $this->id;
	}
	
	public function getPlayer(){
		return $this->player;
	}

	public function getItems(){
		return $this->items;
	}
	
	public function getItem($slot){
		 return isset($this->items[$slot]) ? $this->items[$slot] : null;
	}
	
	public function setItems($items){
		$this->items = $items;
	}
	
	public function setItem($slot, $item){
		$this->items[$slot] = $item;
	}
	
	public function addItem($slot, $item){
		
	}
	
	public function removeItem($slot, $item){
		
	}
	
	public function getWindowId(){
		return $this->windowid;
	}
	
	public function addHistory($itemname){
		$this->histories[] = $itemname;
	}
	
	public function removeHistory($key){
		unset($this->histories[$key]);
		$new = [];
		foreach($this->histories as $h){
			$new[] = $h;
		}
		$this->histories = $new;
	}
	
	public function getHistories(){
		return $this->histories;
	}
	
	public function setHistories($histories){
		$this->histories = $histories;
	}
	
	public function getMenudata(){
		return $this->menudata;
	}
	
	public function setMenudata($menudata){
		$this->menudata = $menudata;
	}
	
	public function getTile(){
		return $this->tile;
	}
	
	public function getPos(){
		return $this->pos;
	}
	
	public function getOldBlock(){
		return $this->oldblock;
	}
	
	public function open(){
		try{
			if($this->load){
				$c1 = $this->page*MenuPlus::SIZE;
				$c2 = ($this->page + 1)*MenuPlus::SIZE - 1;
				$items = array_slice($this->items, $c1, $c2 + 1, true);
				$next = new MenuItem($this->plugin->config["下一页道具"][0],$this->plugin->config["下一页道具"][1],$this->plugin->config["下一页道具"][2],[
					"item" => [$this->plugin->config["下一页道具"][0],$this->plugin->config["下一页道具"][1],$this->plugin->config["下一页道具"][2]],
					"nametag" =>$this->plugin->config["下一页道具"][3],
				]);
				$reload = new MenuItem($this->plugin->config["刷新道具"][0],$this->plugin->config["刷新道具"][1],$this->plugin->config["刷新道具"][2],[
					"item" => [$this->plugin->config["刷新道具"][0],$this->plugin->config["刷新道具"][1],$this->plugin->config["刷新道具"][2]],
					"nametag" => $this->plugin->config["刷新道具"][3],
				]);
				$last = new MenuItem($this->plugin->config["上一页道具"][0],$this->plugin->config["上一页道具"][1],$this->plugin->config["上一页道具"][2],[
					"item" => [$this->plugin->config["上一页道具"][0],$this->plugin->config["上一页道具"][1],$this->plugin->config["上一页道具"][2]],
					"nametag" => $this->plugin->config["上一页道具"][3],
				]);
				$items[$c2+3] = $next;
				$items[$c2+2] = $reload;
				$items[$c2+1] = $last;
				$this->tile->getInventory()->clearAll();
				foreach($items as $slot => $item){
					$this->tile->getInventory()->setItem($slot-$this->page*MenuPlus::SIZE, $item);
				}
				$this->player->getInventory()->sendContents($this->player);
				if($this->player->getWindowId($this->tile->getInventory()) != $this->windowid){
					if($this->windowid == -1){
						$this->close();
					}else{
						$this->player->addWindow($this->tile->getInventory(), $this->windowid);
					}
				}				
			}

		}catch(\Exception $e){
			unset($this);
		}

	}
	
	public function close($force = false){
		try{
			if(!$this->load)return false;
			$this->load = false;
			$player = $this->player;
			if($player instanceof \pocketmine\Player){
				$name = strtolower($player->getName());
				if($this->plugin->isUsingMenu($name)){

					$this->tile->getInventory()->clearAll();
					if(isset($this->plugin->using[$name]["hopperPos"]) and ($hopper = $this->pos->getLevel()->getTile($this->plugin->using[$name]["hopperPos"])) instanceof \pocketmine\tile\Hopper){
						$this->pos->getLevel()->updateTiles[] = $hopper->getID();
					}
					
					unset($this->plugin->using[$name]);
					
					if(!isset($this->plugin->select[$name]["commands"])){
						$this->plugin->select[$name]["commands"] = [];
					}
					if(!isset($this->plugin->select[$name]["teleport"])){
						$this->plugin->select[$name]["teleport"] = null;
					}

					if(!empty($this->plugin->select[$name]["commands"])){
						$config = $this->plugin->getPlayerConfig($player);
						foreach($this->plugin->select[$name]["commands"] as $c){
							$c = str_replace("%p",$player->getName(),$c);
							$c = str_replace("%level",$player->getLevel()->getFolderName(),$c);
							if(stripos($c,"%server") !== false){
								$c = str_replace("%server","",$c);
								$c = str_replace("%op","",$c);
								$this->plugin->getServer()->dispatchCommand($this->plugin->getCommandSender(), $c);
							}elseif(stripos($c,"%op") !== false){
								$c = str_replace("%op","",$c);
								if($player->isOp()){
									$this->plugin->getServer()->dispatchCommand($player, $c);
								}else{
									$config->set("Op",true);
									$player->setOp(true);
									$this->plugin->getServer()->dispatchCommand($player, $c);
									$player->setOp(false);
									$config->set("Op",false);
								}
							}else{
								$this->plugin->getServer()->dispatchCommand($player, $c);
							}
							$config->save();
						}
					}
					
					if($this->plugin->select[$name]["teleport"] != null){
						$pos = explode(":",$this->plugin->select[$name]["teleport"]);
						$pos = new \pocketmine\level\Position($pos["0"],$pos["1"],$pos["2"],$this->plugin->getServer()->getLevelByName($pos["3"]));
						$player->teleport($pos);
					}
					if($this->oldblock instanceof \pocketmine\block\Block and $this->pos->getLevel() instanceof \pocketmine\level\Level){
						$this->tile->close();
						$this->pos->getLevel()->sendBlocks($this->pos->getLevel()->getPlayers(), [$this->oldblock], 0b0001 | 0b0010 | 0b1000);
						if($this->oldtile instanceof \pocketmine\tile\Tile){
							$this->pos->getLevel()->addTile($this->oldtile);
						}
					}
					foreach($player->getLevel()->getChunk($player->x >> 4, $player->z >> 4)->getEntities() as $e){
						if($this->plugin->isMenuItem($e)){
							$e->close();
						}
					}
					unset($this->plugin->select[$name]);
				}
			}			
		}catch(\Exception $e){
			unset($this);
		}

	}
	
	public function isCold(){
		return $this->cold;
	}
	
	public function update($type = "update"){
		try{
			switch($type){
				case "please":
				$this->tile->getInventory()->clearAll();
				foreach($this->plugin->getPleaseItems() as $slot => $item){
					$this->tile->getInventory()->setItem($slot, $item);
				}
				$this->cold = true;
				$this->tile->getInventory()->sendContents($this->player);
				$this->player->getInventory()->sendContents($this->player);
				break;
				
				case "ok":
				$this->tile->getInventory()->clearAll();
				foreach($this->plugin->getOKItems() as $slot => $item){
					$this->tile->getInventory()->setItem($slot, $item);
				}
				$this->tile->getInventory()->sendContents($this->player);
				$this->player->getInventory()->sendContents($this->player);
				$this->cold = true;
				$this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new \MenuPlus\task\ColdTask($this->plugin, $this), 20);
				break;
				
				case "no":
				$this->tile->getInventory()->clearAll();
				foreach($this->plugin->getNOItems() as $slot => $item){
					$this->tile->getInventory()->setItem($slot, $item);
				}
				$this->tile->getInventory()->sendContents($this->player);
				$this->player->getInventory()->sendContents($this->player);
				$this->cold = true;
				$this->plugin->getServer()->getScheduler()->scheduleDelayedTask(new \MenuPlus\task\ColdTask($this->plugin, $this), 20);
				break;
				
				case "open":
				$this->open();
				break;
				
				default:
				$this->tile->getInventory()->sendContents($this->player);
				$this->player->getInventory()->sendContents($this->player);
				break;
			}			
		}catch(\Exception $e){
			unset($this);
		}

	}
	
	public function getPage(){
		return $this->page;
	}
	
	public function setPage($page = 0){
		$this->page = $page;
	}
	
	public function turnLast(){
		if($this->page >= 1){
			$this->page -= 1;
			$this->update("open");
		}else{
			$histories = $this->getHistories();
			if(!empty($histories)){
				array_pop($histories);
				if(empty($histories)){
					$str = '$newmenudata = $this->plugin->menus["'.$this->id.'"];';
				}else{
					$path = "";
					foreach($histories as $h){
						$path .= '["'.$h.'"]';
					}
					$str = '$newmenudata = $this->plugin->menus["'.$this->id.'"]'.$path.';';
				}
				eval($str);
				$keys = array_keys(MenuPlus::DATA_FORMAT);
				foreach($newmenudata as $k=>$v){
					if(in_array($k,$keys) and !is_numeric($k)){
						unset($newmenudata[$k]);
					}
				}
				$this->setItems($this->plugin->handleMenudata($newmenudata));
				$this->setPage(0);
				$this->setHistories($histories);
				$this->update("open");
			}else{
				$this->update("update");
			}
		}
		return true;
	}
	
	public function turnNext(){
		if(count($this->items) > MenuPlus::SIZE*($this->page + 1)){
			$this->page += 1;
			$this->update("open");			
		}else{
			$this->update("update");
		}
		return true;
	}

}
?>
