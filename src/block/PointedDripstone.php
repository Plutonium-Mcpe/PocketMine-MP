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

use pocketmine\block\utils\DripstoneThickness;
use pocketmine\block\utils\HangingTrait;
use pocketmine\block\utils\NoneSupportTrait;
use pocketmine\data\runtime\RuntimeDataDescriber;
use pocketmine\item\Item;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class PointedDripstone extends Transparent{
	use HangingTrait {
		describeBlockOnlyState as describeHangingState;
	}
	use NoneSupportTrait;

	protected DripstoneThickness $thickness = DripstoneThickness::TIP;

	protected function describeBlockOnlyState(RuntimeDataDescriber $w) : void
	{
		$this->describeHangingState($w);
		$w->enum($this->thickness);
	}

	public function getThickness() : DripstoneThickness
	{
		return $this->thickness;
	}

	public function setThickness(DripstoneThickness $thickness) : self
	{
		$this->thickness = $thickness;
		return $this;
	}

	public function onInteract(Item $item, int $face, Vector3 $clickVector, ?Player $player = null, array &$returnedItems = []) : bool
	{
		$this->position->getWorld()->setBlock($this->position, $this->setThickness(match ($this->getThickness()) {
			DripstoneThickness::TIP => DripstoneThickness::MERGE,
			DripstoneThickness::MERGE => DripstoneThickness::FRUSTUM,
			DripstoneThickness::FRUSTUM => DripstoneThickness::MIDDLE,
			DripstoneThickness::MIDDLE => DripstoneThickness::BASE,
			DripstoneThickness::BASE => DripstoneThickness::TIP,
		}));
		return true;
	}
}
