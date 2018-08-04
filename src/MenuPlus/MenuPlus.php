<?php
namespace MenuPlus;

use pocketmine\command\Command;
use pocketmine\command\CommandExecutor;
use pocketmine\command\CommandSender;
use pocketmine\command\ConsoleCommandSender;

use pocketmine\event\Listener;
use pocketmine\entity\Effect;
use pocketmine\item\Item;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityRegainHealthEvent;
use pocketmine\inventory\PlayerInventory;

use pocketmine\level\Level;
use pocketmine\block\Block;
use pocketmine\level\Position;
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

use pocketmine\block\Chest;
use pocketmine\tile\Tile;
use pocketmine\tile\Chest as TileChest;
use pocketmine\level\format\mcregion\Chunk;
use pocketmine\level\format\FullChunk;
use pocketmine\tile\Sign as TileSign;

use pocketmine\scheduler\PluginTask;

use pocketmine\plugin\PluginBase;
use pocketmine\Player;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\utils\Config;
use pocketmine\math\Vector3 as Vector3;

use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;

class MenuPlus extends PluginBase implements CommandExecutor{
	
	const FORMAT = "§l§8▌§b超级菜单§8▌§r ";
	
	const SIZE = 24;
	
	const MAX_SIZE = 27;
	
	const DATA_FORMAT = [
		"item" => [0, 0, 1],
		"nametag" => null,
		"commands" => [],
		"teleport" => null,
		"price" => 0,
		"give_item" => null,
		"cost_item" => null,
		"multilevel" => false,
	];
	
	const USING_FORMAT = [
	"menu" => null,
	"oldblock" => null,
	"oldtile" => null,
	"pos" => null,
	];
		
    public static $instance;
		
	public $path;

	public $newapi;
		
	public $menus;
	
	public $itemlist;
		
	public static function getInstance(){
		return self::$instance;
	}

	public function isPHP7(){
		if(in_array(substr(PHP_VERSION,0,1),["7",7])){
			return true;
		}
		return false;	
	}
	
	public function isNewAPI(){
		$reflector = new \ReflectionClass('\pocketmine\tile\Tile');
		$parameters = $reflector->getMethod('createTile')->getParameters();
		if(is_object($parameters[1])){
			if(stripos($parameters[1]->name, "level") !== false or $parameters[1] instanceof Level){
				return true;
			}
		}
		return false;
	}
	
	public function getPacketObj($name){
		if(class_exists('\pocketmine\network\protocol'."\\".$name, false)){
			$str = '\pocketmine\network\protocol'."\\".$name;
			return new $str();
		}elseif(class_exists('\pocketmine\network\mcpe\protocol'."\\".$name, false)){
			$str = '\pocketmine\network\mcpe\protocol'."\\".$name;
			return new $str();
		}else{
			return false;
		}
	}

	public function onLoad(){
		self::$instance = $this;
	}

	public function onEnable(){
        $this->getLogger()->info("§d开始初始化...");
		if($this->isNewAPI()){
			$this->newapi = true;
		}else{
			$this->newapi = false;
		}
		$this->menucache = [];
		$this->using = [];
		$this->select = [];
		$this->setter = null;
		$this->set = [];
		$this->getServer()->getPluginManager()->registerEvents(new EventListener($this), $this);
		$this->path = $this->getDataFolder();
		@mkdir($this->path ."players/", 0777, true);
		$this->getLogger()->info("§d正在读取§6配置文件§d,若接下来报错,可能是config.yml文件中数据出错");
		$this->config = (new Config($this->path . "config.yml", Config::YAML))->getAll();
		$this->loadConfig();
		$this->getLogger()->info("§d正在读取§6物品列表§d,若接下来报错,可能是itemlist.yml文件中数据出错");
		$this->itemlist = (new Config($this->path . "itemlist.yml", Config::YAML))->getAll();
		$this->loadItemlist();
		$this->getLogger()->info("§d正在读取§6菜单数据§d,若接下来报错,可能是menus.yml文件中数据出错");
		$this->menus = (new Config($this->path . "menus.yml", Config::YAML))->getAll();
		$this->getLogger()->info("§d正在读取§b菜单信息§d,若接下来报错,可能是menusinfo.yml文件中数据出错");
		$this->menusinfo = (new Config($this->path . "menusinfo.yml", Config::YAML))->getAll();
		$this->saveMenusInfo();
		$this->getLogger()->info("§d正在进行§6菜单缓存§d,若接下来报错,可能是menus.yml文件中数据出错");
		$this->cacheAllMenus();
		$this->cachePleaseItems();
		$this->cacheOKItems();
		$this->cacheNOItems();
		$this->registerCommands();
		$this->sender = new ConsoleCommandSender();
		$this->getLogger()->info("§a初始化完毕");
	}
	
	public function onDisable(){
		$this->saveConfigs();
		$this->saveMenus();
		$this->saveMenusInfo();
		($this->setter instanceof Player) ? $this->set_stop($this->setter) : $this->W_paste();
	}
	
