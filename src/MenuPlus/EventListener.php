<?php
namespace MenuPlus;

use pocketmine\entity\Effect;
use pocketmine\entity\Snowball;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeMap;

use pocketmine\Player;
use pocketmine\item\Item;
use pocketmine\block\Block;
use pocketmine\block\Air;
use pocketmine\block\Chest;
use pocketmine\tile\Tile;
use pocketmine\tile\Sign;
use pocketmine\tile\Chest as TileChest;
use pocketmine\scheduler\ServerScheduler;
use pocketmine\scheduler\PluginTask;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\math\Vector3;
use pocketmine\inventory\PlayerInventory;
use pocketmine\inventory\BigShapedRecipe;
use pocketmine\inventory\ShapedRecipe;
use pocketmine\inventory\ShapelessRecipe;
use pocketmine\event\Listener;
use pocketmine\event\inventory\InventoryPickupItemEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerKickEvent;
use pocketmine\event\player\PlayerDeathEvent;
use pocketmine\event\player\PlayerRespawnEvent;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\entity\ProjectileLaunchEvent;
use pocketmine\event\entity\EntityDespawnEvent;
use pocketmine\event\entity\EntityTeleportEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\ExplosionPrimeEvent;
use pocketmine\event\player\PlayerLoginEvent;
use pocketmine\event\player\PlayerMoveEvent;
use pocketmine\event\player\PlayerTextPreSendEvent;
use pocketmine\event\player\PlayerItemHeldEvent;
use pocketmine\event\player\PlayerDropItemEvent;
use pocketmine\event\player\PlayerCreationEvent;
use pocketmine\event\player\PlayerCommandPreprocessEvent;
use pocketmine\event\block\SignChangeEvent;
use pocketmine\event\server\DataPacketReceiveEvent;
use pocketmine\event\server\DataPacketSendEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;

use pocketmine\level\Level;
use pocketmine\level\Position;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\level\particle\DustParticle;
use pocketmine\level\particle\FloatingTextParticle;
use pocketmine\level\particle\Particle;
use pocketmine\level\particle\CriticalParticle;
use pocketmine\level\particle\HeartParticle;
use pocketmine\level\particle\LavaParticle;
use pocketmine\level\particle\PortalParticle;
use pocketmine\level\particle\RedstoneParticle;
use pocketmine\level\particle\SmokeParticle;
use pocketmine\level\particle\WaterParticle;
use pocketmine\level\sound\ClickSound;
use pocketmine\level\sound\DoorSound;
use pocketmine\level\sound\LaunchSound;
use pocketmine\level\sound\PopSound;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;

use MenuPlus\MenuPlus;
class EventListener implements Listener{
	const FULL_CHUNK_DATA_PACKET = 0x3a;
	const DROP_ITEM_PACKET = 0x2e;
	const ITEM_FRAME_DROP_ITEM_PACKET = 0x47;
	const TEXT_PACKET = 0x09;
	public $edit;
	
	public $tap;
	
	public $use;
	
	public $order;
	
	public function __construct(MenuPlus $plugin){
		$this->plugin = $plugin;
		$this->tap = [];
	}
	
	public function onPickup(InventoryPickupItemEvent $event){
		if($this->plugin->isMenuItem($event->getItem())){
			$event->getItem()->setCount(0);
			$event->setCancelled();
		}
	}
	
	public function onDataReceive(DataPacketReceiveEvent $event){
		$pk = $event->getPacket();
		if($pk->getName() == "ContainerSetSlotPacket"){
			$player = $event->getPlayer();
			if($player instanceof Player){
				if(isset($this->plugin->using[strtolower($player->getName())]["menu"])){
					if($this->plugin->using[strtolower($player->getName())]["menu"]->getWindowId() == $pk->windowid){
						$this->plugin->select($player,$pk);
					}
					$event->setCancelled(true);
				}
			}
		}elseif($pk->getName() == "ContainerClosePacket"){
			$player = $event->getPlayer();
			if($player instanceof Player){
				if($this->plugin->isUsingMenu($player)){
					$this->plugin->closeMenu($player);
				}				
			}
		}elseif($pk::NETWORK_ID == self::DROP_ITEM_PACKET){
			$player = $event->getPlayer();
			if($player instanceof Player){
				if($this->plugin->isUsingMenu($player) or $this->plugin->isMenuItem($pk->item)){
					$event->setCancelled();
				}
			}
		}elseif($pk::NETWORK_ID == self::ITEM_FRAME_DROP_ITEM_PACKET){
			$player = $event->getPlayer();
			if($player instanceof Player){
				if($this->plugin->isUsingMenu($player)){
					$event->setCancelled();
				}
			}
		}
		if($pk::NETWORK_ID == self::TEXT_PACKET){
			$player = $event->getPlayer();
			if($player instanceof Player){
				if($this->plugin->setter == $player and isset($this->plugin->set["set_level"])){
					$event->setCancelled();
					$this->onChat($player, $pk->message);
				}
			}
		}
	}
	
