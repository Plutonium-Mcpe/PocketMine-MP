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

namespace pocketmine\crafting;

use pocketmine\item\Durable;
use pocketmine\item\Item;

/**
 * Recipe ingredient that matches any durable item (tools, weapons, armour), regardless of whether it is a vanilla or a
 * custom item. This allows anvil recipes (repair / enchantment combining) to work generically for every durable item,
 * including plugin-defined custom items.
 */
final class DurableRecipeIngredient implements RecipeIngredient{

	public function accepts(Item $item) : bool{
		return $item->getCount() >= 1 && $item instanceof Durable;
	}

	public function __toString() : string{
		return "DurableRecipeIngredient()";
	}
}
