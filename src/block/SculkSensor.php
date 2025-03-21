<?php

/*
 *
 *  ____            _        _   __  __ _                  __  __ ____
 * |  _ \ ___   ___| | _____| |_|  \/  (_)_ __   ___      |  \/  |  _ \
 * | |_) / _ \ / __| |/ / _ \ __| |\/| | | '_ \ / _ \_____| |\/| | |_) |
 * |  __/ (_) | (__|   <  __/ |_| |  | | | | | |  __/_____| |  | |  __/
 * |_|   \___/ \___|_|\_\___|\__|_|  |_|_|_| |_|\___|     |_|  |_|_|
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * @author PocketMine Team
 * @link http://www.pocketmine.net/
 *
 *
 */

declare(strict_types=1);

namespace pocketmine\block;

use pocketmine\block\utils\SupportType;
use pocketmine\data\runtime\RuntimeDataDescriber;

class SculkSensor extends Transparent{
	protected int $phase = 0;

	public  function getPhase() : int{
		return $this->phase;
	}

	public  function setPhase(int $phase) : self {
		if($phase < 0 || $phase > 2){
			throw new \InvalidArgumentException("Phase must be between 0 and 2, got $phase");
		}
		$this->phase = $phase;

		return $this;
	}

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void{
		$w->boundedIntAuto(0, 2, $this->phase);
	}

	public function getSupportType(int $facing) : SupportType{
		return SupportType::NONE;
	}

	public function getLightLevel() : int{
		return 1;
	}
}
