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

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\item\BlockItemIdMap;
use pocketmine\data\bedrock\item\ItemTypeDeserializeException;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\item\SavedItemStackData;
use pocketmine\item\VanillaItems;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\ShortTag;
use pocketmine\network\mcpe\convert\GlobalItemTypeDictionary;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;

use JsonMapper;
use pocketmine\data\SavedDataLoadingException;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\inventory\json\ItemStackData;
use pocketmine\item\Item;
use pocketmine\item\ItemFactory;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\utils\AssumptionFailedError;
use pocketmine\utils\Filesystem;
use pocketmine\utils\Utils;
use ReflectionClass;
use function array_map;
use function is_array;
use function json_decode;

final class CraftingManagerFromDataHelper{

	public static function deserializeItemStack(ItemStackData $data) : ?Item{
		//count, name, block_name, block_states, meta, nbt, can_place_on, can_destroy
		return self::deserializeItemStackFromFields(
			$data->name,
			$data->meta ?? null,
			$data->count ?? null,
			$data->block_states ?? null,
			$data->nbt ?? null,
			$data->can_place_on ?? [],
			$data->can_destroy ?? []
		);
	}

	private static function SavedItemStackDataToItem(SavedItemStackData $data) : ?Item
	{
		$nbt = $data->toNbt();
		$id = GlobalItemTypeDictionary::getInstance()->getDictionary()->fromStringId($data->getTypeData()->getName());
		$tag = new ShortTag($id);
		$nbt->setTag("id", $tag);
		$item = Item::nbtDeserialize($nbt);
		return $item;
	}

	/**
	 * @param string[] $canPlaceOn
	 * @param string[] $canDestroy
	 */
	private static function deserializeItemStackFromFields(string $name, ?int $meta, ?int $count, ?string $blockStatesRaw, ?string $nbtRaw, array $canPlaceOn, array $canDestroy) : ?Item{
		$meta ??= 0;
		$count ??= 1;

		$blockName = BlockItemIdMap::getInstance()->lookupBlockId($name);
		if($blockName !== null){
			if($meta !== 0){
				throw new SavedDataLoadingException("Meta should not be specified for blockitems");
			}
			$blockStatesTag = $blockStatesRaw === null ?
				[] :
				(new LittleEndianNbtSerializer())
					->read(ErrorToExceptionHandler::trapAndRemoveFalse(fn() => base64_decode($blockStatesRaw, true)))
					->mustGetCompoundTag()
					->getValue();
			$blockStateData = BlockStateData::current($blockName, $blockStatesTag);
		}else{
			$blockStateData = null;
		}

		$nbt = $nbtRaw === null ? null : (new LittleEndianNbtSerializer())
			->read(ErrorToExceptionHandler::trapAndRemoveFalse(fn() => base64_decode($nbtRaw, true)))
			->mustGetCompoundTag();

		$itemStackData = new SavedItemStackData(
			new SavedItemData(
				$name,
				$meta,
				$blockStateData,
				$nbt
			),
			$count,
			null,
			null,
			$canPlaceOn,
			$canDestroy,
		);

		try{
			//TODO: convert ItemStackData to Item using PM4 API
			return self::SavedItemStackDataToItem($itemStackData);
		}catch(ItemTypeDeserializeException){
			//probably unknown item
			return null;
		}
	}

	/**
	 * @return mixed[]
	 *
	 * @phpstan-template TData of object
	 * @phpstan-param class-string<TData> $modelCLass
	 * @phpstan-return list<TData>
	 */
	public static function loadJsonArrayOfObjectsFile(string $filePath, string $modelCLass) : array{
		$recipes = json_decode(Filesystem::fileGetContents($filePath));
		if(!is_array($recipes)){
			throw new SavedDataLoadingException("$filePath root should be an array, got " . get_debug_type($recipes));
		}

		$mapper = new JsonMapper();
		$mapper->bStrictObjectTypeChecking = true;
		$mapper->bExceptionOnUndefinedProperty = true;
		$mapper->bExceptionOnMissingData = true;

		return self::loadJsonObjectListIntoModel($mapper, $modelCLass, $recipes);
	}

	/**
	 * @param mixed[] $data
	 * @return object[]
	 *
	 * @phpstan-template TRecipeData of object
	 * @phpstan-param class-string<TRecipeData> $modelClass
	 * @phpstan-return list<TRecipeData>
	 */
	private static function loadJsonObjectListIntoModel(JsonMapper $mapper, string $modelClass, array $data) : array{
		$result = [];
		foreach(Utils::promoteKeys($data) as $i => $item){
			if(!is_object($item)){
				throw new SavedDataLoadingException("Invalid entry at index $i: expected object, got " . get_debug_type($item));
			}
			try{
				$result[] = self::loadJsonObjectIntoModel($mapper, $modelClass, $item);
			}catch(SavedDataLoadingException $e){
				throw new SavedDataLoadingException("Invalid entry at index $i: " . $e->getMessage(), 0, $e);
			}
		}
		return $result;
	}

