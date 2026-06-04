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

namespace pocketmine\timings;

use function hrtime;

/**
 * Tracks the timeline of all timings executions for a given tick, maintaining hierarchy
 */
final class TimingsTimeline{
	/** @var TimingsTimelineEntry[] */
	private array $rootEntries = [];

	/** @var TimingsTimelineEntry[] */
	private array $stack = [];

	private int $nextDepth = 0;
	private readonly int $tickStartTime;
	/** @var array<int, TimingsTimelineEntry[]> */
	private array $pendingChildren = [];

	/**
	 * @param int $previousTick Previous tick represent a mid-tick timeline
	 */
	public function __construct(
		public readonly int $tick,
		public readonly ?int $previousTick
	){
		$this->tickStartTime = hrtime(true);
	}

	/**
	 * Record the start of a timer execution
	 */
	public function startEntry(TimingsRecord $record) : void{
		$depth = $this->nextDepth;
		$this->stack[$depth] = new TimingsTimelineEntry(
			tick: $this->tick,
			record: $record,
			startTime: hrtime(true),
			endTime: 0,
			depth: $depth
		);
		//\GlobalLogger::get()->debug("Starting timer '{$record->getName()}' (ID: {$record->getTimerId()} at depth $depth for tick {$this->tick}");
		$this->nextDepth++;
	}

	/**
	 * Record the end of a timer execution
	 */
	public function endEntry(TimingsRecord $record) : void{
		if($this->nextDepth === 0){
			\GlobalLogger::get()->warning("Attempted to end timing entry but stack is empty for tick {$this->tick}");
			return; // Safety check
		}

		$this->nextDepth--;
		$depth = $this->nextDepth;
		$entry = $this->stack[$depth];
		$endTime = hrtime(true);

		if ($entry->record !== $record) {
			\GlobalLogger::get()->warning("Mismatched timing entry end: expected '{$entry->record->getName()}' (ID: {$entry->record->getTimerId()}) but got '{$record->getName()}' (ID: {$record->getTimerId()}) at depth $depth for tick {$this->tick}");
			return; // Safety check
		}

		// Create the completed entry with actual end time
		$completedEntry = new TimingsTimelineEntry(
			tick: $this->tick,
			record: $record,
			startTime: $entry->startTime,
			endTime: $endTime,
			depth: $depth,
			children: $this->pendingChildren[$depth] ?? []
		);

		unset($this->stack[$depth]);
		unset($this->pendingChildren[$depth]);

		if($this->nextDepth === 0){
			// This is a root entry
			$this->rootEntries[] = $completedEntry;
			//\GlobalLogger::get()->debug("Completed root timer '{$entry->record}' at depth {$entry->depth} for tick {$this->tick}");
		}else{
			// This entry will be added as a child by its parent when it ends
			$this->pendingChildren[$depth - 1][] = $completedEntry;
			//\GlobalLogger::get()->debug("Completed timer '{$entry->record}' at depth {$entry->depth} for tick {$this->tick} (attached to parent)");
		}
	}

	/**
	 * Get all root entries (top-level timers executed during this tick)
	 * @return TimingsTimelineEntry[]
	 */
	public function getRootEntries() : array{
		return $this->rootEntries;
	}

	/**
	 * Get the tick start time
	 */
	public function getTickStartTime() : int{
		return $this->tickStartTime;
	}

	/**
	 * Get all entries in depth-first order
	 * @return TimingsTimelineEntry[]
	 */
	public function getAllEntries() : array{
		$entries = [];
		foreach($this->rootEntries as $root){
			$this->collectEntries($root, $entries);
		}
		return $entries;
	}

	/**
	 * @param TimingsTimelineEntry[] $entries
	 */
	private function collectEntries(TimingsTimelineEntry $entry, array &$entries) : void{
		$entries[] = $entry;
		foreach($entry->children as $child){
			$this->collectEntries($child, $entries);
		}
	}
}

