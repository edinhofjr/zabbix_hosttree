<?php

namespace Modules\HostTree\Classes;

use Modules\HostTree\Classes\Interfaces\JsonDecoder;

class HostGroup implements JsonDecoder {
    public function __construct(
    public int $id,
    public string $name,
    ) {}

    public static function decode($json) {
        return new HostGroup(
            $json["groupid"],
            $json["name"]
        );   
    }
}