<?php

namespace MenuPlus\task;

use pocketmine\scheduler\PluginTask;
use MenuPlus\MenuPlus;

class ColdTask extends PluginTask{
	
	public function __construct(MenuPlus $plugin,$menu){
		parent::__construct($plugin);
		$this->menu = $menu;
	}

	public function onRun($currentTick){
		$this->menu->open();
		$this->menu->cold = false;
	}
	
}