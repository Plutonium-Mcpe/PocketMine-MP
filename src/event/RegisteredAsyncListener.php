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

namespace pocketmine\event;

use pocketmine\plugin\Plugin;
use pocketmine\promise\Promise;
use pocketmine\timings\TimingsHandler;

class RegisteredAsyncListener extends RegisteredListener{
	/**
	 * @phpstan-param \Closure(AsyncEvent) : Promise<null> $handler
	 */
	public function __construct(
		protected \Closure $handler,
		int $priority,
		Plugin $plugin,
		bool $handleCancelled,
		private bool $exclusiveCall,
		protected TimingsHandler $timings
	){
		parent::__construct($handler, $priority, $plugin, $handleCancelled, $timings);
	}

	public function canBeCalledConcurrently() : bool{
		return !$this->exclusiveCall;
	}

	public function callEvent(Event $event) : void{
		throw new \BadMethodCallException("Cannot call async event synchronously, use callAsync() instead");
	}

	/**
	 * @phpstan-return Promise<null>|null
	 */
	public function callAsync(AsyncEvent $event) : ?Promise{
		if($event instanceof Cancellable && $event->isCancelled() && !$this->isHandlingCancelled()){
			return null;
		}
		$this->timings->startTiming();
		try{
			return ($this->handler)($event);
		}finally{
			$this->timings->stopTiming();
		}
	}
}
