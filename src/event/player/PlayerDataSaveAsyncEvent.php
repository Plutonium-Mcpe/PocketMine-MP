<?php

namespace pocketmine\event\player;

use pocketmine\event\AsyncEvent;
use pocketmine\event\Cancellable;
use pocketmine\event\CancellableTrait;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;

class PlayerDataSaveAsyncEvent extends AsyncEvent implements Cancellable{
	use CancellableTrait;

	public function __construct(
		protected CompoundTag $data,
		protected string $playerName,
		private ?Player $player
	){}

	/**
	 * Returns the data to be written to disk as a CompoundTag
	 */
	public function getSaveData() : CompoundTag{
		return $this->data;
	}

	public function setSaveData(CompoundTag $data) : void{
		$this->data = $data;
	}

	/**
	 * Returns the username of the player whose data is being saved. This is not necessarily an online player.
	 */
	public function getPlayerName() : string{
		return $this->playerName;
	}

	/**
	 * Returns the player whose data is being saved, if online.
	 * If null, this data is for an offline player (possibly just disconnected).
	 */
	public function getPlayer() : ?Player{
		return $this->player;
	}
}