<?php

/**
* Ultima PHP - OpenSource Ultima Online Server written in PHP
* Version: 0.1 - Pre Alpha
*/

class DoubleAxe extends TypeNormal {
	public function build() {
		$this->name = "double axe";
		$this->graphic = 0xF4B;
		$this->type = "";
		$this->flags = 0x00;
		$this->value = 0;
		$this->amount = 1;
		$this->color = 0;
		$this->aosstrengthreq = 45;
		$this->aosmindamage = 15;
		$this->aosmaxdamage = 17;
		$this->aosspeed = 33;
		$this->mlspeed = 3;
		$this->oldstrengthreq = 45;
		$this->oldmindamage = 5;
		$this->oldspeed = 37;
		$this->defhitsound = 0;
		$this->defmisssound = 0;
		$this->hits = 31;
		$this->maxHits = 110;
		$this->weight = 8.0;

}}
?>
