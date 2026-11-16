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

use pocketmine\item\Item;
use pocketmine\world\format\io\GlobalItemDataHandlers;

/**
 * Represent a recipe that repair an item with a material in an anvil.
 */
class ItemSelfCombineRecipe extends ItemCombineRecipe{
	/**
	 * @param RecipeIngredient $target The item that will be concerned by the combinaison.
	 *                                 The input and material have to be accepted by this ingredient to be able to combine them.
	 */
	public function __construct(
		private RecipeIngredient $target
	){
	}

	protected function validate(Item $input, Item $material) : bool{
		if(!$this->target->accepts($input) || !$this->target->accepts($material)){
			return false;
		}

		// Self-combining requires both items to be of the same type (e.g. a diamond sword cannot be combined with an
		// iron pickaxe), matching vanilla behaviour.
		$serializer = GlobalItemDataHandlers::getSerializer();
		return $serializer->serializeType($input)->getName() === $serializer->serializeType($material)->getName();
	}
}