    public function loadConfig(){
    	$configs = [
    	"注意" => '选项的格式:true为打开,false为关闭,加入其他字符的后果自负',
		"消息前缀" => self::FORMAT,
		"货币昵称" => "§e金币",
		"物品昵称" => "§2材料",
		"花费货币前缀" => "§7> §9需花费",
		"获得货币前缀" => "§7> §9可获得",
		"花费物品前缀" => "§7> §9需消耗",
		"获得物品前缀" => "§7> §9可获得",
		"使用按钮反馈的开关" => true,
		"上一页道具" => [404,0,1,"§b< §7上一页"],
		"刷新道具" => [208,0,1,"§7刷新"],
		"下一页道具" => [356,0,1,"§7下一页 §b>"],
		"征求其他插件的意见来处理玩家当时能不能点地打开菜单" => false,
    	];
    	foreach ($configs as $key => $value) {
    		if(isset($this->config[$key]) == false){
				$this->config[$key] = $value;
				@$this->getServer()->getLogger()->info("§7[初始化]已将§a".$key."§7设置为§b".$value);
    		}
			if(is_array($value)){
				foreach($value as $key2 => $val2){
					if(!isset($this->config[$key][$key2])){
						$this->config[$key][$key2] = $val2;
						@$this->getServer()->getLogger()->info("§7[初始化]已将§a".$key."§7中的§a".$key2."§7设置为§b".$val2);
					}
				}
			}
    	}
    	$this->saveConfigs();
    }
	
    public function saveConfigs(){
    	$config = new Config($this->path . "config.yml",Config::YAML);
    	if(isset($this->config) == false or is_array($this->config) == false){
    		$config->setAll(array());
    	}else{
    		$config->setAll($this->config);
    	}
    	$config->save();
    }
	
    public function loadItemlist(){
    	$configs = \MenuPlus\ItemList::$items;
    	foreach ($configs as $key => $value) {
    		if(!isset($this->itemlist[$key])){
				$this->itemlist[$key] = $value;
    		}
    	}
    	$this->saveItemlist();
    }

    public function saveItemlist(){
    	$config = new Config($this->path . "itemlist.yml",Config::YAML);
    	if(isset($this->itemlist) == false or is_array($this->itemlist) == false){
    		$config->setAll(array());
    	}else{
    		$config->setAll($this->itemlist);
    	}
    	$config->save();
    }
	
    public function getItemName($item){
		if($item instanceof \pocketmine\item\Item){ // Item
			$str = $item->getID();
			$meta = $item->getDamage();
		}elseif(is_array($item)){  //  [1,0,1]
			$str = $item[0];
			$meta = isset($item[1]) ? $item[1] : 0;
		}else{ //    1:0:1 
			$item = explode(":",$item);
			$str = $item[0];
			$meta = isset($item[1]) ? $item[1] : 0;
		}
		if($meta != 0){
			$str = $str.":".$meta;
		}
		if(isset($this->itemlist[$str])){
			return $this->itemlist[$str];
		}else{
			if($item instanceof \pocketmine\item\Item){ // Item
				return $item->getName();
			}else{
				return \pocketmine\item\Item::get($item[0], $meta, 1)->getName();
			}
		}
    }
	
	public static $menu_info_format = [
	"提示0" => "只有关服后修改有效",
	"菜单名称" => "未定义名称的菜单",
	"是否启用" => true,
	"快捷道具(请勿手动修改)" => "",
	"是否只能通过快捷道具打开(指令无法打开)" => false,
	"是否玩家进服给快捷道具" => true,
	"是否只有OP可打开" => false,
	"提示1" => "黑白名单优先度比OP更高",
	"玩家黑名单开关" => false,
	"玩家黑名单" => ["小杰","李华","张三"],
	"玩家白名单开关" => false,
	"玩家白名单" => ["小杰","李华","张三"],
	"提示2" => "若玩家黑白名单同时打开，只应用白名单",
	"地图黑名单开关" => false,
	"地图黑名单" => ["天空","地底"],
	"地图白名单开关" => false,
	"地图白名单" => ["天空","地底"],
	"提示3" => "若地图黑白名单同时打开，只应用白名单",
	];
	
