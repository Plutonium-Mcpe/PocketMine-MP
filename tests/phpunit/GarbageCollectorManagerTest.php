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

namespace pocketmine;

use PHPUnit\Framework\TestCase;

final class GarbageCollectorManagerTest extends TestCase{

	private const DEFAULT = 100_001;

	/**
	 * A collection that found nothing means the threshold was too low: it climbs.
	 */
	public function testSterileRunRaisesTheThreshold() : void{
		self::assertSame(self::DEFAULT + 100_000, GarbageCollectorManager::nextThreshold(self::DEFAULT, 0, 0));
	}

	/**
	 * A productive run lowers the threshold, but only by a step small enough that the roots a busy
	 * server accumulates in one tick cannot cross it again right away. Dropping the whole way back to
	 * the floor is what made every useful collection drag a sterile one behind it.
	 */
	public function testProductiveRunLowersTheThresholdGradually() : void{
		$threshold = self::DEFAULT + 100_000;
		$lowered = GarbageCollectorManager::nextThreshold($threshold, 1_000, 14);

		self::assertSame($threshold - 10_000, $lowered);
		self::assertGreaterThan(50_000, $lowered, "a tick refills tens of thousands of roots: the threshold must stay above that");
	}

	/**
	 * Several productive runs in a row must not pin the threshold to a floor the next tick clears,
	 * which had the collector running every tick for as long as it kept finding cycles.
	 */
	public function testRepeatedProductiveRunsStayAboveATicksWorthOfRoots() : void{
		$threshold = self::DEFAULT + 100_000;
		for($i = 0; $i < 50; $i++){
			$threshold = GarbageCollectorManager::nextThreshold($threshold, 5_000, 20);
		}

		self::assertSame(self::DEFAULT, $threshold);
		self::assertGreaterThan(20_000, $threshold);
	}

	public function testThresholdIsCapped() : void{
		self::assertSame(1_000_000_000, GarbageCollectorManager::nextThreshold(1_000_000_000, 0, 0));
	}

	/**
	 * Roots left over above the threshold mean the collection could not keep up, so it climbs even
	 * though it collected something.
	 */
	public function testLeftoverRootsRaiseTheThreshold() : void{
		$threshold = self::DEFAULT;
		self::assertSame($threshold + 100_000, GarbageCollectorManager::nextThreshold($threshold, 5_000, $threshold));
	}
}
