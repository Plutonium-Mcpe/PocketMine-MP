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

namespace pocketmine\timings;

use PHPUnit\Framework\TestCase;
use pocketmine\promise\PromiseResolver;
use pocketmine\Server;
use function hrtime;
use function preg_match;
use function usleep;

final class TimingsHandlerTest extends TestCase{

	protected function setUp() : void{
		//the report footer only reads constants through the server, so an instance without constructor is enough
		$server = (new \ReflectionClass(Server::class))->newInstanceWithoutConstructor();
		(new \ReflectionProperty(Server::class, "instance"))->setValue(null, $server);
		TimingsHandler::setEnabled();
	}

	protected function tearDown() : void{
		TimingsHandler::setEnabled(false);
		(new \ReflectionProperty(Server::class, "instance"))->setValue(null, null);
	}

	public function testSampleTimeExcludesWaitForOtherThreads() : void{
		$timer = new TimingsHandler("Test");
		$timer->startTiming();
		$timer->stopTiming();

		/** @phpstan-var PromiseResolver<list<string>> $otherThread */
		$otherThread = new PromiseResolver();
		$callback = fn() : array => [$otherThread->getPromise()];
		TimingsHandler::getCollectCallbacks()->add($callback);
		try{
			$lines = null;
			TimingsHandler::requestPrintTimings()->onCompletion(
				function(array $result) use (&$lines) : void{
					$lines = $result;
				},
				function() : void{
					self::fail("Timings collection should not be rejected");
				}
			);
			$requestedAfter = hrtime(true) - TimingsHandler::getStartTime();

			//another thread answering late
			usleep(100_000);
			$otherThread->resolve([]);
		}finally{
			TimingsHandler::getCollectCallbacks()->remove($callback);
		}

		self::assertIsArray($lines);
		$sampleTime = null;
		foreach($lines as $line){
			if(preg_match('/^Sample time (\d+) /', $line, $matches) === 1){
				$sampleTime = (int) $matches[1];
			}
		}
		self::assertNotNull($sampleTime, "Report has no sample time");
		self::assertLessThanOrEqual($requestedAfter, $sampleTime);
	}
}
