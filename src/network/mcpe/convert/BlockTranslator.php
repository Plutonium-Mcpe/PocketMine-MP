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

namespace pocketmine\network\mcpe\convert;

use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\BlockStateSerializeException;
use pocketmine\data\bedrock\block\BlockStateSerializer;
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\utils\AssumptionFailedError;

/**
 * @internal
 */
final class BlockTranslator{
	/**
	 * When true, block runtime IDs written to the wire are FNV1a-32 hashes of the blockstate identity
	 * ({@see BlockStateDictionaryEntry::getNetworkRuntimeHash()}) instead of dictionary indices, and
	 * StartGamePacket advertises blockNetworkIdsAreHashes=true. This makes runtime IDs independent of
	 * palette ordering and size, so a client that keeps a previous server's palette across a
	 * transparent (proxy) transfer no longer mis-resolves blocks and crashes with the "Block" decode
	 * error. Set once at boot (before any player connects) from plugin config; a global mode flag
	 * keeps the change to a single point and stays trivially reversible without touching call sites.
	 */
	public static bool $blockNetworkIdsAreHashes = false;

	/**
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $networkIdCache = [];

	/**
	 * Cache of internal state ID => dictionary state ID (positional index). Kept separate from
	 * {@see $networkIdCache} because the index is still needed for reverse lookups
	 * ({@see internalIdToNetworkStateData()}) even when the wire ID is a hash.
	 *
	 * @var int[]
	 * @phpstan-var array<int, int>
	 */
	private array $stateIdCache = [];

	/** Used when a blockstate can't be correctly serialized (e.g. because it's unknown) */
	private BlockStateData $fallbackStateData;
	private int $fallbackStateId;

	public function __construct(
		private BlockStateDictionary $blockStateDictionary,
		private BlockStateSerializer $blockStateSerializer
	){
		$this->fallbackStateData = BlockStateData::current(BlockTypeNames::INFO_UPDATE, []);
		$this->fallbackStateId = $this->blockStateDictionary->lookupStateIdFromData($this->fallbackStateData) ??
			throw new AssumptionFailedError(BlockTypeNames::INFO_UPDATE . " should always exist");
	}

	/**
	 * Resolves the dictionary state ID (positional index) for an internal state ID. This is the value
	 * used for reverse lookups and, in index mode, is also the wire runtime ID. Falls back to the
	 * info_update state when the blockstate can't be serialized.
	 */
	private function internalIdToNetworkStateId(int $internalStateId) : int{
		if(isset($this->stateIdCache[$internalStateId])){
			return $this->stateIdCache[$internalStateId];
		}

		try{
			$blockStateData = $this->blockStateSerializer->serialize($internalStateId);

			$stateId = $this->blockStateDictionary->lookupStateIdFromData($blockStateData);
			if($stateId === null){
				throw new AssumptionFailedError("Unmapped blockstate returned by blockstate serializer: " . $blockStateData->toNbt());
			}
		}catch(BlockStateSerializeException){
			//TODO: this will swallow any error caused by invalid block properties; this is not ideal, but it should be
			//covered by unit tests, so this is probably a safe assumption.
			$stateId = $this->fallbackStateId;
		}

		return $this->stateIdCache[$internalStateId] = $stateId;
	}

	public function internalIdToNetworkId(int $internalStateId) : int{
		if(isset($this->networkIdCache[$internalStateId])){
			return $this->networkIdCache[$internalStateId];
		}

		$stateId = $this->internalIdToNetworkStateId($internalStateId);
		//In hash mode the wire runtime ID is the blockstate's identity hash rather than its palette
		//index, so it stays stable across servers with different custom-block sets (see the static
		//$blockNetworkIdsAreHashes doc). Every call site that writes a block runtime ID to a client
		//goes through this method, so this single branch covers chunks, UpdateBlock, particles, etc.
		$networkId = self::$blockNetworkIdsAreHashes
			? $this->blockStateDictionary->getNetworkHashFromStateId($stateId)
			: $stateId;

		return $this->networkIdCache[$internalStateId] = $networkId;
	}

	/**
	 * Converts a dictionary state ID (positional index) into the wire runtime ID, applying the hash
	 * transform when in hash mode. For the rare call sites that already hold a dictionary state ID
	 * (e.g. a synthetic/fake blockstate looked up directly via the dictionary) rather than an internal
	 * state ID, so they don't accidentally write a raw index while chunks write hashes.
	 */
	public function networkStateIdToNetworkId(int $networkStateId) : int{
		return self::$blockNetworkIdsAreHashes
			? $this->blockStateDictionary->getNetworkHashFromStateId($networkStateId)
			: $networkStateId;
	}

	/**
	 * Looks up the network state data associated with the given internal state ID.
	 */
	public function internalIdToNetworkStateData(int $internalStateId) : BlockStateData{
		//we don't directly use the blockstate serializer here - we can't assume that the network blockstate NBT is the
		//same as the disk blockstate NBT, in case we decide to have different world version than network version (or in
		//case someone wants to implement multi version).
		//NB: this must use the dictionary state ID (index), NOT internalIdToNetworkId() — in hash mode
		//the latter returns a hash, which is not a valid index into the dictionary.
		$stateId = $this->internalIdToNetworkStateId($internalStateId);

		return $this->blockStateDictionary->generateDataFromStateId($stateId) ?? throw new AssumptionFailedError("We just looked up this state ID, so it must exist");
	}

	public function getBlockStateDictionary() : BlockStateDictionary{ return $this->blockStateDictionary; }

	public function getFallbackStateData() : BlockStateData{ return $this->fallbackStateData; }
}
