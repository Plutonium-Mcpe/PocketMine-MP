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

namespace pocketmine\data\bedrock\blockstate\upgrade;

use pocketmine\data\bedrock\blockstate\upgrade\model\BlockStateUpgradeSchemaModel;
use pocketmine\data\bedrock\blockstate\upgrade\model\BlockStateUpgradeSchemaModelTag;
use pocketmine\data\bedrock\blockstate\upgrade\model\BlockStateUpgradeSchemaModelValueRemap;
use pocketmine\errorhandler\ErrorToExceptionHandler;
use pocketmine\nbt\tag\ByteTag;
use pocketmine\nbt\tag\IntTag;
use pocketmine\nbt\tag\StringTag;
use pocketmine\nbt\tag\Tag;
use pocketmine\utils\Utils;
use Webmozart\PathUtil\Path;
use function file_get_contents;
use function get_class;
use function implode;
use function is_int;
use function is_object;
use function is_string;
use function json_decode;
use function ksort;
use function var_dump;
use const JSON_THROW_ON_ERROR;
use const SORT_NUMERIC;

final class BlockStateUpgradeSchemaUtils{

	public static function describe(BlockStateUpgradeSchema $schema) : string{
		$lines = [];
		$lines[] = "Renames:";
		foreach($schema->renamedIds as $rename){
			$lines[] = "- $rename";
		}
		$lines[] = "Added properties:";
		foreach(Utils::stringifyKeys($schema->addedProperties) as $blockName => $tags){
			foreach(Utils::stringifyKeys($tags) as $k => $v){
				$lines[] = "- $blockName has $k added: $v";
			}
		}

		$lines[] = "Removed properties:";
		foreach(Utils::stringifyKeys($schema->removedProperties) as $blockName => $tagNames){
			foreach($tagNames as $tagName){
				$lines[] = "- $blockName has $tagName removed";
			}
		}
		$lines[] = "Renamed properties:";
		foreach(Utils::stringifyKeys($schema->renamedProperties) as $blockName => $tagNames){
			foreach(Utils::stringifyKeys($tagNames) as $oldTagName => $newTagName){
				$lines[] = "- $blockName has $oldTagName renamed to $newTagName";
			}
		}
		$lines[] = "Remapped property values:";
		foreach(Utils::stringifyKeys($schema->remappedPropertyValues) as $blockName => $remaps){
			foreach(Utils::stringifyKeys($remaps) as $tagName => $oldNewList){
				foreach($oldNewList as $oldNew){
					$lines[] = "- $blockName has $tagName value changed from $oldNew->old to $oldNew->new";
				}
			}
		}
		return implode("\n", $lines);
	}

	private static function tagToJsonModel(Tag $tag) : BlockStateUpgradeSchemaModelTag{
		$type = match(get_class($tag)){
			IntTag::class => "int",
			StringTag::class => "string",
			ByteTag::class => "byte",
			default => throw new \UnexpectedValueException()
		};

		return new BlockStateUpgradeSchemaModelTag($type, $tag->getValue());
	}

	private static function jsonModelToTag(BlockStateUpgradeSchemaModelTag $model) : Tag{
		if($model->type === "int"){
			if(!is_int($model->value)){
				throw new \UnexpectedValueException("Value for type int must be an int");
			}
			return new IntTag($model->value);
		}elseif($model->type === "byte"){
			if(!is_int($model->value)){
				throw new \UnexpectedValueException("Value for type byte must be an int");
			}
			return new ByteTag($model->value);
		}elseif($model->type === "string"){
			if(!is_string($model->value)){
				throw new \UnexpectedValueException("Value for type string must be a string");
			}
			return new StringTag($model->value);
		}else{
			throw new \UnexpectedValueException("Unknown blockstate value type $model->type");
		}
	}

