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
use pocketmine\data\bedrock\block\BlockTypeNames;
use pocketmine\nbt\BigEndianNbtSerializer;
use pocketmine\nbt\NBT;
use pocketmine\nbt\NbtDataException;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\utils\Utils;
use function array_key_first;
use function count;
use function get_debug_type;
use function is_array;
use function is_int;
use function is_string;
use function json_decode;
use function ord;
use function strlen;
use function substr;
use function unpack;
use function zlib_decode;
use const JSON_THROW_ON_ERROR;

/**
 * Handles translation of network block runtime IDs into blockstate data, and vice versa
 */
final class BlockStateDictionary{
	/**
	 * @var int[][]|int[]
	 * @phpstan-var array<string, array<string, int>|int>
	 */
	private array $stateDataToStateIdLookup = [];

	/**
	 * @var int[][]|null
	 * @phpstan-var array<string, array<int, int>|int>|null
	 */
	private ?array $idMetaToStateIdLookupCache = null;

	/**
	 * @param BlockStateDictionaryEntry[] $states
	 *
	 * @phpstan-param list<BlockStateDictionaryEntry> $states
	 */
	public function __construct(
		private array $states
	){
		$table = [];
		foreach($this->states as $stateId => $stateNbt){
			$table[$stateNbt->getStateName()][$stateNbt->getRawStateProperties()] = $stateId;
		}

		//setup fast path for stateless blocks
		foreach(Utils::stringifyKeys($table) as $name => $stateIds){
			if(count($stateIds) === 1){
				$this->stateDataToStateIdLookup[$name] = $stateIds[array_key_first($stateIds)];
			}else{
				$this->stateDataToStateIdLookup[$name] = $stateIds;
			}
		}
	}

	/**
	 * @return int[][]
	 * @phpstan-return array<string, array<int, int>|int>
	 */
	private function getIdMetaToStateIdLookup() : array{
		if($this->idMetaToStateIdLookupCache === null){
			$table = [];
			//TODO: if we ever allow mutating the dictionary, this would need to be rebuilt on modification

			foreach($this->states as $i => $state){
				$table[$state->getStateName()][$state->getMeta()] = $i;
			}

			$this->idMetaToStateIdLookupCache = [];
			foreach(Utils::stringifyKeys($table) as $name => $metaToStateId){
				//if only one meta value exists
				if(count($metaToStateId) === 1){
					$this->idMetaToStateIdLookupCache[$name] = $metaToStateId[array_key_first($metaToStateId)];
				}else{
					$this->idMetaToStateIdLookupCache[$name] = $metaToStateId;
				}
			}
		}

		return $this->idMetaToStateIdLookupCache;
	}

	public function generateDataFromStateId(int $networkRuntimeId) : ?BlockStateData{
		return ($this->states[$networkRuntimeId] ?? null)?->generateStateData();
	}

	/**
	 * Returns the FNV1a-32 network hash of the blockstate at the given dictionary state ID, used as
	 * the wire runtime ID when blockNetworkIdsAreHashes is enabled (see BlockStateDictionaryEntry).
	 * The state ID is a dictionary index that always resolves (callers pass the result of a lookup or
	 * the fallback state ID), so a missing entry is a programming error.
	 */
	public function getNetworkHashFromStateId(int $stateId) : int{
		$entry = $this->states[$stateId] ?? throw new \InvalidArgumentException("Unknown blockstate ID $stateId");
		return $entry->getNetworkRuntimeHash();
	}

	/**
	 * Searches for the appropriate state ID which matches the given blockstate NBT.
	 * Returns null if there were no matches.
	 */
	public function lookupStateIdFromData(BlockStateData $data) : ?int{
		$name = $data->getName();

		$lookup = $this->stateDataToStateIdLookup[$name] ?? null;
		return match(true){
			$lookup === null => null,
			is_int($lookup) => $lookup,
			is_array($lookup) => $lookup[BlockStateDictionaryEntry::encodeStateProperties($data->getStates())] ?? null
		};
	}

	/**
	 * Returns the blockstate meta value associated with the given blockstate runtime ID.
	 * This is used for serializing crafting recipe inputs.
	 */
	public function getMetaFromStateId(int $networkRuntimeId) : ?int{
		return ($this->states[$networkRuntimeId] ?? null)?->getMeta();
	}

	/**
	 * Returns the blockstate data associated with the given block ID and meta value.
	 * This is used for deserializing crafting recipe inputs.
	 */
	public function lookupStateIdFromIdMeta(string $id, int $meta) : ?int{
		$metas = $this->getIdMetaToStateIdLookup()[$id] ?? null;
		return match(true){
			$metas === null => null,
			is_int($metas) => $metas,
			is_array($metas) => $metas[$meta] ?? null
		};
	}

	public function lookupDefaultStateIdFromId(string $id) : ?int{
		$metas = $this->getIdMetaToStateIdLookup()[$id] ?? null;
		return match(true){
			$metas === null => null,
			is_int($metas) => $metas,
			is_array($metas) => $metas[0] ?? $metas[array_key_first($metas)] ?? null
		};
	}

	/**
	 * Returns an array mapping runtime ID => blockstate data.
	 * @return BlockStateDictionaryEntry[]
	 * @phpstan-return array<int, BlockStateDictionaryEntry>
	 */
	public function getStates() : array{ return $this->states; }

