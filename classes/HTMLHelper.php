<?php

namespace Modules\HostTree\Classes;

class HTMLHelper {
    public static function NBSP(int $times = 1) {
        $nbsp = str_repeat('&nbsp;', $times);
        return new CHtmlEntityBG($nbsp);
    }
}

class CHtmlEntityBG
{
	private $entity = '';
	public function __construct(string $entity)
	{
		$this->entity = $entity;
	}
	public function toString(): string
	{
		return $this->entity;
	}
}
