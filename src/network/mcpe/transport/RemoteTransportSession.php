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

use altay\network\ipc\MainToTransportThreadMessageSender;
use altay\network\transport\TransportSession;

final class RemoteTransportSession implements TransportSession{

	private int $ping = -1;
	private bool $connected = true;

	public function __construct(
		private MainToTransportThreadMessageSender $commandSender,
		private int $sessionId,
		private string $address,
		private int $port,
		private ?string $authenticatedPublicKey = null
	){}

	public function getId() : int{
		return $this->sessionId;
	}

	public function getAddress() : string{
		return $this->address;
	}

	public function getPort() : int{
		return $this->port;
	}

	public function getPing() : int{
		return $this->ping;
	}

	public function getAuthenticatedPublicKey() : ?string{
		return $this->authenticatedPublicKey;
	}

	public function updatePing(int $ping) : void{
		$this->ping = $ping;
	}

	public function isConnected() : bool{
		return $this->connected;
	}

	public function markDisconnected() : void{
		$this->connected = false;
	}

	public function sendPacket(string $payload, bool $immediate = false, ?int $receiptId = null) : void{
		if($this->connected){
			$this->commandSender->sendPacket($this->sessionId, $payload, $immediate, $receiptId);
		}
	}

	public function disconnect() : void{
		if($this->connected){
			$this->connected = false;
			$this->commandSender->closeSession($this->sessionId);
		}
	}
}