	public function saveMenusInfo(){
    	$config = new Config($this->path . "menusinfo.yml",Config::YAML);
		foreach(array_keys($this->menus) as $id){
			foreach(self::$menu_info_format as $key => $val){
				if(!isset($this->menusinfo[$id][$key])){
					$this->menusinfo[$id][$key] = $val;
					if(!is_array($val)){
						$this->getServer()->getLogger()->info(self::FORMAT."菜单{$id}的 [$key] 没有设置,已暂时自动改为 [$val]");
					}
				}else{
					if(is_array($val)){
						foreach($val as $key2 => $val2){
							if(!isset($this->menusinfo[$id][$key][$key2])){
								$this->menusinfo[$id][$key][$key2] = $val2;
							}
						}
					}
				}
				if(getType($this->menusinfo[$id][$key]) != getType($val)){
					if(is_numeric($this->menusinfo[$id][$key]) and is_numeric($val)){
						
					}else{
						$this->menusinfo[$id][$key] = $val;
						if(!is_array($val)){
							$this->getServer()->getLogger()->info(self::FORMAT."菜单{$id}的 [$key] 没有设置,已暂时自动改为 [$val]");
						}
					}
				}else{
					if(is_array($val)){
						foreach($val as $key2 => $val2){
							if(getType($this->menusinfo[$id][$key][$key2]) != getType($val2)){
								if(is_numeric($this->menusinfo[$id][$key][$key2]) and is_numeric($val2)){
							
								}else{
									$this->menusinfo[$id][$key][$key2] = $val2;
									if(!is_array($val2)){
										$this->getServer()->getLogger()->info(self::FORMAT."菜单{$id}的 [$key] 没有设置,已暂时自动改为 [$val]");
									}
								}
							}
						}
					}
				}
			}
		}
		$config->setAll($this->menusinfo);
    	$config->save();
	}

	public function testPermission(Player $player, $id){
		if(!isset($this->menus[$id])){
			return "不存在此菜单";
		}
		$result = true;
		$name = strtolower($player->getName());
		$level = strtolower($player->getLevel()->getFolderName());
		$info = $this->menusinfo[$id];
		if($info["是否只能通过快捷道具打开(指令无法打开)"]  == true and isset($info["快捷道具(请勿手动修改)"])){
			$array = explode(":",$info["快捷道具(请勿手动修改)"]);
			if(count($array) !== 3){
				return "此菜单只能通过快捷道具打开,但服主没有设置快捷道具,无法打开此菜单";
			}
			$item = $player->getInventory()->getItemInHand();
			if(!isset($item->getNamedTag()["MenU"]) or $item->getNamedTag()->MenU != $id){
				return "此菜单只能通过快捷道具打开";
			}
		}
		if($info["是否启用"] == false){
			return "此菜单未启用";
		}
		if($info["是否只有OP可打开"] == true and !$player->isOp()){
			$result = "此菜单只有OP可打开";
		}
		if($info["玩家黑名单开关"] == true and in_array($name, $info["玩家黑名单"], false)){
			$result = "你在此菜单中被列入了黑名单";
		}
		if($info["玩家白名单开关"] == true){
			if(in_array($name, $info["玩家白名单"], false)){
				$result = true;
			}else{
				$result = "你没有在此菜单的白名单中";
			}
		}
		if($info["地图黑名单开关"] == true and in_array($level, $info["地图黑名单"], false)){
			$result = "你所在的地图无法打开此菜单";
		}
		if($info["地图白名单开关"] == true and !in_array($level, $info["地图白名单"], false)){
			$result = "你所在的地图无法打开此菜单";
		}
		return $result;
	}
		
	public function getCommandSender(){
		return $this->sender;
	}
	
    public function saveMenus(){
    	$menus = new Config($this->path . "menus.yml",Config::YAML);
		if(is_array($this->menus) and !empty($this->menus)){
			$menus->setAll($this->menus);
			$menus->save();
		}
    }
		
	public function cacheAllMenus(){
		$this->menucache = [];
		foreach($this->menus as $id => $menu){
			$this->menucache[$id] = $this->handleMenudata($menu);
		}
	}
	
	public function cacheMenu($id){
		if(isset($this->menus[$id])){
			$this->menucache[$id] = $this->handleMenudata($this->menus[$id]);
		}
	}
	
	public function getMenuCache($id = null){
		if($id == null){
			return $this->menucache;
		}
		return isset($this->menucache[$id]) ? $this->menucache[$id] : [];
	}

	public function handleHash($id, $data){
		$str = "";
		$cnt = count($data);
		if($cnt <= 4){
			$step = 1;
		}elseif($cnt <= 8){
			$step = 2;
		}elseif($cnt <= 16){
			$step = 3;
		}else{
			$step = 4;
		}
		$str .= (string)$cnt;
		$keys = array_keys($data);
		$values = array_values($data);
		for($i=0; $i<$cnt; $i+=$step){
			if(isset($keys[$i]) and isset($values[$i])){
				$str .= $keys[$i].serialize($values[$i]);
			}
		}
		unset($data);
		$str = substr(hash("md5", $str), 0, 10);
		$str = "Menu".$id.$str;
		return $str;
	}
	
	public function handleMenudata($all){
		$items = [];
		foreach($all as $head => $body){
				$body["key"] = $head;
				$item = new MenuItem(
				$body["item"][0],
				$body["item"][1],
				$body["item"][2],
				$body
				);
				$items[] = $item;
		}
		unset($all);
		return $items;
	}
	