	public function onJoin(PlayerJoinEvent $event){
		$player = $event->getPlayer();
		$config = $this->plugin->getPlayerConfig($player);
		if(!$config->get("Op") and $config->get("Op") != null){
			$player->setOp(false);
			$config->set("Op",null);
			$config->save();
		}
		foreach($this->plugin->menusinfo as $id => $info){
			if($this->plugin->menusinfo[$id]["是否玩家进服给快捷道具"] == true){
				$this->plugin->giveTool($id, $player);
			}
		}
	}
	
	public function onQuit(PlayerQuitEvent $event){
		$player = $event->getPlayer();
		$this->plugin->set_stop($player);
		if($this->plugin->isUsingMenu($player)){
			$this->plugin->using[strtolower($player->getName())]["menu"]->close();
			unset($this->plugin->using[strtolower($player->getName())]);
		}
	}

	public function onClose(\pocketmine\event\inventory\InventoryCloseEvent $event){
		$player = $event->getPlayer();
		if($this->plugin->isUsingMenu($player)){
			$this->plugin->closeMenu($player);
		}
	}
		
	public function onTeleport(\pocketmine\event\entity\EntityTeleportEvent $event){
		$player = $event->getEntity();
		if($player instanceof Player){
			if($this->plugin->isUsingMenu($player)){
				$this->plugin->closeMenu($player);
			}
		}
	}

	public function onTouch(\pocketmine\event\player\PlayerInteractEvent $event){
		if($event->isCancelled() and $this->plugin->config["征求其他插件的意见来处理玩家当时能不能点地打开菜单"]){
			return;
		}
		$player = $event->getPlayer();
		$item = $player->getInventory()->getItemInHand();
		if(isset($item->getNamedTag()["MenU"])){
			$this->plugin->openMenu($player, (string)$item->getNamedTag()->MenU);
			$event->setCancelled();
		}
		$block = $event->getBlock();
		if($block->x == 0 and $block->y == 0 and $block->z == 0){
			return;
		}
		if($this->plugin->isSetting($player)){
			$name  = strtolower($player->getName());
			$id = $event->getItem()->getID();
			switch($id){
				case "292":
				case 292:
					$player->sendPopup($this->plugin->config["消息前缀"]."§6查询/修改 §7(菜单工具) 多次点击按钮使用");
					if(!isset($this->tap[$name])){
						$this->tap[$name] = [$block, $id];
						$this->plugin->handleSet("look", $block, $player);
					}else{
						if($this->tap[$name][0] == $block and  $this->tap[$name][1] == $id){
							$this->plugin->handleSet("set", $block, $player);
							unset($this->tap[$name]);
						}else{
							$this->tap[$name] = [$block, $id];
							$this->plugin->handleSet("look", $block, $player);
						}
					}
				break;
				
				case "258":
				case 258:  //删除
					$player->sendPopup($this->plugin->config["消息前缀"]."§c删除 §7(菜单工具) 多次点击按钮使用");
					if(!isset($this->tap[$name])){
						$this->tap[$name] = [$block, $id];
					}else{
						if($this->tap[$name][0] == $block  and  $this->tap[$name][1] == $id){
							$this->plugin->handleSet("delete", $block, $player);
							unset($this->tap[$name]);
						}else{
							$this->tap[$name] = [$block, $id];
						}
					}
				break;
				
				case "256":
				case 256:  //进入
					$player->sendPopup($this->plugin->config["消息前缀"]."§a进入多级菜单 §7(菜单工具) 多次点击按钮使用");
					if(!isset($this->tap[$name])){
						$this->tap[$name] = [$block, $id];
					}else{
						if($this->tap[$name][0] == $block  and  $this->tap[$name][1] == $id){
							$this->plugin->handleSet("enter", $block, $player);
							unset($this->tap[$name]);
						}else{
							$this->tap[$name] = [$block, $id];
						}
					}
				break;
				
				case "283":
				case 283:
					$player->sendPopup($this->plugin->config["消息前缀"]."§a上一页 §7(菜单工具) 多次点击按钮使用");
					if(!isset($this->tap[$name])){
						$this->tap[$name] = [$block, $id];
					}else{
						if($this->tap[$name][0] == $block  and  $this->tap[$name][1] == $id){
							$this->plugin->handleSet("last", $block, $player);
							unset($this->tap[$name]);
						}else{
							$this->tap[$name] = [$block, $id];
						}
					}
				break;
				
				case "285":
				case 285:
					$player->sendPopup($this->plugin->config["消息前缀"]."§a下一页 §7(菜单工具) 多次点击按钮使用");
					if(!isset($this->tap[$name])){
						$this->tap[$name] = [$block, $id];
					}else{
						if($this->tap[$name][0] == $block  and  $this->tap[$name][1] == $id){
							$this->plugin->handleSet("next", $block, $player);
							unset($this->tap[$name]);
						}else{
							$this->tap[$name] = [$block, $id];
						}
					}
				break;
				
				default:
				break;
			}
		}
	}
	
