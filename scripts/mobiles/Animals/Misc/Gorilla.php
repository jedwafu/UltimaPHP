<?php

/**
* Ultima PHP - OpenSource Ultima Online Server written in PHP
* Version: 0.1 - Pre Alpha
*/

class Gorilla extends Mobile {
	public function summon() {
		$this->name = "a gorilla";
		$this->body = 0;
		$this->type = "";
		$this->flags = 0x00;
		$this->color = 0x00;
		$this->basesoundid = 0x9E;
		$this->str = rand(53, 95);
		$this->dex = rand(36, 55);
		$this->int = rand(36, 60);
		$this->maxhits = rand(38, 51);
		$this->hits = $this->maxhits;
		$this->damage = 4;
		$this->damageMax = 10;
		$this->resist_physical = rand(20, 25);
		$this->resist_fire = rand(5, 10);
		$this->resist_cold = rand(10, 15);
		$this->resist_poison = 0;
		$this->resist_energy = 0;
		$this->karma = 0;
		$this->fame = 450;
		$this->virtualarmor = 20;

}}
?>