	public function giveTool($id, Player $player){
		if(isset($this->menusinfo[$id]) and $player instanceof Player){
			$inventory = $player->getInventory();
			$item_id = $this->menusinfo[$id]["快捷道具(请勿手动修改)"];
			$array = explode(":",$item_id);
			if(count($array) !== 3){
				return false;
			}
			$item = new Item(intval($array[0]), intval($array[1]), intval($array[2]));
			$nbt=new CompoundTag("", [
			"ench"=>new CompoundTag("ench",[]),
			"display"=> new CompoundTag("display", [
			"Name" => new StringTag("Name", "\n".str_replace(["\n",'\n'], "\n", $this->menusinfo[$id]["菜单名称"]))
			]),
			"MenU" => new StringTag("MenU", (string)$id)
			]);
			$item->setNamedTag($nbt);
			foreach ($inventory->getContents() as $i) {
				if($i->getID() == $item->getID() and $i->getDamage() == $item->getDamage()){
					return false;
				}
			}
			$inventory->addItem($item);
			$player->sendMessage($this->config["消息前缀"]."§6已得到§a[".$this->menusinfo[$id]["菜单名称"]."]§6的打开工具");
			return true;
		}else{
			return false;
		}
	}
	
	public function openMenu(Player $player, $id){
		$name = strtolower($player->getName());
		if($this->isUsingMenu($player)){
			$this->using[$name]["menu"]->close();
		}
		$result = $this->testPermission($player, $id);
		if($result === true){
			$this->using[$name] = [];
			$menu = new Menu($player, $id);
			$menu->open();
			return true;
		}else{
			$player->sendMessage($this->config["消息前缀"]."§2".$result);
			return false;
		}
	}


	public function closeMenu($player){
		$name = strtolower($player->getName());
		if($this->isUsingMenu($player)){
			$this->using[$name]["menu"]->close();
		}
		if($this->isUsingMenu($player)){
			unset($this->using[$name]);
		}
	}
	
	public function isUsingMenu($player){
		if($player instanceof Player){
			$name = strtolower($player->getName());
		}else{
			$name = strtolower($player);
		}
		if(isset($this->using[$name]["menu"]) and $this->using[$name]["menu"] instanceof Menu){
			return true;
		}
		return false;
	}
	
