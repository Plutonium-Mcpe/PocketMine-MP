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

use pocketmine\inventory\transaction\TransactionValidationException;
use pocketmine\item\Durable;
use pocketmine\item\EnchantedBook;
use pocketmine\item\enchantment\AvailableEnchantmentRegistry;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\Rarity;
use pocketmine\item\Item;
use function max;
use function min;

abstract class ItemCombineRecipe implements AnvilRecipe{
	abstract protected function validate(Item $input, Item $material) : bool;

	public function getResultFor(Item $input, Item $material) : ?AnvilCraftResult{
		if(!$this->validate($input, $material)){
			return null;
		}

		$result = (clone $input);
		$xpCost = 0;
		$changed = false;

		if($result instanceof Durable && $material instanceof Durable && $this->repair($result, $material)){
			// The two items are compatible for repair
			$xpCost += 2;
			$changed = true;
		}

		// combining enchantments
		foreach($material->getEnchantments() as $instance){
			$enchantment = $instance->getType();
			$level = $instance->getLevel();
			if(!AvailableEnchantmentRegistry::getInstance()->isAvailableForItem($enchantment, $input)){
				continue;
			}
			if(($targetEnchantment = $input->getEnchantment($enchantment)) !== null){
				// Enchant already present on the target item
				$targetLevel = $targetEnchantment->getLevel();
				$newLevel = ($targetLevel === $level ? $targetLevel + 1 : max($targetLevel, $level));
				$level = min($newLevel, $enchantment->getMaxLevel());
				$instance = new EnchantmentInstance($enchantment, $level);
			}else{
				// Check if the enchantment is compatible with the existing enchantments
				$compatible = true;
				foreach($input->getEnchantments() as $testedInstance){
					if(!$testedInstance->getType()->isCompatibleWith($enchantment)){
						// Incompatible enchantments are skipped but still add a small penalty, like vanilla.
						$xpCost++;
						$compatible = false;
						break;
					}
				}
				if(!$compatible){
					continue;
				}
			}

			$costAddition = match ($enchantment->getRarity()) {
				Rarity::COMMON => 1,
				Rarity::UNCOMMON => 2,
				Rarity::RARE => 4,
				Rarity::MYTHIC => 8,
				default => throw new TransactionValidationException("Invalid rarity " . $enchantment->getRarity() . " found")
			};

			if($material instanceof EnchantedBook){
				// Enchanted books are half as expensive to combine
				$costAddition = max(1, (int) ($costAddition / 2));
			}
			// Vanilla bases the cost on the resulting enchantment level, not the difference with the existing level.
			$xpCost += $costAddition * $instance->getLevel();
			$result->addEnchantment($instance);
			$changed = true;
		}

		if(!$changed){
			return null;
		}

		// The prior-work penalty and the increment of the repair cost are applied once per anvil operation, not once
		// per transferred enchantment (matching vanilla behaviour and avoiding runaway XP costs).
		// The repair cost is stored as vanilla's "RepairCost" value (0, 1, 3, 7, 15, ...): the penalty added to the XP
		// cost is the raw stored value, and the new repair cost is max(base, material) * 2 + 1.
		$xpCost += $input->getAnvilRepairCost() + $material->getAnvilRepairCost();
		$result->setAnvilRepairCost(
			max($input->getAnvilRepairCost(), $material->getAnvilRepairCost()) * 2 + 1
		);

		if($xpCost <= 0){
			return null;
		}

		return new AnvilCraftResult($xpCost, $result, null);
	}

	private function repair(Durable $result, Durable $material) : bool{
		$damage = $result->getDamage();
		if($damage === 0){
			return false;
		}

		$baseMaxDurability = $result->getMaxDurability();
		$baseDurability = $baseMaxDurability - $damage;
		$materialDurability = $material->getMaxDurability() - $material->getDamage();
		$addDurability = (int) ($baseMaxDurability * 12 / 100);

		$result->setDamage($baseMaxDurability - min(
				$baseMaxDurability,
				$baseDurability + $materialDurability + $addDurability
			));

		return true;
	}
}
