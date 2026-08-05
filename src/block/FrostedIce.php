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

namespace pocketmine\block;

use pocketmine\block\utils\Ageable;
use pocketmine\block\utils\AgeableTrait;
use pocketmine\block\utils\BlockEventHelper;
use function mt_rand;

class FrostedIce extends Ice implements Ageable{
	use AgeableTrait;

	public const MAX_AGE = 3;

	public function onNearbyBlockChange() : void{
		$world = $this->position->getWorld();
		//scheduleDelayedBlockUpdate() n'ecarte le doublon que si le delai en attente
		//est plus court, et les deux tirent dans 20-40 ticks: sans ce garde, une fois
		//sur deux on inserait une entree supplementaire dans la file sans jamais
		//retirer l'ancienne. Dans un champ dense chaque fonte touche six voisins qui
		//en injectaient chacun une.
		if($world->hasScheduledBlockUpdate($this->position)){
			return;
		}
		$world->scheduleDelayedBlockUpdate($this->position, mt_rand(20, 40));
	}

	public function onRandomTick() : void{
		$world = $this->position->getWorld();
		//Le tirage est evalue en premier: il vaut le meme resultat booleen mais
		//court-circuite checkAdjacentBlocks() une fois sur trois, et donc ses
		//huit getBlockAt(). Sur un champ de glace dense c'est le poste le plus
		//cher de la mise a jour.
		//
		//Pas de replanification quand la glace ne fond pas: la file d'updates
		//n'est pas persistee, elle repart vide a chaque demarrage et seul le
		//random tick y injecte de la glace. Se replanifier soi-meme faisait de
		//cette injection un cliquet — un bloc recrute n'en sortait jamais tant
		//qu'il ne fondait pas — donc la file ne faisait que grossir avec
		//l'uptime. Sans elle, la glace n'est plus vue que par le random tick,
		//qui est plafonne a 3 blocs par sous-chunk et par tick.
		if((mt_rand(0, 2) === 0 || !$this->checkAdjacentBlocks(4)) &&
			$world->getHighestAdjacentFullLightAt($this->position->x, $this->position->y, $this->position->z) >= 12 - $this->age){
			if($this->tryMelt()){
				foreach($this->getAllSides() as $block){
					if($block instanceof FrostedIce){
						$block->tryMelt();
					}
				}
			}
		}
	}

	public function onScheduledUpdate() : void{
		$this->onRandomTick();
	}

	private function checkAdjacentBlocks(int $requirement) : bool{
		$found = 0;
		for($x = -1; $x <= 1; ++$x){
			for($z = -1; $z <= 1; ++$z){
				if($x === 0 && $z === 0){
					continue;
				}
				if(
					$this->position->getWorld()->getBlockAt($this->position->x + $x, $this->position->y, $this->position->z + $z) instanceof FrostedIce &&
					++$found >= $requirement
				){
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Updates the age of the ice, destroying it if appropriate.
	 *
	 * @return bool Whether the ice was destroyed.
	 */
	private function tryMelt() : bool{
		$world = $this->position->getWorld();
		if($this->age >= self::MAX_AGE){
			BlockEventHelper::melt($this, VanillaBlocks::WATER());
			return true;
		}

		$this->age++;
		$world->setBlock($this->position, $this);
		$world->scheduleDelayedBlockUpdate($this->position, mt_rand(20, 40));
		return false;
	}

	public function isAffectedBySilkTouch() : bool{
		return false;
	}
}