	public function select($player, $packet){
		$name = strtolower($player->getName());
		$slot = $packet->slot;
		$menu = &$this->using[$name]["menu"];
		if($menu->isCold()){
			$menu->update("update");
			return;
		}
		$page = $menu->getPage();
		$item = $menu->getItem($slot + $page * self::SIZE);
		if($slot == self::MAX_SIZE - 3){
			$result = $menu->turnLast();
			unset($this->select[$name]);
			return true;
		}
		if($slot == self::MAX_SIZE - 2){
			$menu->update("update");
			return true;
		}		
		if($slot == self::MAX_SIZE - 1){
			$result = $menu->turnNext();
			unset($this->select[$name]);
			return true;
		}
		if(!isset($this->select[$name])){
			if($this->isMenuItem($item)){
				$this->select[$name]["times"] = 1;
				$this->select[$name]["slot"] = $slot;
			}
			$menu->update("update");
			return true;
		}else{
			unset($this->select[$name]["times"]);
			if($this->isMenuItem($item)){
				$data = $item->getNamedTag()->menudata;
				$menudata = [];
				foreach($data as $k => $v){
					$menudata[$k] = $v;
				}
				foreach(self::DATA_FORMAT as $k => $v){
					if(!isset($menudata[$k])){
						$menudata[$k] = $v;
					}
				}
			}
			if($slot != $this->select[$name]["slot"]){
				$this->select[$name]["times"] = 1;
				$this->select[$name]["slot"] = $slot;
			}else{
				if($this->isMenuItem($item)){
					$money = $menudata["price"];
					$plugin = Server::getInstance()->getPluginManager()->getPlugin("EconomyAPI");
						$allow = false;
						if($money != 0 and is_numeric($money)){
							if($plugin != null){
								if($money > $plugin->myMoney($player)){
									$player->sendPopup($this->config["消息前缀"]."你的金钱不够,无法使用此菜单按钮");
									$allow = false;
								}else{
									if($money < 0){
										$plugin->addMoney($player->getName(), abs($money));
									}else{
										$plugin->reduceMoney($player->getName(), $money);
									}
									$allow = true;									
								}
							}else{
								$player->sendPopup($this->config["消息前缀"]."§c当前服务器没有安装§a[EconomyAPI]插件,无法使用此涉及金钱的按钮");
							}
						}else{
							$allow = true;
						}
						if(isset($menudata["cost_item"]) and $allow == true){
							$cost_item = str_replace(" ","",$menudata["cost_item"]);
							$items = explode("++",$cost_item);
							foreach($items as $array){
								$cost_item = explode(":",$array);
								if(count($cost_item) == 3){
									if($player->isCreative() or $player->getGamemode() == 1){
										$allow= false;
										$player->sendPopup($this->config["消息前缀"]."创造模式无法使用消耗物品的按钮");
										break;	
									}else{
										if(!$player->getInventory()->contains(Item::get($cost_item[0],$cost_item[1],$cost_item[2]))){
											$allow = false;
											break;	
										}
									}
								}
							}
							if($allow == false){
								if($money != 0 and is_numeric($money)){
									if($plugin != null){
										if($money < 0){
											$plugin->reduceMoney($player->getName(), abs($money));
										}else{
											$plugin->addMoney($player->getName(), abs($money));
										}
									}
								}									
							}else{
								foreach($items as $array){
									$cost_item = explode(":",$array);
									if(count($cost_item) == 3){
										$player->getInventory()->removeItem(Item::get($cost_item[0],$cost_item[1],$cost_item[2]));
									}
								}
							}
						}
						if($allow == true){
							if(isset($menudata["shop"])){
								if($menudata["shop"] == true){
									$thing = Item::get($item->getID(),$item->getDamage(),$item->getCount());
									if(!$player->getInventory()->canAddItem($thing)){
										if($money != 0 and is_numeric($money)){
											if($plugin != null){
												if($money < 0){
													$plugin->reduceMoney($player->getName(), abs($money));
												}else{
													$plugin->addMoney($player->getName(), abs($money));
												}
												$player->sendPopup($this->config["消息前缀"]."§a获得物品失败,背包已满");
												$allow = false;
											}
										}
									}else{
										$player->getInventory()->addItem($thing);
									}
								}
							}
							if($allow == true){
								if(isset($menudata["give_item"])){
									$items = explode("++",str_replace(" ","",$menudata["give_item"]));
									foreach($items as $array){
										$give_item = explode(":",$array);
										if(count($give_item) == 3){
											$player->getInventory()->addItem(Item::get($give_item[0],$give_item[1],$give_item[2]));
										}
									}
								}
								if($menudata["teleport"] != null or !empty($menudata["commands"])){
									$this->select[$name]["teleport"] = $menudata["teleport"];
									$this->select[$name]["commands"] = $menudata["commands"];
									if(isset($this->using[$name]["menu"]) and $this->using[$name]["menu"] instanceof Menu){
										$this->using[$name]["menu"]->update("please");
										return;
									}
								}else{
									$this->select[$name]["teleport"] = null;
									$this->select[$name]["commands"] = [];
									if($menudata["multilevel"] == true){
										$newmenudata = [];
										$f = self::DATA_FORMAT;
										foreach($menudata as $key => $data){
											if(is_array($data) and !isset($f[$key])){
												$newmenudata[$key] = $data;
											}
										}
										$ser = $this->handleHash($menu->getID(),$newmenudata);
										if($this->getMenuCache($ser) != null){
											$this->using[$name]["menu"]->setItems($this->getMenuCache($ser));
										}else{
											$this->menucache[$ser] = $this->handleMenudata($newmenudata);
											$this->using[$name]["menu"]->setItems($this->getMenuCache($ser));
										}
										$this->using[$name]["menu"]->setPage(0);
										$this->using[$name]["menu"]->addHistory($menudata["key"]);
										$menu->update("open");
									}else{
										if($this->config["使用按钮反馈的开关"]){
											$menu->update("ok");
										}
									}
								}
							}
						}
						if($allow == false and $this->config["使用按钮反馈的开关"]){
							$menu->update("no");
						}
					}
					unset($this->select[$name]);
				}
				if(isset($this->using[$name]["menu"])){
					$menu->update("update");
				}
			}
	}
	
	public function configExists($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
        return file_exists($this->path . "players/" . strtolower($player) . ".yml");
    }
	
    public function getPlayerConfig($player){
		if($player instanceof Player){
			$player = $player->getName();
		}
		$player = strtolower($player);
		$array = array(
                "Name" => $player."",
				"Op" => null,
				);
        if(!(file_exists($this->path . "players/" . $player . ".yml")))
        {
            return new Config($this->path . "players/" . $player . ".yml", Config::YAML, $array);
        }else{
            $config = new Config($this->path . "players/" . $player . ".yml", Config::YAML, array());
			$all = $config->getAll();
			foreach($array as $key => $val){
				if(!isset($all[$key])){
					$all[$key] = $val;
				}
			}
			$config->setAll($all);
			$config->save();
			return $config;
		}
    }
	
	public function isMenuItem($entity){
		if($entity instanceof MenuItem){
			return true;
		}
		if($entity instanceof \pocketmine\item\Item){
			if(isset($entity->getNamedTag()["menudata"])){
				return true;
			}
		}
		return false;
	}	
	
