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

namespace pocketmine\block\inventory;

use pocketmine\block\tile\EnderChest;
use pocketmine\inventory\DelegateInventory;
use pocketmine\inventory\Inventory;
use pocketmine\inventory\PlayerEnderInventory;
use pocketmine\network\mcpe\protocol\BlockEventPacket;
use pocketmine\network\mcpe\protocol\types\BlockPosition;
use pocketmine\player\Player;
use pocketmine\world\Position;
use pocketmine\world\sound\EnderChestCloseSound;
use pocketmine\world\sound\EnderChestOpenSound;
use pocketmine\world\sound\Sound;
use function max;

/**
 * EnderChestInventory is not a real inventory; it's just a gateway to the player's ender inventory.
 */
class EnderChestInventory extends DelegateInventory implements BlockInventory{
	use AnimatedBlockInventoryTrait {
		onClose as animatedBlockInventoryTrait_onClose;
	}

	public function __construct(
		Position $holder,
		private PlayerEnderInventory $inventory
	){
		parent::__construct($inventory);
		$this->holder = $holder;
	}

	public function getEnderInventory() : PlayerEnderInventory{
		return $this->inventory;
	}

	public function getViewerCount() : int{
		$enderChest = $this->getHolder()->getWorld()->getTile($this->getHolder());
		if(!$enderChest instanceof EnderChest){
			return 0;
		}
		return $enderChest->getViewerCount();
	}

	protected function getOpenSound() : Sound{
		return new EnderChestOpenSound();
	}

	protected function getCloseSound() : Sound{
		return new EnderChestCloseSound();
	}

	protected function animateBlock(bool $isOpen) : void{
		$holder = $this->getHolder();

		//event ID is always 1 for a chest
		$holder->getWorld()->broadcastPacketToViewers($holder, BlockEventPacket::create(BlockPosition::fromVector3($holder), 1, $isOpen ? 1 : 0));
	}

	public function onClose(Player $who) : void{
		$this->animatedBlockInventoryTrait_onClose($who);
		$enderChest = $this->getHolder()->getWorld()->getTile($this->getHolder());
		if($enderChest instanceof EnderChest){
			//The tile is looked up again here, so it may not be the one Block\EnderChest::onInteract()
			//counted on when the window was opened: unloading and reloading the chunk, or breaking and
			//replacing the block, leaves a fresh tile whose count is already zero. Decrementing that threw,
			//which killed the packet handler, disconnected the player, and threw a second time from the
			//disconnect path, which closes the window again.
			$enderChest->setViewerCount(max(0, $enderChest->getViewerCount() - 1));
		}
	}
}
