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

namespace pocketmine\player;

use function mb_strtolower;

/**
 * Tells whether a player data snapshot taken earlier is still the most recent one when it is finally written.
 * An asynchronous save writes its snapshot only once its event handlers are done, by which time a later save
 * (typically the one made when the player quits) may already be on disk.
 */
final class PlayerSaveSequence{
	/**
	 * @var int[]
	 * @phpstan-var array<string, int>
	 */
	private array $latest = [];

	public function next(string $name) : int{
		$key = mb_strtolower($name);
		return $this->latest[$key] = ($this->latest[$key] ?? 0) + 1;
	}

	public function isLatest(string $name, int $sequence) : bool{
		return ($this->latest[mb_strtolower($name)] ?? 0) === $sequence;
	}
}