	public function set_start(Player $player, $id = 0){
		if(!isset($this->menus[$id])){
			$player->sendMessage($this->config["消息前缀"]."不存在此id的菜单,无法设置(/menu add [id]来创建菜单)");
			return false;
		}
		if($this->setter instanceof Player){
			if($this->setter == $player){
				$this->set_stop($player);
				$player->sendMessage($this->config["消息前缀"]."已退出设置模式");
			}else{
				$player->sendMessage($this->config["消息前缀"]." [".$this->setter->getName()."]已经在设置菜单了,请稍后再试");
			}
		}else{
				$x = (int)$player->x;
				$y = (int)$player->y;
				$z = (int)$player->z;
				
				$pos1 = new Position($x - 2,$y - 1,$z - 3,$player->level);
				$pos2 = new Position($x + 3,$y - 1,$z + 4,$player->level);
				$pos3 = new Position($x + 3,$y + 1,$z + 4,$player->level);
				$pos4 = new Position($x - 1,$y -1,$z - 2,$player->level);
				$pos5 = new Position($x + 2,$y -1,$z + 3,$player->level);					
				
				$this->W_cut($pos1, $pos3);
				$this->W_fill($pos1, $pos2, Block::get(35, 15));
				$this->W_fill($pos4, $pos5, Block::get(20), true);
				unset($pos1,$pos2,$pos3,$pos4,$pos5);
				$this->setter = $player;
				$this->set["menu"] = new Menu(null, $id);
				$this->set["id"] = $id;
				$player->getInventory()->clearAll();
				$player->getInventory()->setItem(0, Item::get(292,0,1));
				$player->getInventory()->setItem(1, Item::get(258,0,1));
				$player->getInventory()->setItem(2, Item::get(256,0,1));
				$player->getInventory()->setItem(3, Item::get(283,0,1));
				$player->getInventory()->setItem(4, Item::get(285,0,1));
				$this->W_update();
				$player->sendMessage($this->config["消息前缀"]." 已进入设置模式，输入/menu ok退出设置模式");
		}
	}
	
	public function set_stop(Player $player){
		if($this->setter instanceof Player and ($this->setter == $player or $player->getName() == $this->setter->getName())){
			$this->menus[$this->set["id"]] == $this->set["menu"]->getMenudata();
			$this->saveMenus();
			$this->cacheMenu($this->set["id"]);
			foreach($this->menucache as $k => $v){
				if(strstr($k, "Menu".$this->set["id"]) != false){
					unset($this->menucache[$k]);
				}
			}
			$this->W_paste();
			$this->W_update(true);
			$this->setter = null;
			$this->set = [];
		}
	}
	
