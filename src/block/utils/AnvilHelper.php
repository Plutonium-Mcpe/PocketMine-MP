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

namespace pocketmine\block\utils;

use pocketmine\crafting\AnvilCraftResult;
use pocketmine\item\Item;
use pocketmine\Server;

final class AnvilHelper{
	private const COST_LIMIT = 39;

	/**
	 * Attempts to calculate the result of an anvil operation.
	 *
	 * Returns null if the operation can't do anything.
	 */
	public static function calculateResult(Item $base, Item $material, ?string $customName, bool $isCreative) : ?AnvilCraftResult{
		$recipe = Server::getInstance()->getCraftingManager()->matchAnvilRecipe($base, $material);
		if($recipe === null){
			return null;
		}
		$result = $recipe->getResultFor($base, $material);

		if($result !== null){
			$resultItem = $result->getOutput();
			$xpCost = $result->getXpCost();
			if(($customName === null || $customName === "") && $resultItem->hasCustomName()){
				$xpCost++;
				$resultItem->clearCustomName();
			}elseif($customName !== null && $resultItem->getCustomName() !== $customName){
				$xpCost++;
				$resultItem->setCustomName($customName);
			}

			$result = new AnvilCraftResult($xpCost, $resultItem, $result->getSacrificeResult());
		}

		if($result === null || $result->getXpCost() <= 0 || ($result->getXpCost() > self::COST_LIMIT && !$isCreative)){
			return null;
		}

		return $result;
	}
}
