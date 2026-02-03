<?php

namespace Modules\HostTree\Services;

use API;

class HostTreeAPIService {
    public static function getAllHostGroups() {
        return API::HostGroup()->get([
			'output' => ['groupid', 'name'],
			'sortfield' => 'name',
		]);
    }

    public static function getHostTreeByHostGroupId($hostGroupId) {
        $hosts = self::getAllHostsByHostGroupId($hostGroupId);
        $hostTree = []; 
        
        foreach ($hosts as $host) {
            $tags = $host["tags"];
        
            foreach ($tags as $tag) {
                if ($tag["tag"] != "ponto") continue;
                
                $hostTree[$tag["value"]][] = $host;
            }
        }

        return $hostTree;
    }

    public static function hasTag($key, $value, $tagArray) {
        foreach( $tagArray as $tag) {

        }
    }

    public static function getTag($key, $tagArray) {
        foreach($tagArray as $tag) {

        }
    }

    public static function getAllHostsByHostGroupId($hostGroupId) {
        return API::Host()->get([
			'output' => ['hostid', 'name'],
			'selectTags' => ['tag', 'value'],	
			'groupids' => $hostGroupId,
			'tags' => [
				[
					'tag' => 'ponto',
					'operator' => 4
				]
			],
		]);
    }
}