<?php

namespace pocketmine\block\utils;

trait NoneSupportTrait{
	public function getSupportType(int $facing): SupportType
	{
		return SupportType::NONE;
	}
}