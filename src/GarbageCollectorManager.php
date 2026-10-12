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

use pocketmine\timings\TimingsHandler;
use function gc_collect_cycles;
use function gc_disable;
use function gc_status;
use function hrtime;
use function max;
use function min;
use function number_format;
use function sprintf;

/**
 * Allows threads to manually trigger the cyclic garbage collector using a threshold like PHP's own garbage collector,
 * but triggered at a time that suits the thread instead of in random code pathways.
 *
 * The GC trigger behaviour in this class was adapted from Zend/zend_gc.c as of PHP 8.3.14.
 */
final class GarbageCollectorManager{
	//These values mirror PHP's own GC, which is tuned for short-lived requests where the live object
	//graph is small. A server keeps worlds, chunks, entities and players alive for hours, and walking
	//that graph is what a collection actually costs: production measurements over 37 consecutive runs
	//give 155 ms on average with almost no correlation to the number of roots (R² = 0.23 against root
	//count, and runs that collected nothing still cost 83 ms). The cost is therefore per RUN, not per
	//root, so the only thing that reduces it is running less often.
	//
	//PHP's step of 10,000 cannot get there: the buffer refills 45,000 roots in 200 ms, so the
	//threshold is overtaken as fast as it grows and collection settles into a run every few hundred
	//milliseconds — 150 ms of frozen main thread each time, which players feel as a stutter while the
	//TPS average hides it.
	//
	//Climbing and falling must not share a step, and the floor must stay above what a single tick
	//accumulates. With both at 100,000 one productive run dropped the threshold the whole way back to
	//10,001, a busy server crossed that again 20 ms later, and the collection that followed found
	//nothing and still cost 55 ms. Every useful run dragged a sterile one behind it, and when several
	//runs in a row collected something the threshold stayed pinned to the floor and the collector ran
	//every tick: five runs and 348 ms of frozen main thread within the same second, measured in
	//production.
	private const GC_THRESHOLD_TRIGGER = 100;
	private const GC_THRESHOLD_MAX = 1_000_000_000;
	private const GC_THRESHOLD_DEFAULT = 100_001;
	private const GC_THRESHOLD_STEP_UP = 100_000;
	private const GC_THRESHOLD_STEP_DOWN = 10_000;

	private int $threshold = self::GC_THRESHOLD_DEFAULT;
	private int $collectionTimeTotalNs = 0;
	private int $runs = 0;

	private \Logger $logger;
	private TimingsHandler $timings;

	public function __construct(
		\Logger $logger,
		?TimingsHandler $parentTimings,
	){
		gc_disable();
		$this->logger = new \PrefixedLogger($logger, "Cyclic Garbage Collector");
		$this->timings = new TimingsHandler("Cyclic Garbage Collector", $parentTimings);
	}

	/**
	 * Threshold a collection leaves behind, given what it found.
	 * Adapted from zend_gc.c/gc_adjust_threshold() as of PHP 8.3.14.
	 */
	public static function nextThreshold(int $threshold, int $cyclesCollected, int $rootsAfterGC) : int{
		if($cyclesCollected < self::GC_THRESHOLD_TRIGGER || $rootsAfterGC >= $threshold){
			return min(self::GC_THRESHOLD_MAX, $threshold + self::GC_THRESHOLD_STEP_UP);
		}
		if($threshold > self::GC_THRESHOLD_DEFAULT){
			return max(self::GC_THRESHOLD_DEFAULT, $threshold - self::GC_THRESHOLD_STEP_DOWN);
		}

		return $threshold;
	}

	public function getThreshold() : int{ return $this->threshold; }

	public function getCollectionTimeTotalNs() : int{ return $this->collectionTimeTotalNs; }

	public function maybeCollectCycles() : int{
		$rootsBefore = gc_status()["roots"];
		if($rootsBefore < $this->threshold){
			return 0;
		}

		$this->timings->startTiming();

		$start = hrtime(true);
		$cycles = gc_collect_cycles();
		$end = hrtime(true);

		$rootsAfter = gc_status()["roots"];
		$this->threshold = self::nextThreshold($this->threshold, $cycles, $rootsAfter);

		$this->timings->stopTiming();

		$time = $end - $start;
		$this->collectionTimeTotalNs += $time;
		$this->runs++;
		$this->logger->info(sprintf(
			"Run #%d took %s ms (%s -> %s roots, %s cycles collected) - cumulative GC time: %s ms",
			$this->runs,
			number_format($time / 1_000_000, 2),
			$rootsBefore,
			$rootsAfter,
			$cycles,
			number_format($this->collectionTimeTotalNs / 1_000_000, 2)
		));

		return $cycles;
	}
}
