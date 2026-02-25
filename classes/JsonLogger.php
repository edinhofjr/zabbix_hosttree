<?php

namespace Modules\HostTree\Classes;

use CScriptTag;

class JsonLogger {
    public static function log($data) {
        $encodedData = json_encode(
            $data,
            JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
        );

        if ($encodedData === false) {
            $encodedData = '"[JsonLogger] failed to encode payload"';
        }

        (new CScriptTag(
            "console.log(" . $encodedData . ");"
        ))->show();
    }
}
