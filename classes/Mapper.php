<?php

namespace Modules\HostTree\Classes;
use Modules\HostTree\Classes\HostGroup;

class Mapper
{
    /**
     * @return HostGroup[]
     */
    public static function DecodeTo(array $jsonArray, string $class): array {
        $objs = [];

        foreach ($jsonArray as $json) {
            $objs[] = $class::decode($json);
        }
        
        return $objs;
    }
}