	public function handleSet($type,$block,$player){
		if($this->isSetting($player) and $block instanceof Block){
			if(isset($this->set["slots"][$block->x.":".$block->y.":".$block->z])){
				$slot = $this->set["slots"][$block->x.":".$block->y.":".$block->z];
			}else{
				$slot = null;
			}
			if($slot !== null){
				$item = $this->set["menu"]->getItem($slot + $this->set["menu"]->getPage() * self::SIZE);
				if($this->isMenuItem($item)){
					$data = $item->getNamedTag()->menudata;
				}else{
					$data = self::DATA_FORMAT;
				}
				$menudata = [];
				foreach($data as $k => $v){
					$menudata[$k] = $v;
				}
				if($menudata["multilevel"]){
					$multilevel = true;
				}else{
					$multilevel = false;
				}
				switch($type){
					case "look":
					$str = "§b--§f<----------->§b--§f";
					if($this->isMenuItem($item)){
						$str .= "\n§6物品 ID:".$item->getID()." 特殊值:".$item->getDamage()." 数量:".$item->getCount();
						if($multilevel){
							$str .= "\n§e包含多个按钮";
						}else{
							$str .= "\n§7单一按钮";
						}
						$str .= "\n§2显示昵称§a:§f".$menudata["nametag"];
						$str .= "\n§2触发指令§a:§f".implode("++",$menudata["commands"]);
						$str .= "\n§2传送坐标§a:§f".$menudata["teleport"];
						$str .= "\n§2消耗金钱§a:§f".$menudata["price"];
						$str .= "\n§2给予物品§a:§f".$menudata["give_item"];
						$str .= "\n§2消耗物品§a:§f".$menudata["cost_item"];
					}else{
						$str .= "\n§7再次点击此未设置的按钮  进行设置";
					}
					$str .= "\n§b--§f<----------->§b--§f";
					$player->sendMessage($str);
					break;
					
					
					case "delete":
					if($this->isMenuItem($item)){
						$body = $this->set["menu"]->getMenudata();
						$histories = $this->set["menu"]->getHistories();
						$path = "";
						foreach($histories as $h){
							$path .= '["'.$h.'"]';
						}
						$data = $item->getNamedTag()->menudata;
						$menudata = [];
						foreach($data as $k => $v){
							$menudata[$k] = $v;
						}
						if(is_numeric($menudata["key"])){
							$str = 'unset($body'.$path.'["'.$menudata["key"].'"]'.');';
						}else{
							$str = 'unset($body'.$path.'['.$menudata["key"].']'.');';
						}
						eval($str);
						$this->set["menu"]->setMenudata($body);
						$this->set["menu"]->setItems($this->handleMenudata($body));
						$this->set["menu"]->setPage(0);
						$this->set["menu"]->setHistories([]);
						$this->menus[$this->set["id"]] = $body;
						$player->sendMessage($this->config["消息前缀"]."删除成功");
						$this->W_update();
					}else{
						$player->sendMessage($this->config["消息前缀"]."删除失败 对象不是一个按钮");
					}
					break;
				

					case "enter":
					if($multilevel){
						$newmenudata = [];
						$f = self::DATA_FORMAT;
						foreach($menudata as $key => $data){
							if(is_array($data) and !isset($f[$key])){
								$newmenudata[$key] = $data;
							}
						}
						$this->set["menu"]->setItems($this->handleMenudata($newmenudata));
						$this->set["menu"]->setPage(0);
						$this->set["menu"]->addHistory($menudata["key"]);
						$this->W_update();
						$str = $this->config["消息前缀"]."已进入多级菜单 [".$menudata["key"]."]";
					}else{
						$str = $this->config["消息前缀"]."此个菜单按钮不是多级菜单,无法进入";
					}
					$player->sendMessage($str);
					break;
					
					case "last":
						if($this->set["menu"]->getPage() >= 1){
							$this->set["menu"]->setPage($this->set["menu"]->getPage() - 1);
							$this->W_update();
						}else{
							$histories = $this->set["menu"]->getHistories();
							if(!empty($histories)){
								array_pop($histories);
								if(empty($histories)){
									$str = '$newmenudata = $this->menus['.$this->set["id"].'];';
								}else{
									$path = "";
									foreach($histories as $h){
										$path .= '["'.$h.'"]';
									}
									$str = '$newmenudata = $this->menus['.$this->set["id"].']'.$path.';';
								}
								eval($str);
								$keys = array_keys(self::DATA_FORMAT);
								foreach($newmenudata as $k=>$v){
									if(in_array($k,$keys) and !is_numeric($k)){
										unset($newmenudata[$k]);
									}
								}
								$this->set["menu"]->setItems($this->handleMenudata($newmenudata));
								$this->set["menu"]->setPage(0);
								$this->set["menu"]->setHistories($histories);
								$this->W_update();
								$str = $this->config["消息前缀"]."已回到上一级菜单";
							}else{
								$str = $this->config["消息前缀"]."已到达最上一级菜单,无法继续向上";
							}
							$player->sendMessage($str);
						}
					break;
					
					case "next":
						$this->set["menu"]->setPage($this->set["menu"]->getPage() + 1);
						$this->W_update();
					break;
					
					case "set":
						$str = $this->config["消息前缀"]."请在聊天框输入内容进行设置\n";
						$str .= $this->config["消息前缀"]."接下来请输入菜单按钮的§e物品ID";
						$player->sendMessage($str);
						$this->set["set_level"] = 0;
						$this->set["set_key"] = isset($menudata["key"]) ? $menudata["key"] : null;
					break;
					
					default:
					break;
				}
			}
		}
	}
	
	public function isSetting(Player $player){
		if($this->setter == $player){
			return true;
		}
		return false;
	}
	
	public function W_cut($pos1, $pos2){
        $level = $pos1->level;
        $blocks = array();
        $startX = min($pos1->x, $pos2->x);
        $endX = max($pos1->x, $pos2->x);
        $startY = min($pos1->y, $pos2->y);
        $endY = max($pos1->y, $pos2->y);
        $startZ = min($pos1->z, $pos2->z);
        $endZ = max($pos1->z, $pos2->z);
        for ($x = $startX; $x <= $endX; ++$x) {
            for ($y = $startY; $y <= $endY; ++$y) {
                for ($z = $startZ; $z <= $endZ; ++$z) {
					$pos = new Vector3($x, $y, $z);
                    $blocks[$x][$y][$z] = $level->getBlock($pos);
					$level->setBlock($pos, new \pocketmine\block\Air(), true, false);
                    unset($b);
                }
            }
        }
		$this->set["blocks"] = $blocks;
		$this->set["level"] = $level;
		unset($blocks);
	}
	
    public function W_paste(){
		if(isset($this->set["level"]) and isset($this->set["blocks"])){
        $level = $this->set["level"];
        foreach ($this->set["blocks"] as $x => $i) {
            foreach ($i as $y => $j) {
                foreach ($j as $z => $block) {
                    $level->setBlock(new Vector3($x, $y, $z), $block, false, true);
                    unset($block);
                }
            }
        }
		$this->set["blocks"] = [];			
		}
    }

