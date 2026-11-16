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

namespace pocketmine\network\mcpe\transport;

use altay\network\raknet\RakNetTransport;
use altay\network\transport\Transport;

final class RakNetTransportFactory implements TransportFactory{

	/**
	 * @phpstan-param positive-int $maxSplitPacketParts
	 * @phpstan-param positive-int $maxConcurrentSplitPackets
	 */
	public function __construct(
		private string $ip,
		private int $port,
		private bool $ipV6,
		private int $maxMtuSize,
		private int $serverId,
		private int $maxSplitPacketParts,
		private int $maxConcurrentSplitPackets
	){}

	public function getName() : string{
		return "raknet";
	}

	public function getServerId() : int{
		return $this->serverId;
	}

	public function make(\Logger $logger) : Transport{
		return new RakNetTransport(
			$logger,
			$this->ip,
			$this->port,
			$this->ipV6,
			$this->maxMtuSize,
			RakNetTransport::BEDROCK_RAKNET_PROTOCOL_VERSION,
			$this->serverId,
			$this->maxSplitPacketParts,
			$this->maxConcurrentSplitPackets
		);
	}
}
