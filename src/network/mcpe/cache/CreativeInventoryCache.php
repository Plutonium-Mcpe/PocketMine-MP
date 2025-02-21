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

namespace pocketmine\network\mcpe\cache;

use pocketmine\block\VanillaBlocks;
use pocketmine\inventory\CreativeCategory;
use pocketmine\inventory\CreativeInventory;
use pocketmine\item\Item;
use pocketmine\item\VanillaItems;
use pocketmine\lang\Translatable;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\network\mcpe\convert\ItemTranslator;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\network\mcpe\NetworkSession;
use pocketmine\network\mcpe\protocol\CreativeContentPacket;
use pocketmine\network\mcpe\protocol\serializer\PacketSerializer;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeGroupEntry;
use pocketmine\network\mcpe\protocol\types\inventory\CreativeItemEntry;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStack;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraData;
use pocketmine\network\mcpe\protocol\types\inventory\ItemStackExtraDataShield;
use pocketmine\utils\SingletonTrait;
use function is_string;
use function spl_object_id;
use const PHP_INT_MIN;

final class CreativeInventoryCache{
	use SingletonTrait;

	/**
	 * @var CreativeInventoryCacheEntry[]
	 * @phpstan-var array<int, CreativeInventoryCacheEntry>
	 */
	private array $caches = [];

	private function getCacheEntry(CreativeInventory $inventory) : CreativeInventoryCacheEntry{
		$id = spl_object_id($inventory);
		if(!isset($this->caches[$id])){
			$inventory->getDestructorCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$inventory->getContentChangedCallbacks()->add(function() use ($id) : void{
				unset($this->caches[$id]);
			});
			$this->caches[$id] = $this->buildCacheEntry($inventory);
		}
		return $this->caches[$id];
	}

	/**
	 * Rebuild the cache for the given inventory.
	 */
	private function buildCacheEntry(CreativeInventory $inventory) : CreativeInventoryCacheEntry{
		$categories = [];
		$groups = [];

		$typeConverter = TypeConverter::getInstance();

		$nextIndex = 0;
		$groupIndexes = [];
		$itemGroupIndexes = [];


		foreach($inventory->getAllEntries() as $k => $entry){
			$group = $entry->getGroup();
			$category = $entry->getCategory();
			if($group === null){
				$groupId = PHP_INT_MIN;
			}else{
				$groupId = spl_object_id($group);
				unset($groupIndexes[$category->name][PHP_INT_MIN]); //start a new anonymous group for this category
			}

			//group object may be reused by multiple categories
			if(!isset($groupIndexes[$category->name][$groupId])){
				$groupIndexes[$category->name][$groupId] = $nextIndex++;
				$categories[] = $category;
				$groups[] = $group;
			}
			$itemGroupIndexes[$k] = $groupIndexes[$category->name][$groupId];
		}

		//creative inventory may have holes if items were unregistered - ensure network IDs used are always consistent
		$items = [];
		foreach($inventory->getAllEntries() as $k => $entry){
			$items[] = new CreativeItemEntry(
				$k,
				$typeConverter->coreItemStackToNet($entry->getItem()),
				$itemGroupIndexes[$k]
			);
		}

		return new CreativeInventoryCacheEntry($categories, $groups, $items);
	}

	/*
	 * hardcoded packet:
	 *
	 * {
  "category": "construction",
  "name": "itemGroup.name.planks",
  "icon_item": {
    "network_id": 5,
    "count": 1,
    "metadata": 0,
    "block_runtime_id": 13764,
    "extra": {
      "has_nbt": true,
      "nbt": {
        "version": 1,
        "nbt": {
          "type": "compound",
          "name": "",
          "value": {
            "___GroupBugWorkaround___": {
              "type": "int",
              "value": 0
            }
          }
        }
      },
      "can_place_on": [],
      "can_destroy": []
    }
  }
}
	 */

	public function buildPacket(CreativeInventory $inventory, NetworkSession $session) : CreativeContentPacket{
		$player = $session->getPlayer() ?? throw new \LogicException("Cannot prepare creative data for a session without a player");
		$language = $player->getLanguage();
		//$typeConverter = $session->getTypeConverter();
		$cachedEntry = $this->getCacheEntry($inventory);
		$translate = function(Translatable|string $translatable) use ($session, $language) : string{
			if(is_string($translatable)){
				$message = $translatable;
			}else{
				$message = $language->translate($translatable);
			}
			return $message;
		};

		//var_dump($cachedEntry->categories);
		//var_dump($cachedEntry->items);


		$groupEntries = [];
		foreach($cachedEntry->categories as $index => $category){
			$group = $cachedEntry->groups[$index];
			$categoryId = match ($category) {
				CreativeCategory::CONSTRUCTION => CreativeContentPacket::CATEGORY_CONSTRUCTION,
				CreativeCategory::NATURE => CreativeContentPacket::CATEGORY_NATURE,
				CreativeCategory::EQUIPMENT => CreativeContentPacket::CATEGORY_EQUIPMENT,
				CreativeCategory::ITEMS => CreativeContentPacket::CATEGORY_ITEMS
			};
			if($group === null){
				$groupEntries[] = new CreativeGroupEntry($categoryId, "", ItemStack::null());
			}else{
				$groupIcon = $group->getIcon();
				//TODO: HACK! In 1.21.60, Workaround glitchy behaviour when an item is used as an icon for a group it
				//doesn't belong to. Without this hack, both instances of the item will show a +, but neither of them
				//will actually expand the group work correctly.

				//var_dump($groupIcon);


				$groupIcon->getNamedTag()->setInt("___GroupBugWorkaround___", $index);
				$groupName = $group->getName();
				$groupEntries[] = new CreativeGroupEntry(
					$categoryId,
					$translate($groupName),
					$this->coreItemStackToNet($groupIcon)
				);
			}
		}

		return CreativeContentPacket::create($groupEntries, $cachedEntry->items);
	}

	public function coreItemStackToNet(Item $itemStack) : ItemStack{
		if($itemStack->isNull()){
			return ItemStack::null();
		}
		$nbt = $itemStack->getNamedTag();
		if($nbt->count() === 0){
			$nbt = null;
		}else{
			$nbt = clone $nbt;
		}

		$id = $itemStack->getId();
		$meta = $itemStack->getMeta();
		$blockRuntimeId = null;

		$extraData = new ItemStackExtraData($nbt, canPlaceOn: [], canDestroy: []);
		$extraDataSerializer = PacketSerializer::encoder();
		$extraData->write($extraDataSerializer);

		return new ItemStack(
			$id,
			$meta,
			$itemStack->getCount(),
			$blockRuntimeId ?? 0,
			$extraDataSerializer->getBuffer(),
		);
	}
}