	public function W_fill($pos1, $pos2, $block, $AsSlot = false){
        $level = $pos1->level;
		$this->set["slots"] = ["0:0:0" => 0];
        $startX = min($pos1->x, $pos2->x);
        $endX = max($pos1->x, $pos2->x);
        $startY = min($pos1->y, $pos2->y);
        $endY = max($pos1->y, $pos2->y);
        $startZ = min($pos1->z, $pos2->z);
        $endZ = max($pos1->z, $pos2->z);
		$i = 0;
        for ($x = $endX; $x >= $startX; $x--) {
            for ($y = $startY; $y <= $endY; $y++) {
                for ($z = $startZ; $z <= $endZ; $z++) {
					$v3 = new Vector3($x, $y, $z);
					$level->setBlock($v3, $block);
					if($AsSlot){
						 $this->set["slots"]["$x:$y:$z"] = $i;
						 $i += 1;
					}
                }
            }
        }
	}
	
	
	public function W_update($force = false){
		$level = $this->set["level"];
		$world = $level->getFolderName();
		if(isset($this->set["cases"])){
			foreach($this->set["cases"][$world] as $cid => $e){
				$pk = $this->getPacketObj("RemoveEntityPacket");
				if($pk != false){
					$pk->eid = $this->set["cases"][$world][$cid]["eid"];
					foreach ($level->getPlayers() as $pl) {
						$pl->directDataPacket($pk);
					}					
				}

			}
		}
		unset($this->set["cases"]);
		if(!$force){
			$page = $this->set["menu"]->getPage();
			$c1 = $page*MenuPlus::SIZE;
			$c2 = ($page + 1)*MenuPlus::SIZE - 1;
			$items = array_slice($this->set["menu"]->getItems(), $c1, $c2 + 1, true);
			$a = [];
			foreach($items as $slot => $item){
				$a[$slot-$page*MenuPlus::SIZE] = $item;
			}
			foreach($this->set["slots"] as $pos => $slot){
				if(isset($a[$slot])){
					$item = $a[$slot];
				}else{
					$item = 0;
				}
				if($this->isMenuItem($item)){
					$this->addItemCase($level,$pos,implode(":",[$item->getId(),$item->getDamage()]),$item->getCount());
				}
			}
		}
	}
	
	public function addItemCase(Level $level,$cid, $idmeta, $count){
		$world = $level->getFolderName();
		if(isset($this->set["cases"][$world][$cid])){
			$this->removeItemCase($level,$cid,$level->getPlayers());
		}
		$this->set["cases"][$world][$cid] = ["item"=>$idmeta,"count"=> $count];
		$this->sendItemCase($level,$cid,$level->getPlayers());
		return true;
	}

	public function removeItemCase(Level $level,$cid,array $players){
		$world = $level->getFolderName();
		if (!isset($this->set["cases"][$world][$cid]["eid"])){
			return;
		}
		$pk = $this->getPacketObj("RemoveEntityPacket");
		if($pk != false){
			$pk->eid = $this->set["cases"][$world][$cid]["eid"];
			foreach ($players as $pl) {
				$pl->directDataPacket($pk);
			}			
		}

	}

	public function sendItemCase(Level $level,$cid,array $players){
		$world = $level->getFolderName();
		$pos = explode(":",$cid);
		if(!isset($this->set["cases"][$world][$cid]["eid"])){
			$this->set["cases"][$world][$cid]["eid"] = \pocketmine\entity\Entity::$entityCount++;
		}
		$item = Item::fromString($this->set["cases"][$world][$cid]["item"]);
		$item->setCount($this->set["cases"][$world][$cid]["count"]);
		$pk = $this->getPacketObj("AddItemEntityPacket");
		if($pk != false){
			$pk->eid = $this->set["cases"][$world][$cid]["eid"];
			$pk->item = $item;
			$pk->x = $pos[0] + 0.5;
			$pk->y = $pos[1] + 1;
			$pk->z = $pos[2] + 0.5;
			$pk->yaw = 0;
			$pk->pitch = 0;
			$pk->roll = 0;
			foreach ($players as $pl) {
				$pl->directDataPacket($pk);
			}
		}

	}
	
	public function cachePleaseItems(){
		$item = Item::get(349,3,1);
		$item->setCustomName("§9需要§e关闭§9菜单哦");
		$items = [];
		foreach([0,2,4,6,8,10,12,14,16,18,20,22] as $slot){
			$items[$slot] = $item;
		}
		$this->pleaseitems = $items;
		unset($items);
	}
	
	public function cacheOKItems(){
		$item = Item::get(35,5,1);
		$items = [];
		foreach([5,10,15,20,19,12] as $slot){
			$items[$slot] = $item;
		}
		$this->okitems = $items;
		unset($items);
	}
	
	public function cacheNOItems(){
		$item = Item::get(35,14,1);
		$items = [];
		foreach([1, 4, 8, 9, 14, 15, 19, 22] as $slot){
			$items[$slot] = $item;
		}
		$this->noitems = $items;
		unset($items);
	}
	
	public function getPleaseItems(){
		return $this->pleaseitems;
	}
	
	public function getOKItems(){
		return $this->okitems;
	}
	
	public function getNOItems(){
		return $this->noitems;
	}	
	
	public $commands = [
		"menu" => "\\MenuPlus\\command\\MenuCommand"
	];
		
	
	public function registerCommands(){
		$map = $this->getServer()->getCommandMap();
		foreach($this->commands as $cmd => $class){
			$map->register($cmd, new $class($this));
		}
	}	
	
}
?>