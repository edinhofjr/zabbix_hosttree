<?php

namespace Modules\HostTree\Services;

class HostGroupCache {
    private const CACHE_FILE = '/tmp/zbx_hosttree_hostgroups.json';
    private const TTL = 60; // segundos

    public static function get(): ?array {
        $file = self::CACHE_FILE;

        if (!is_file($file)) {
            return null;
        }

        if (filemtime($file) + self::TTL <= time()) {
            unlink($file);
            return null;
        }

        $raw = file_get_contents($file);

        if ($raw === false) {
            return null;
        }

        $data = json_decode($raw, true);

        return is_array($data) ? $data : null;
    }

    public static function set(array $groups): array {
        file_put_contents(self::CACHE_FILE, json_encode($groups));

        return $groups;
    }

    public static function invalidate(): void {
        if (is_file(self::CACHE_FILE)) {
            unlink(self::CACHE_FILE);
        }
    }
}