	public function onSendPacket(DataPacketSendEvent $ev){
		if ($ev->getPacket()->pid() !== self::FULL_CHUNK_DATA_PACKET)
			return;
		$pl = $ev->getPlayer();
		$level = $pl->getLevel();
		if (!isset($this->plugin->set["cases"][$level->getName()])) return;
		$chunkX = $ev->getPacket()->chunkX;
		$chunkZ = $ev->getPacket()->chunkZ;
		foreach (array_keys($this->plugin->set["cases"][$level->getName()]) as $cid) {
			$pos = explode(":",$cid);
			if ($pos[0] >> 4 == $chunkX && $pos[2] >> 4 == $chunkZ) {
				$this->plugin->sendItemCase($level,$cid,[$pl]);
			}
		}
	}
	
	//public function onChat(PlayerChatEvent $event){
	public function onChat($player, $msg){
		if($this->plugin->setter == $player and isset($this->plugin->set["set_level"])){
			switch($this->plugin->set["set_level"]){
				case 0:
				if(is_numeric($msg)){
					if($msg < 0){
						$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数或0");
					}else{
						if($msg > 0){
							$this->plugin->set["set_item"]["id"] = intval($msg);
							$this->plugin->set["set_level"] = 1;
							$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e物品的特殊值§f,默认为§30§f,0也得输入");							
						}else{
							$this->plugin->set["set_item"]["id"] = 0;
							$this->plugin->set["set_item"]["meta"] = 0;
							$this->plugin->set["set_item"]["count"] = 1;
							$this->plugin->set["set_item"]["nametag"] = null;
							$this->plugin->set["set_item"]["commands"] = null;
							$this->plugin->set["set_item"]["teleport"] = null;
							$this->plugin->set["set_item"]["price"] = 0;
							$this->plugin->set["set_item"]["give_item"] = null;
							$this->plugin->set["set_item"]["cost_item"] = null;
							$this->plugin->set["set_item"]["multilevel"] = false;
							$this->plugin->set["set_level"] = 10;
							$player->sendMessage($this->plugin->config["消息前缀"]."设置成功,物品§aid§f为[§b $msg §f]");
							$player->sendMessage($this->plugin->config["消息前缀"]."这将是一个空格子");
							$player->sendMessage($this->plugin->config["消息前缀"]."请输入§a(覆盖原来按钮)§f或§a(增加新的按钮)  §3覆盖 §f或 §3新建");
						}
					}
				}else{
					$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数或0");
				}
				break;
				
				case 1:
				if(is_numeric($msg)){
					if($msg < 0 or (int)$msg != $msg){
						$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数或0");
					}else{
						$this->plugin->set["set_item"]["meta"] = intval($msg);
						$this->plugin->set["set_level"] = 2;
						$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e物品的数量§f,默认为§31§f,1也得输入");
					}
				}else{
					$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数或0");
				}				
				break;
				
				
				case 2:
				if(is_numeric($msg)){
					if($msg <= 0 or (int)$msg != $msg){
						$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数");
					}else{
						$this->plugin->set["set_item"]["count"] = intval($msg);
						$this->plugin->set["set_level"] = 3;
						$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e按钮昵称§f");
					}
				}else{
					$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,请输入正整数");
				}	
				break;
				
				
				case 3:
				$msg = str_replace(":", "：",$msg);
				$this->plugin->set["set_item"]["nametag"] = $msg;
				$this->plugin->set["set_level"] = 4;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e使用按钮给予物品§f,§3物品ID:物品特殊值:物品数量§f(若多种物品则用++隔开),如不消耗就只输入 §3否");
				break;
				
				case 4:
				if($msg === "否" or $msg === "false"){
					$msg = null;
				}else{
					$msg = str_replace(" ","",$msg);
					$items = explode("++",$msg);
					foreach($items as $array){
						$array = explode(":",$array);
						if(count($array) !== 3){
							$player->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  物品ID:物品特殊值:物品数量 (若多种物品则用++隔开)");
							return;
						}
						foreach($array as $k => $v){
							if(!is_numeric($v)){
								$player->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  物品ID:物品特殊值:物品数量 (若多种物品则用++隔开)");
								return;
							}
						}
					}
				}
				$this->plugin->set["set_item"]["give_item"] = $msg;
				$this->plugin->set["set_level"] = 5;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e触发指令§f,§3多个指令请用 ++ 隔开§f,如无内容就只输入 §3否");
				$player->sendMessage($this->plugin->config["消息前缀"]."          特殊参数:§d%p §7使用菜单的玩家  §d%level §7玩家所在世界 §d%op §7模拟玩家为op执行命令(不要加空格) §d%server §7让服务器运行此命令(不要加空格)");
				$player->sendMessage($this->plugin->config["消息前缀"]."          例子§7: spawn++say 我回到主城获得了100金币++%servergivemoney %p 100");
				break;
				
				
				case 5:
				if($msg === "否" or $msg === "false"){
					$commands = [];
				}else{
					$commands = explode("++", $msg);
				}
				foreach($commands as $k => $v){
					$commands[$k] = trim($v);
				}
				$this->plugin->set["set_item"]["commands"] = $commands;
				$this->plugin->set["set_level"] = 6;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e触发传送§f,§3x:y:z:地图名§f,如无内容就只输入 §3否");
				$player->sendMessage($this->plugin->config["消息前缀"]."          例子§7: 256:122:64:zy   或   否");
				break;
				
				
				case 6:
				if($msg === "否" or $msg === "false"){
					$msg = null;
				}else{
					$msg = str_replace(" ","",$msg);
						foreach(explode(":",$msg) as $k => $v){
							if(!is_numeric($v) and $k<=2){
								$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,格式为  x:y:z:地图名");
								return;
							}
						}					
					}
				$this->plugin->set["set_item"]["teleport"] = $msg;
				$this->plugin->set["set_level"] = 7;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e使用一次的价格§f,默认为§30§f,0也要输入");
				$player->sendMessage($this->plugin->config["消息前缀"]."          例子§7: -10 玩家使用后将得到10金钱 或 100 玩家使用消耗100金钱");
				break;
				
				case 7:
				if(is_numeric($msg)){
					$this->plugin->set["set_item"]["price"] = $msg;
					$this->plugin->set["set_level"] = 8;
					$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e消耗物品§f,§3物品ID:物品特殊值:物品数量§f(若多种物品则用++隔开),如不消耗就只输入 §3否");
					$player->sendMessage($this->plugin->config["消息前缀"]."          例子§7: 2:0:5++50:0:1 (使用此按钮需要消耗5个草方块和1个火把,否则不可使用)   或   否");
				}else{
					$player->sendMessage($this->plugin->config["消息前缀"]."§4输入错误,内容需要为数字");
				}
				break;
				
				case 8:
				if($msg === "否" or $msg === "false"){
					$msg = null;
				}else{
					$msg = str_replace(" ","",$msg);
					$items = explode("++",$msg);
					foreach($items as $array){
						$array = explode(":",$array);
						if(count($array) !== 3){
							$player->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  物品ID:物品特殊值:物品数量 (若多种物品则用++隔开)");
							return;
						}
						foreach($array as $k => $v){
							if(!is_numeric($v)){
								$player->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  物品ID:物品特殊值:物品数量 (若多种物品则用++隔开)");
								return;
							}
						}
					}
				}
				$this->plugin->set["set_item"]["cost_item"] = $msg;
				$this->plugin->set["set_level"] = 9;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§e是否为多级菜单§f,§3是 §f或 §3否");
				break;
				
				case 9:
				if(strtolower($msg) === "true"){
					$this->plugin->set["set_item"]["multilevel"] = true;
				}elseif(strtolower($msg)  === "false"){
					$this->plugin->set["set_item"]["multilevel"] = false;
				}else{
					$this->plugin->set["set_item"]["multilevel"] = ($msg==="是" ? true : false);
				}
				$this->plugin->set["set_level"] = 10;
				$player->sendMessage($this->plugin->config["消息前缀"]."请输入§a(覆盖原来按钮)§f或§a(增加新的按钮)  §3覆盖 §f或 §3新建");
				break;
				
				case 10:
				$body = $this->plugin->set["menu"]->getMenudata();
				$histories = $this->plugin->set["menu"]->getHistories();
				$path = "";
				foreach($histories as $h){
					$path .= '["'.$h.'"]';
				}
				if($msg == "覆盖"){
					if($this->plugin->set["set_key"] != null){
					eval('$body'.$path.'[$this->plugin->set["set_key"]] = ["item" => [$this->plugin->set["set_item"]["id"], $this->plugin->set["set_item"]["meta"], $this->plugin->set["set_item"]["count"]],
															   "nametag" => $this->plugin->set["set_item"]["nametag"],
															   "commands" => $this->plugin->set["set_item"]["commands"],
															   "teleport" => $this->plugin->set["set_item"]["teleport"],
															   "price" => $this->plugin->set["set_item"]["price"],
															   "give_item" => $this->plugin->set["set_item"]["give_item"],
															   "cost_item" => $this->plugin->set["set_item"]["cost_item"],
															   "multilevel" => $this->plugin->set["set_item"]["multilevel"],
															 ];');				
					}else{
					eval('$body'.$path.'[] = ["item" => [$this->plugin->set["set_item"]["id"], $this->plugin->set["set_item"]["meta"], $this->plugin->set["set_item"]["count"]],
															   "nametag" => $this->plugin->set["set_item"]["nametag"],
															   "commands" => $this->plugin->set["set_item"]["commands"],
															   "teleport" => $this->plugin->set["set_item"]["teleport"],
															   "price" => $this->plugin->set["set_item"]["price"],
															   "give_item" => $this->plugin->set["set_item"]["give_item"],
															   "cost_item" => $this->plugin->set["set_item"]["cost_item"],
															   "multilevel" => $this->plugin->set["set_item"]["multilevel"],
															 ];');
					}
				}elseif($msg == "新建"){
					eval('$body'.$path.'[] = ["item" => [$this->plugin->set["set_item"]["id"], $this->plugin->set["set_item"]["meta"], $this->plugin->set["set_item"]["count"]],
															   "nametag" => $this->plugin->set["set_item"]["nametag"],
															   "commands" => $this->plugin->set["set_item"]["commands"],
															   "teleport" => $this->plugin->set["set_item"]["teleport"],
															   "price" => $this->plugin->set["set_item"]["price"],
															   "give_item" => $this->plugin->set["set_item"]["give_item"],
															   "cost_item" => $this->plugin->set["set_item"]["cost_item"],
															   "multilevel" => $this->plugin->set["set_item"]["multilevel"],
															 ];');
				}else{
					$player->sendMessage($this->plugin->config["消息前缀"]."§c错误!  §a(覆盖原来按钮)§f或§a(增加新的按钮)  §3覆盖 §f或 §3新建");
					return;
				}
						$this->plugin->set["menu"]->setMenudata($body);
						$this->plugin->set["menu"]->setItems($this->plugin->handleMenudata($body));
						$this->plugin->set["menu"]->setPage(0);
						$this->plugin->set["menu"]->setHistories([]);
						$this->plugin->menus[$this->plugin->set["id"]] = $body;
						$this->plugin->cacheMenu($this->plugin->set["id"]);
						$this->plugin->W_update();
						unset($this->plugin->set["set_item"]);
						unset($this->plugin->set["set_level"]);
						$player->sendMessage($this->plugin->config["消息前缀"]."---新的菜单按钮设置成功---");
				break;
				
				default:
				break;
			}
		}
	}
	