	/**
	 * Walks the "blocks" list of the gzipped big-endian NBT block palette one entry at a time.
	 *
	 * Decoding the whole tree up front peaks at ~50 MB for a 4.5 MB palette, and every async worker that serializes
	 * chunks builds this dictionary, so we only ever hold one entry in memory here.
	 *
	 * @return \Generator<int, Tag, void, void>
	 */
	private static function streamBlocksFromString(string $blockPaletteContents) : \Generator{
		$raw = zlib_decode($blockPaletteContents);
		if($raw === false){
			throw new \InvalidArgumentException("Failed to decompress block palette");
		}
		$length = strlen($raw);
		$serializer = new BigEndianNbtSerializer();

		$readName = static function(int &$offset) use ($raw, $length) : string{
			if($offset + 2 > $length){
				throw new \InvalidArgumentException("Unexpected end of block palette");
			}
			$unpacked = unpack("n", $raw, $offset);
			if($unpacked === false){
				throw new \InvalidArgumentException("Unexpected end of block palette");
			}
			$nameLength = $unpacked[1];
			$name = substr($raw, $offset + 2, $nameLength);
			$offset += 2 + $nameLength;
			return $name;
		};

		$offset = 0;
		if($length === 0 || ord($raw[$offset++]) !== NBT::TAG_Compound){
			throw new \InvalidArgumentException("Block palette root must be a TAG_Compound");
		}
		$readName($offset);

		try{
			while($offset < $length){
				$type = ord($raw[$offset++]);
				if($type === NBT::TAG_End){
					break;
				}
				$name = $readName($offset);
				if($name !== "blocks" || $type !== NBT::TAG_List){
					//not ours, decode it only to step over it
					$serializer->readHeadless($raw, $type, $offset);
					continue;
				}

				if($offset + 5 > $length){
					throw new \InvalidArgumentException("Unexpected end of block palette");
				}
				$elementType = ord($raw[$offset]);
				$unpacked = unpack("N", $raw, $offset + 1);
				if($unpacked === false){
					throw new \InvalidArgumentException("Unexpected end of block palette");
				}
				$count = $unpacked[1];
				$offset += 5;
				if($count > 0 && $elementType !== NBT::TAG_Compound){
					throw new \InvalidArgumentException("Expected \"blocks\" to be a list of TAG_Compound");
				}
				for($i = 0; $i < $count; ++$i){
					yield $i => $serializer->readHeadless($raw, NBT::TAG_Compound, $offset);
				}
				return;
			}
		}catch(NbtDataException $e){
			throw new \InvalidArgumentException("Corrupted block palette: " . $e->getMessage(), 0, $e);
		}
		throw new \InvalidArgumentException("Missing \"blocks\" list in block palette");
	}

	/**
	 * Turns one streamed palette entry into its BlockStateData.
	 */
	private static function blockStateFromTag(int $i, Tag $blockTag) : BlockStateData{
		if(!($blockTag instanceof CompoundTag)){
			throw new \InvalidArgumentException("Invalid block palette entry at offset $i, expected TAG_Compound, got " . get_debug_type($blockTag));
		}
		$stateTag = $blockTag->getCompoundTag(BlockStateData::TAG_STATES) ??
			throw new \InvalidArgumentException("Missing states for block palette entry $i");
		return BlockStateData::current($blockTag->getString(BlockStateData::TAG_NAME), $stateTag->getValue());
	}

	/**
	 * @return BlockStateData[]
	 * @phpstan-return list<BlockStateData>
	 *
	 * @throws NbtDataException
	 */
	public static function loadPaletteFromString(string $blockPaletteContents) : array{
		$states = [];
		foreach(self::streamBlocksFromString($blockPaletteContents) as $i => $blockTag){
			$states[] = self::blockStateFromTag($i, $blockTag);
		}

		return $states;
	}

	public static function loadFromString(string $blockPaletteContents, string $metaMapContents) : self{
		$metaMap = json_decode($metaMapContents, flags: JSON_THROW_ON_ERROR);
		if(!is_array($metaMap)){
			throw new \InvalidArgumentException("Invalid metaMap, expected array for root type, got " . get_debug_type($metaMap));
		}

		$entries = [];

		$uniqueNames = [];

		//this hack allows the internal cache index to use interned strings which are already available in the
		//core code anyway, saving around 40 KB of memory
		foreach((new \ReflectionClass(BlockTypeNames::class))->getConstants() as $value){
			if(is_string($value)){
				$uniqueNames[$value] = $value;
			}
		}

		foreach(self::streamBlocksFromString($blockPaletteContents) as $i => $blockTag){
			$state = self::blockStateFromTag($i, $blockTag);
			$meta = $metaMap[$i] ?? null;
			if($meta === null){
				throw new \InvalidArgumentException("Missing associated meta value for state $i (" . $state->toNbt() . ")");
			}
			if(!is_int($meta)){
				throw new \InvalidArgumentException("Invalid metaMap offset $i, expected int, got " . get_debug_type($meta));
			}
			$uniqueName = $uniqueNames[$state->getName()] ??= $state->getName();
			//le generateur rend des cles 0..n-1 dans l'ordre, l'ajout en fin garde la meme liste
			$entries[] = new BlockStateDictionaryEntry($uniqueName, $state->getStates(), $meta);
		}

		return new self($entries);
	}
}
