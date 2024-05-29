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

namespace pocketmine\command\defaults;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\lang\KnownTranslationFactory;
use pocketmine\permission\DefaultPermissionNames;
use pocketmine\promise\Promise;
use pocketmine\promise\PromiseResolver;
use function microtime;
use function round;

class SaveCommand extends VanillaCommand{

	public function __construct(){
		parent::__construct(
			"save-all",
			KnownTranslationFactory::pocketmine_command_save_description()
		);
		$this->setPermission(DefaultPermissionNames::COMMAND_SAVE_PERFORM);
	}

	public function execute(CommandSender $sender, string $commandLabel, array $args){
		Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_save_start());
		$start = microtime(true);

		$promises = [];
		foreach($sender->getServer()->getOnlinePlayers() as $player){
			$promises[] = $player->saveAsync();
		}

		$resolver = new PromiseResolver();

		if(count($promises) === 0){
			$resolver->resolve(null);
		} else {
			Promise::all($promises)->onCompletion(
				fn () => $resolver->resolve(null),
				fn () => $resolver->reject()
			);
		}

		$resolver->getPromise()->onCompletion(
			function () use ($sender, $start) : void {
				foreach($sender->getServer()->getWorldManager()->getWorlds() as $world){
					$world->save(true);
				}

				Command::broadcastCommandMessage($sender, KnownTranslationFactory::pocketmine_save_success((string) round(microtime(true) - $start, 3)));
			},
			fn() => Command::broadcastCommandMessage($sender, "§cUnable to save the server")
		);

		return true;
	}
}
