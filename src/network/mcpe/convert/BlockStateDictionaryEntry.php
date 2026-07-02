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
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\nbt\TreeRoot;
use pocketmine\utils\Utils;
use function count;
use function ksort;
use function ord;
use function strlen;
use const SORT_STRING;

final class BlockStateDictionaryEntry{
	/**
	 * @var string[]
	 * @phpstan-var array<string, string>
	 */
	private static array $uniqueRawStates = [];

	private string $rawStateProperties;

	/**
	 * Lazily-computed network runtime ID hash of this blockstate, used when the server advertises
	 * blockNetworkIdsAreHashes=true in StartGame. See {@see computeNetworkRuntimeHash()}.
	 */
	private ?int $networkRuntimeHash = null;

	/**
	 * @param Tag[] $stateProperties
	 * @phpstan-param array<string, Tag> $stateProperties
	 */
	public function __construct(
		private string $stateName,
		array $stateProperties,
		private int $meta
	){
		$rawStateProperties = self::encodeStateProperties($stateProperties);
		$this->rawStateProperties = self::$uniqueRawStates[$rawStateProperties] ??= $rawStateProperties;
	}

	public function getStateName() : string{ return $this->stateName; }

	public function getRawStateProperties() : string{ return $this->rawStateProperties; }

	/**
	 * Returns the FNV1a-32 hash the Bedrock client uses as this blockstate's runtime ID when
	 * blockNetworkIdsAreHashes is enabled. Because the ID is derived from the state's identity
	 * (its {name, states} NBT) rather than its position in the palette, it is stable across servers
	 * regardless of how many custom blocks each one registers or in what order — which removes the
	 * whole class of "Block" client decode crashes on transparent cross-server transfers, where the
	 * client keeps the first server's palette. Lazily computed and cached (entries are immutable).
	 */
	public function getNetworkRuntimeHash() : int{
		return $this->networkRuntimeHash ??= self::computeNetworkRuntimeHash($this->stateName, $this->rawStateProperties);
	}

	/**
	 * Computes the block state network hash exactly as the client does: FNV1a-32 over the
	 * little-endian NBT of a compound holding only "name" and a key-sorted "states" compound (no
	 * "version" field). Matches the reference implementation used by the Bedrock client / gophertunnel.
	 */
	private static function computeNetworkRuntimeHash(string $name, string $rawStateProperties) : int{
		if($name === "minecraft:unknown"){
			return -2;
		}

		//$rawStateProperties is the states compound already serialized in key-sorted order by
		//encodeStateProperties(), so decoding it back yields a compound whose insertion order matches
		//the client's TreeMap ordering — no re-sort needed.
		$statesTag = $rawStateProperties === ""
			? new CompoundTag()
			: (new LittleEndianNbtSerializer())->read($rawStateProperties)->mustGetCompoundTag();

		$root = CompoundTag::create()
			->setString("name", $name)
			->setTag("states", $statesTag);
		$buffer = (new LittleEndianNbtSerializer())->write(new TreeRoot($root));

		return self::fnv1a32($buffer);
	}

	/**
	 * FNV1a-32 over the raw bytes, returned as a signed 32-bit int (block runtime IDs are written
	 * to the wire as signed ints). Requires 64-bit PHP (guaranteed by PocketMine) so the intermediate
	 * multiplication never overflows into a float before the 32-bit mask.
	 */
	private static function fnv1a32(string $data) : int{
		$hash = 0x811c9dc5;
		for($i = 0, $len = strlen($data); $i < $len; ++$i){
			$hash ^= ord($data[$i]);
			$hash = ($hash * 0x01000193) & 0xffffffff;
		}
		if($hash >= 0x80000000){
			$hash -= 0x100000000;
		}

		return $hash;
	}

	public function generateStateData() : BlockStateData{
		return new BlockStateData(
			$this->stateName,
			self::decodeStateProperties($this->rawStateProperties),
			BlockStateData::CURRENT_VERSION
		);
	}

	public function getMeta() : int{ return $this->meta; }

	/**
	 * @return Tag[]
	 */
	public static function decodeStateProperties(string $rawProperties) : array{
		if($rawProperties === ""){
			return [];
		}
		return (new LittleEndianNbtSerializer())->read($rawProperties)->mustGetCompoundTag()->getValue();
	}

	/**
	 * @param Tag[] $properties
	 * @phpstan-param array<string, Tag> $properties
	 */
	public static function encodeStateProperties(array $properties) : string{
		if(count($properties) === 0){
			return "";
		}
		//TODO: make a more efficient encoding - NBT will do for now, but it's not very compact
		ksort($properties, SORT_STRING);
		$tag = new CompoundTag();
		foreach(Utils::stringifyKeys($properties) as $k => $v){
			$tag->setTag($k, $v);
		}
		return (new LittleEndianNbtSerializer())->write(new TreeRoot($tag));
	}
}
