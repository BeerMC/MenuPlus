<?php

namespace MenuPlus\command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\utils\TextFormat;
use pocketmine\Player;
use pocketmine\Server;
use MenuPlus\MenuPlus;
use pocketmine\nbt\NBT;
use pocketmine\nbt\tag\DoubleTag;
use pocketmine\nbt\tag\ListTag;
use pocketmine\nbt\tag\FloatTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\item\Item;
class MenuCommand extends Command{
	
	private $plugin;

	public function __construct(MenuPlus $plugin){
		parent::__construct("menu", "description", "usage");

		$this->setPermission("MenuPlus.command.menu");

		$this->plugin = $plugin;
	}

	public function execute(CommandSender $sender, $label, array $args){
		if(!$this->plugin->isEnabled()) return false;
		if(!$this->testPermission($sender)){
			return false;
		}
		if($args == null){
			$this->sendCommandHelp($sender);
			return true;
		}
					switch($args[0]){
						case "set":
						case "start":
						case "设置":
						case "修改":
						if(!$sender instanceof Player){
							$sender->sendMessage("你不是玩家!");
							return true;
						}						
						if($sender->isOp()){
							$id = "";
							foreach($args as $k=>$v){
								if($k >= 1){
									$id .= " ".$v;
								}
							}
							$id = trim($id);
							if(isset($args[1])){
								$this->plugin->set_start($sender, $id);
							}else{
								$sender->sendMessage($this->plugin->config["消息前缀"]."/menu set [id]");
							}
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
						
						case "ok":
						case "stop":
						case "结束":
						case "完成":
						if(!$sender instanceof Player){
							$sender->sendMessage("You are not a player!");
							return true;
						}						
						if($sender->isOp()){
							$this->plugin->set_stop($sender);
							$sender->sendMessage($this->plugin->config["消息前缀"]."已退出设置模式");
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}						
						return true;
						
						case "add":
						case "create":
						case "cre":
						if($sender->isOp()){
							$id = "";
							foreach($args as $k=>$v){
								if($k >= 1){
									$id .= " ".$v;
								}
							}
							$id = trim($id);
							if(isset($args[1])){
								if(isset($this->plugin->menus[$id])){
									$sender->sendMessage($this->plugin->config["消息前缀"]."已存在此id的菜单");
								}else{
									$this->plugin->menus[$id.""] = [];
									$this->plugin->menusinfo[$id.""] = [];
									$this->plugin->saveMenus();
									$this->plugin->saveMenusinfo();
									$sender->sendMessage($this->plugin->config["消息前缀"]."新建成功,赶紧开始设置吧");
								}
							}else{
								$sender->sendMessage($this->plugin->config["消息前缀"]."/menu add [id]");
							}
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
						
						case "del":
						case "delete":
						if($sender->isOp()){
							$id = "";
							foreach($args as $k=>$v){
								if($k >= 1){
									$id .= " ".$v;
								}
							}
							$id = trim($id);
							if(isset($args[1])){
								if(isset($this->plugin->menus[$id])){
									if(isset($this->plugin->set["id"]) and $this->plugin->set["id"] == $id){
										$sender->sendMessage($this->plugin->config["消息前缀"]."此菜单正在设置状态,无法删除");		
									}else{
										try{
											unset($this->plugin->menus[$id]);
											unset($this->plugin->menusinfo[$id]);
											$this->plugin->saveMenus();
											$this->plugin->saveMenusinfo();											
											$sender->sendMessage($this->plugin->config["消息前缀"]."删除成功");	
										}catch(\Exception $e){
											
										}
									}
								}else{
									$sender->sendMessage($this->plugin->config["消息前缀"]."没有此id的菜单");
								}
							}else{
								$sender->sendMessage($this->plugin->config["消息前缀"]."/menu del [id]");
							}
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
						case "list":
						if($sender->isOp()){
							$str = $this->plugin->config["消息前缀"] . "   -=菜单列表=-  \n";
							foreach($this->plugin->menus as $id => $menudata){
								$str .= "§7[ID]=>§3" . $id . "  ";
							}
							$sender->sendMessage($str);
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
						case "update":
						if(!$sender->isOp()){
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
							return true;
						}
						$this->plugin->update();
						return true;
						
						case "open":	
						if(!$sender->isOp()){
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
							return true;
						}
						if(!isset($args[2])){
							$sender->sendMessage($this->plugin->config["消息前缀"]."/menu open [player] [id]");
						}else{
							$player = $this->plugin->getServer()->getPlayerExact($args[1]);
							if($player instanceof Player){
								$id = "";
								foreach($args as $k=>$v){
									if($k >= 2){
										$id .= " ".$v;
									}
								}
								$id = trim($id);
								if(!isset($this->plugin->menus[$id])){
									$sender->sendMessage($this->plugin->config["消息前缀"]."id 不存在");
									$player->sendMessage($this->plugin->config["消息前缀"]."id 不存在");
								}else{
									$this->plugin->openMenu($player, $id);
								}
							}
						}
						return true;
						
						case "close":
						if(!$sender->isOp()){
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
							return true;
						}
						if(!isset($args[1])){
							$sender->sendMessage($this->plugin->config["消息前缀"]."/menu open [player]");
						}else{
							$player = $this->plugin->getServer()->getPlayerExact($args[1]);
							if($player instanceof Player){
								$this->plugin->closeMenu($player);
							}
						}
						return true;
						
						case "givetool":
						if($sender->isOp()){
							if(count($args) < 2){
								$sender->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  /menu givetool [菜单id] [玩家]");
								return true;
							}
							if(isset($args[2])){
								$player = $args[2];
								if(($player = $this->plugin->getServer()->getPlayerExact($player)) instanceof Player){
									$this->plugin->giveTool($args[1], $player);
								}
							}else{
								if(!$sender instanceof Player){
									$sender->sendMessage("你不是玩家!");
									return true;
								}
								$this->plugin->giveTool($args[1], $sender);
							}
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
						case "settool":
						if($sender->isOp()){
							$id = $args[1];
							$item_id = $args[2];
							if(isset($this->plugin->menusinfo[$id])){
								$item_id = str_replace(" ","",$item_id);
								$array = explode(":",$item_id);
								if(count($array) !== 3){
									$sender->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  /menu settool 菜单id 物品ID:物品特殊值:物品数量");
									return;
								}
								foreach($array as $k => $v){
									if(!is_numeric($v)){
										$sender->sendMessage($this->plugin->config["消息前缀"]."输入错误,格式为  /menu settool id 物品ID:物品特殊值:物品数量");
										return;
									}
								}
								$this->plugin->menusinfo[$id]["快捷道具(请勿手动修改)"] = $item_id;
								$sender->sendMessage($this->plugin->config["消息前缀"]."成功将{$id}号菜单的快捷道具ID设置为{$item_id}");
							}else{
								$sender->sendMessage($this->plugin->config["消息前缀"]."不存在此ID的菜单");
							}
						}else{
							$sender->sendMessage($this->plugin->config["消息前缀"]."无权限");
						}
						return true;
						
				/*	case "w":
						if ($sender instanceof Player){
							if(isset($args[1])){
								$str = "";
								foreach($args as $key => $value){
									if($key >= 1)
									$str .= " ".$value;
								}
								$str = substr($str,1);
								if ($this->plugin->getServer()->isLevelLoaded($str)) {  //如果这个世界已加载
									//$sender->sendMessage("[SWorld] 传送中...");
									$sender->teleport(Server::getInstance()->getLevelByName($str)->getSafeSpawn());
									$sender->sendMessage("§5[§6世界系统§5] §2传送至世界:$str .... §a√");
								}else{
									$sender->sendMessage("§5[§6世界系统§5] §2世界 ".$str." 不存在 §cX");
								}
							}else{
								$sender->sendMessage("§5[§6世界系统§5] §2 请输入世界名");
							}
						}else{
							$sender->sendMessage("§5[§6世界系统§5] §2只能在游戏中使用这个命令");
						}
					break;						
						*/
						default:
							if(!$sender instanceof Player){
								$sender->sendMessage("你不是玩家!");
								return true;
							}						
							$id = "";
							foreach($args as $k=>$v){
								$id .= " ".$v;
							}
							$id = trim($id);
							$this->plugin->openMenu($sender, $id);
						return true;
					}
	}

	public function sendCommandHelp($sender){
		$sender->sendMessage($this->plugin->config["消息前缀"]."§d/menu §6[菜单ID] §7打开一个菜单");
		if($sender->isOp()){
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu open §a[玩家名] §6[菜单ID] §7强制对玩家打开菜单");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu close §a[玩家名]  §7强制对玩家关闭菜单");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu givetool §6[菜单ID] §a[玩家名] §7给予§a快捷开启某菜单§7道具");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu settool §6[菜单ID] §a[物品ID] §7设置§a快捷开启某菜单§7道具");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu add §6[菜单ID] §7添加一个菜单");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu set §6[菜单ID] §7设置一个菜单");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu del §6[菜单ID] §7删除一个菜单");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c/menu list §7查询菜单列表");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c更多设置请进后台修改§amenusinfo.yml§c与§aconfig.yml");
			$sender->sendMessage($this->plugin->config["消息前缀"]."§c没有一定英语基础和菜单使用经验的话,不不不要碰§2menus.yml");
		}
	}
}
?>