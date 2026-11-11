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

namespace pocketmine\block\utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use function str_repeat;

class AnvilHelperTest extends TestCase{

	/**
	 * @phpstan-return \Generator<string, array{string, bool}, void, void>
	 */
	public static function customNameProvider() : \Generator{
		yield "plain" => ["Excalibur", true];
		yield "empty" => ["", true];
		yield "accents and symbols" => ["Épée du Néant ★", true];
		yield "colour codes" => ["§6Pioche §lsacrée", true];
		yield "longest accepted" => [str_repeat("é", AnvilHelper::MAX_CUSTOM_NAME_LENGTH), true];
		yield "one character too long" => [str_repeat("é", AnvilHelper::MAX_CUSTOM_NAME_LENGTH + 1), false];
		yield "largest name an NBT string can hold" => [str_repeat("a", 32767), false];
		yield "larger than an NBT string can hold" => [str_repeat("a", 32768), false];
		yield "invalid UTF-8" => ["abc\xff\xfe", false];
		yield "truncated UTF-8 sequence" => ["abc\xc3", false];
		yield "line break" => ["ligne 1\nligne 2", false];
		yield "null byte" => ["a\x00b", false];
		yield "delete character" => ["a\x7fb", false];
	}

	#[DataProvider("customNameProvider")]
	public function testIsValidCustomName(string $customName, bool $expected) : void{
		self::assertSame($expected, AnvilHelper::isValidCustomName($customName));
	}
}
