<?php

/*
 *
 *      _    _ _
 *     / \  | | |_ __ _ _   _
 *    / _ \ | | __/ _` | | | |
 *   / ___ \| | || (_| | |_| |
 *  /_/   \_\_|\__\__,_|\__, |
 *                       |___/
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Original work by the PocketMine Team.
 * https://www.pocketmine.net/
 *
 * @author Altay Team
 * @link https://github.com/altayofficial
 */

declare(strict_types=1);

namespace pocketmine\crafting;

use pocketmine\item\Armor;
use pocketmine\item\ArmorMaterial;
use pocketmine\item\Item;
use function spl_object_id;

class ArmorRecipeIngredient implements RecipeIngredient{
	public function __construct(
		private ArmorMaterial $material
	){
	}

	public function getMaterial() : ArmorMaterial{ return $this->material; }

	public function accepts(Item $item) : bool{
		if($item->getCount() < 1){
			return false;
		}

		return $item instanceof Armor && $item->getMaterial() === $this->material;
	}

	public function __toString() : string{
		return "ArmorRecipeIngredient(ArmorMaterial@" . spl_object_id($this->material) . ")";
	}
}
