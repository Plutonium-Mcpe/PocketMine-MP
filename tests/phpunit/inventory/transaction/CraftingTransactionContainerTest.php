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

namespace pocketmine\inventory\transaction;

use PHPUnit\Framework\TestCase;
use pocketmine\block\tile\Container;
use pocketmine\block\utils\DyeColor;
use pocketmine\block\VanillaBlocks;
use pocketmine\crafting\CraftingManager;
use pocketmine\crafting\CraftingResultTransfer;
use pocketmine\crafting\ExactRecipeIngredient;
use pocketmine\crafting\ShapelessRecipe;
use pocketmine\crafting\ShapelessRecipeType;
use pocketmine\entity\Entity;
use pocketmine\inventory\PlayerCraftingInventory;
use pocketmine\inventory\transaction\action\InventoryAction;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\ListTag;
use pocketmine\player\Player;

class CraftingTransactionContainerTest extends TestCase{

	private static function shulker(DyeColor $color, ?Item $content = null) : Item{
		$box = VanillaBlocks::DYED_SHULKER_BOX()->setColor($color)->asItem();
		if($content !== null){
			$tag = $box->getNamedTag();
			$tag->setTag(Container::TAG_ITEMS, new ListTag([$content->nbtSerialize(0)]));
			$box->setNamedTag($tag);
		}
		return $box;
	}

	private static function dyed(Item $source) : Item{
		$result = self::shulker(DyeColor::BLACK);
		CraftingResultTransfer::transferContainerNamedTag([$source], [$result]);
		return $result;
	}

	private static function action(Item $source, Item $target) : InventoryAction{
		return new class($source, $target) extends InventoryAction{
			public function validate(Player $source) : void{}

			public function execute(Player $source) : void{}
		};
	}

	/**
	 * @param Item[]      $consumed
	 * @param Item[]      $created
	 * @param Item[]|null $recipeResults
	 *
	 * @phpstan-param list<Item>|null $recipeResults
	 */
	private static function transaction(array $consumed, array $created, int $repetitions, ?array $recipeResults = null) : CraftingTransaction{
		$player = (new \ReflectionClass(Player::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Player::class, "craftingGrid"))->setValue($player, new PlayerCraftingInventory($player));
		(new \ReflectionProperty(Entity::class, "closed"))->setValue($player, true);
		(new \ReflectionProperty(Player::class, "logger"))->setValue($player, new class extends \SimpleLogger{
			public function log($level, $message){}
		});

		$recipe = new ShapelessRecipe(
			[new ExactRecipeIngredient(self::shulker(DyeColor::LIME)), new ExactRecipeIngredient(VanillaItems::INK_SAC())],
			$recipeResults ?? [self::shulker(DyeColor::BLACK)],
			ShapelessRecipeType::CRAFTING
		);

		$actions = [];
		foreach($consumed as $item){
			$actions[] = self::action($item, VanillaItems::AIR());
		}
		foreach($created as $item){
			$actions[] = self::action(VanillaItems::AIR(), $item);
		}
		return new CraftingTransaction($player, new CraftingManager(), $actions, $recipe, $repetitions);
	}

	public function testDyeingOneFilledShulkerKeepsItsContents() : void{
		$content = VanillaItems::DIAMOND()->setCount(64);
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME, $content), VanillaItems::INK_SAC()],
			[self::dyed(self::shulker(DyeColor::LIME, $content))],
			1
		);

		$transaction->validate();
		$this->addToAssertionCount(1);
	}

	public function testDyeingOneEmptyShulker() : void{
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME), VanillaItems::INK_SAC()],
			[self::shulker(DyeColor::BLACK)],
			1
		);

		$transaction->validate();
		$this->addToAssertionCount(1);
	}

	public function testDyeingSeveralEmptyShulkersAtOnce() : void{
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME), self::shulker(DyeColor::LIME), VanillaItems::INK_SAC()->setCount(2)],
			[self::shulker(DyeColor::BLACK), self::shulker(DyeColor::BLACK)],
			2
		);

		$transaction->validate();
		$this->addToAssertionCount(1);
	}

	public function testFilledShulkerContentsCannotBeCopiedOntoASecondBox() : void{
		$content = VanillaItems::DIAMOND()->setCount(64);
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME, $content), self::shulker(DyeColor::LIME), VanillaItems::INK_SAC()->setCount(2)],
			[self::dyed(self::shulker(DyeColor::LIME, $content)), self::dyed(self::shulker(DyeColor::LIME, $content))],
			2
		);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testTwoFilledShulkersCannotBeMergedIntoCopiesOfTheFirst() : void{
		$diamonds = VanillaItems::DIAMOND()->setCount(64);
		$dirt = VanillaBlocks::DIRT()->asItem();
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME, $diamonds), self::shulker(DyeColor::LIME, $dirt), VanillaItems::INK_SAC()->setCount(2)],
			[self::dyed(self::shulker(DyeColor::LIME, $diamonds)), self::dyed(self::shulker(DyeColor::LIME, $diamonds))],
			2
		);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testRecipeYieldingTwoItemsCannotCopyFilledShulkerContents() : void{
		$content = VanillaItems::DIAMOND()->setCount(64);
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME, $content), VanillaItems::INK_SAC()],
			[self::dyed(self::shulker(DyeColor::LIME, $content)), self::dyed(self::shulker(DyeColor::LIME, $content))],
			1,
			[self::shulker(DyeColor::BLACK)->setCount(2)]
		);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testRecipeWithTwoResultsCannotCopyFilledShulkerContents() : void{
		$content = VanillaItems::DIAMOND()->setCount(64);
		$source = self::shulker(DyeColor::LIME, $content);
		$red = self::shulker(DyeColor::RED);
		CraftingResultTransfer::transferContainerNamedTag([$source], [$red]);
		$transaction = self::transaction(
			[$source, VanillaItems::INK_SAC()],
			[self::dyed($source), $red],
			1,
			[self::shulker(DyeColor::BLACK), self::shulker(DyeColor::RED)]
		);

		$this->expectException(TransactionValidationException::class);
		$transaction->validate();
	}

	public function testRecipeYieldingTwoItemsStillWorksWithAnEmptyShulker() : void{
		$transaction = self::transaction(
			[self::shulker(DyeColor::LIME), VanillaItems::INK_SAC()],
			[self::shulker(DyeColor::BLACK), self::shulker(DyeColor::BLACK)],
			1,
			[self::shulker(DyeColor::BLACK)->setCount(2)]
		);

		$transaction->validate();
		$this->addToAssertionCount(1);
	}
}
