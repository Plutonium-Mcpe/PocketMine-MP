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

namespace pocketmine\network\mcpe\handler;

use pocketmine\network\mcpe\protocol\PacketViolationWarningPacket;
use function strlen;

trait PacketViolationWarningTrait{

	public function handlePacketViolationWarning(PacketViolationWarningPacket $packet) : bool{
		$message = $packet->getMessage();
		//the message comes straight from the client, so anything longer than this is dropped to keep a malicious
		//client from flooding the debug log
		if(strlen($message) > 100){
			return true;
		}

		$this->session->getLogger()->debug(
			"Client reported packet violation (type " . $packet->getType() .
			", severity " . $packet->getSeverity() .
			", packet ID " . $packet->getPacketId() . "): " . $message
		);
		return true;
	}
}
