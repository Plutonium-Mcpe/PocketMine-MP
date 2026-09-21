<?php

namespace pocketmine\timings;

/**
 * Represents a single execution event in the timeline hierarchy
 */
final readonly class TimingsTimelineEntry{
	public function __construct(
		public int $tick,
		public TimingsRecord $record,
		public int $startTime,
		public int $endTime,
		public int $depth,
		/** @var TimingsTimelineEntry[] */
		public array $children = []
	){}

	public function getDuration() : int{
		return $this->endTime - $this->startTime;
	}

	public function getRelativeStartTime(int $tickStartTime) : int{
		return $this->startTime - $tickStartTime;
	}

	public function getRelativeEndTime(int $tickStartTime) : int{
		return $this->endTime - $tickStartTime;
	}
}