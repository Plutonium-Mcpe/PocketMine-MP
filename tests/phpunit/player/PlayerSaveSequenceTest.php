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

use PHPUnit\Framework\TestCase;

class PlayerSaveSequenceTest extends TestCase{

	public function testASnapshotStaysLatestUntilAnotherSaveStarts() : void{
		$sequence = new PlayerSaveSequence();
		$autosave = $sequence->next("Steve");

		self::assertTrue($sequence->isLatest("Steve", $autosave));
	}

	public function testASaveMadeMeanwhileOutdatesThePendingSnapshot() : void{
		$sequence = new PlayerSaveSequence();
		$autosave = $sequence->next("Steve");
		$quit = $sequence->next("Steve");

		self::assertFalse($sequence->isLatest("Steve", $autosave));
		self::assertTrue($sequence->isLatest("Steve", $quit));
	}

	public function testPlayersDoNotOutdateEachOther() : void{
		$sequence = new PlayerSaveSequence();
		$steve = $sequence->next("Steve");
		$sequence->next("Alex");
		$sequence->next("Alex");

		self::assertTrue($sequence->isLatest("Steve", $steve));
	}

	public function testNamesAreCaseInsensitiveLikePlayerData() : void{
		$sequence = new PlayerSaveSequence();
		$autosave = $sequence->next("Steve");
		$sequence->next("STEVE");

		self::assertFalse($sequence->isLatest("steve", $autosave));
	}

	public function testAnUnknownSequenceIsNeverLatest() : void{
		$sequence = new PlayerSaveSequence();

		self::assertFalse($sequence->isLatest("Steve", 1));
	}
}