	/**
	 * @phpstan-template TRecipeData of object
	 * @phpstan-param class-string<TRecipeData> $modelClass
	 * @phpstan-return TRecipeData
	 */
	private static function loadJsonObjectIntoModel(JsonMapper $mapper, string $modelClass, object $data) : object{
		//JsonMapper does this for subtypes, but not for the base type :(
		try{
			return $mapper->map($data, (new ReflectionClass($modelClass))->newInstanceWithoutConstructor());
		}catch(\JsonMapper_Exception $e){
			throw new SavedDataLoadingException($e->getMessage(), 0, $e);
		}
	}

	/**
	 * @param Item[] $items
	 */
	private static function containsUnknownOutputs(array $items) : bool{
		$factory = ItemFactory::getInstance();
		foreach($items as $item){
			if($item->hasAnyDamageValue()){
				throw new \InvalidArgumentException("Recipe outputs must not have wildcard meta values");
			}
			if(!$factory->isRegistered($item->getId(), $item->getMeta())){
				return true;
			}
		}

		return false;
	}

	public static function make(string $filePath) : CraftingManager{
		$recipes = json_decode(Filesystem::fileGetContents($filePath), true);
		if(!is_array($recipes)){
			throw new AssumptionFailedError("recipes.json root should contain a map of recipe types");
		}
		$result = new CraftingManager();

		$itemDeserializerFunc = \Closure::fromCallable([Item::class, 'jsonDeserialize']);

		foreach($recipes["shapeless"] as $recipe){
			$recipeType = match($recipe["block"]){
				"crafting_table" => ShapelessRecipeType::CRAFTING(),
				"stonecutter" => ShapelessRecipeType::STONECUTTER(),
				//TODO: Cartography Table
				default => null
			};
			if($recipeType === null){
				continue;
			}
			$output = array_map($itemDeserializerFunc, $recipe["output"]);
			if(self::containsUnknownOutputs($output)){
				continue;
			}
			$result->registerShapelessRecipe(new ShapelessRecipe(
				array_map($itemDeserializerFunc, $recipe["input"]),
				$output,
				$recipeType
			));
		}
		foreach($recipes["shaped"] as $recipe){
			if($recipe["block"] !== "crafting_table"){ //TODO: filter others out for now to avoid breaking economics
				continue;
			}
			$output = array_map($itemDeserializerFunc, $recipe["output"]);
			if(self::containsUnknownOutputs($output)){
				continue;
			}
			$result->registerShapedRecipe(new ShapedRecipe(
				$recipe["shape"],
				array_map($itemDeserializerFunc, $recipe["input"]),
				$output
			));
		}
		foreach($recipes["smelting"] as $recipe){
			$furnaceType = match ($recipe["block"]){
				"furnace" => FurnaceType::FURNACE(),
				"blast_furnace" => FurnaceType::BLAST_FURNACE(),
				"smoker" => FurnaceType::SMOKER(),
				//TODO: campfire
				default => null
			};
			if($furnaceType === null){
				continue;
			}
			$output = Item::jsonDeserialize($recipe["output"]);
			if(self::containsUnknownOutputs([$output])){
				continue;
			}
			$result->getFurnaceRecipeManager($furnaceType)->register(new FurnaceRecipe(
				$output,
				Item::jsonDeserialize($recipe["input"]))
			);
		}
		foreach($recipes["potion_type"] as $recipe){
			$output = Item::jsonDeserialize($recipe["output"]);
			if(self::containsUnknownOutputs([$output])){
				continue;
			}
			$result->registerPotionTypeRecipe(new PotionTypeRecipe(
				Item::jsonDeserialize($recipe["input"]),
				Item::jsonDeserialize($recipe["ingredient"]),
				$output
			));
		}
		foreach($recipes["potion_container_change"] as $recipe){
			if(!ItemFactory::getInstance()->isRegistered($recipe["output_item_id"])){
				continue;
			}
			$result->registerPotionContainerChangeRecipe(new PotionContainerChangeRecipe(
				$recipe["input_item_id"],
				Item::jsonDeserialize($recipe["ingredient"]),
				$recipe["output_item_id"]
			));
		}

		return $result;
	}
}