	public function onBreak(BlockBreakEvent $event){
		if($this->plugin->isSetting($event->getPlayer())){
			$event->getPlayer()->sendMessage($this->plugin->config["消息前缀"]."§8输入§3/menu ok§8后方可退出菜单编辑模式");
			$event->setCancelled();
		}
		$tile = $event->getBlock()->getLevel()->getTile($event->getBlock());
		if($tile instanceof TileChest){
			$item1 = $tile->getInventory()->getItem(MenuPlus::MAX_SIZE - 3);//上一个
			$item2 = $tile->getInventory()->getItem(MenuPlus::MAX_SIZE - 2);
			$item3 = $tile->getInventory()->getItem(MenuPlus::MAX_SIZE - 1);//下一个
			if($this->plugin->isMenuItem($item1) or ($item1->getID()==$this->plugin->config["上一页道具"][0] and $item2->getID()==$this->plugin->config["刷新道具"][0] and $item3->getID()==$this->plugin->config["下一页道具"][0])){
				$event->setCancelled();
				$tile->getInventory()->clearAll();
			}
		}
	}
	
	public function onPlace(BlockPlaceEvent $e){
		if($this->plugin->isSetting($e->getPlayer())){
			$e->getPlayer()->sendMessage($this->plugin->config["消息前缀"]."§8输入§3/menu ok§8后方可退出菜单编辑模式");
			$e->setCancelled();
		}
	}
	
}
?>