	public static function fromJsonModel(BlockStateUpgradeSchemaModel $model) : BlockStateUpgradeSchema{
		$result = new BlockStateUpgradeSchema(
			$model->maxVersionMajor,
			$model->maxVersionMinor,
			$model->maxVersionPatch,
			$model->maxVersionRevision
		);
		$result->renamedIds = $model->renamedIds ?? [];
		$result->renamedProperties = $model->renamedProperties ?? [];
		$result->removedProperties = $model->removedProperties ?? [];

		foreach(Utils::stringifyKeys($model->addedProperties ?? []) as $blockName => $properties){
			foreach(Utils::stringifyKeys($properties) as $propertyName => $propertyValue){
				$result->addedProperties[$blockName][$propertyName] = self::jsonModelToTag($propertyValue);
			}
		}

		foreach(Utils::stringifyKeys($model->remappedPropertyValues ?? []) as $blockName => $properties){
			foreach(Utils::stringifyKeys($properties) as $property => $mappedValuesKey){
				foreach($mappedValuesKey as $oldNew){
					$result->remappedPropertyValues[$blockName][$property][] = new BlockStateUpgradeSchemaValueRemap(
						self::jsonModelToTag($oldNew->old),
						self::jsonModelToTag($oldNew->new)
					);
				}
			}
		}

		return $result;
	}

	public static function toJsonModel(BlockStateUpgradeSchema $schema) : BlockStateUpgradeSchemaModel{
		$result = new BlockStateUpgradeSchemaModel();
		$result->maxVersionMajor = $schema->maxVersionMajor;
		$result->maxVersionMinor = $schema->maxVersionMinor;
		$result->maxVersionPatch = $schema->maxVersionPatch;
		$result->maxVersionRevision = $schema->maxVersionRevision;
		$result->renamedIds = $schema->renamedIds;
		$result->renamedProperties = $schema->renamedProperties;
		$result->removedProperties = $schema->removedProperties;

		foreach(Utils::stringifyKeys($schema->addedProperties) as $blockName => $properties){
			foreach(Utils::stringifyKeys($properties) as $propertyName => $propertyValue){
				$result->addedProperties[$blockName][$propertyName] = self::tagToJsonModel($propertyValue);
			}
		}

		foreach(Utils::stringifyKeys($schema->remappedPropertyValues) as $blockName => $properties){
			foreach(Utils::stringifyKeys($properties) as $property => $propertyValues){
				foreach($propertyValues as $oldNew){
					$result->remappedPropertyValues[$blockName][$property][] = (array) new BlockStateUpgradeSchemaModelValueRemap(
						self::tagToJsonModel($oldNew->old),
						self::tagToJsonModel($oldNew->new)
					);
				}
			}
		}

		return $result;
	}

	/**
	 * Returns a list of schemas ordered by priority. Oldest schemas appear first.
	 *
	 * @return BlockStateUpgradeSchema[]
	 */
	public static function loadSchemas(string $path) : array{
		$iterator = new \RegexIterator(
			new \FilesystemIterator(
				$path,
				\FilesystemIterator::CURRENT_AS_PATHNAME | \FilesystemIterator::SKIP_DOTS | \FilesystemIterator::UNIX_PATHS
			),
			'/\/mapping_schema_(\d{4}).*\.json$/',
			\RegexIterator::GET_MATCH
		);

		$result = [];

		$jsonMapper = new \JsonMapper();
		/** @var string[] $matches */
		foreach($iterator as $matches){
			$filename = $matches[0];
			$priority = (int) $matches[1];

			var_dump($filename);

			$fullPath = Path::join($path, $filename);

			//TODO: should we bother handling exceptions in here?
			$raw = ErrorToExceptionHandler::trapAndRemoveFalse(fn() => file_get_contents($fullPath));

			$json = json_decode($raw, false, flags: JSON_THROW_ON_ERROR);
			if(!is_object($json)){
				throw new \RuntimeException("Unexpected root type of schema file $fullPath");
			}
			$model = $jsonMapper->map($json, new BlockStateUpgradeSchemaModel());

			$result[$priority] = self::fromJsonModel($model);
		}

		ksort($result, SORT_NUMERIC);
		return $result;
	}
}