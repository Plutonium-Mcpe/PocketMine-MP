<?php

namespace pocketmine\timings;

use pocketmine\utils\BinaryStream;

final class TimingsTimelineRegistry{
	private static ?TimingsTimeline $currentTimeline = null;
	/** @var string[] */
	private static array $archivedTimelines = [];

	public static function newTick(int $tick, ?int $previousTick) : void {
		if (TimingsHandler::isEnabled() && TimingsHandler::isTimelineEnabled()) {
			//\GlobalLogger::get()->debug("Starting new timings timeline for tick $tick (previousTick: $previousTick)");
			self::$currentTimeline = new TimingsTimeline($tick, $previousTick);
		}
	}

	/** @return string[] */
	public static function getArchivedTimelines() : array{
		return self::$archivedTimelines;
	}

	public static function endTick() : void {
		if (TimingsHandler::isEnabled() && TimingsHandler::isTimelineEnabled() && self::$currentTimeline !== null) {
			//\GlobalLogger::get()->debug("Ending new timings timeline for current tick");
			$stream = new BinaryStream();
			$tickStartTime = self::$currentTimeline->getTickStartTime();
			if (self::$currentTimeline->previousTick === null) {
				$stream->putBool(false);
				$stream->putLInt(self::$currentTimeline->tick);
				$stream->putUnsignedVarLong($tickStartTime);
			}else{
				$stream->putBool(true);
				$stream->putLInt(self::$currentTimeline->previousTick);
				$stream->putLInt(self::$currentTimeline->tick);
				$stream->putUnsignedVarLong($tickStartTime);
			}
			foreach(self::$currentTimeline->getAllEntries() as $entry){
				$stream->putInt($entry->depth);
				$stream->putInt($entry->record->getId());
				$stream->putUnsignedVarLong($entry->getRelativeStartTime($tickStartTime));
				$stream->putUnsignedVarLong($entry->getRelativeEndTime($tickStartTime));
			}
			self::$archivedTimelines[] = $stream->getBuffer();
			self::$currentTimeline = null;
		}
	}

	public static function startEntry(TimingsRecord $record) : void {
		if (TimingsHandler::isEnabled() && TimingsHandler::isTimelineEnabled()) {
			self::$currentTimeline?->startEntry($record);
		}
	}

	public static function endEntry(TimingsRecord $record) : void {
		if (TimingsHandler::isEnabled() && TimingsHandler::isTimelineEnabled()) {
			self::$currentTimeline?->endEntry($record);
		}
	}
}