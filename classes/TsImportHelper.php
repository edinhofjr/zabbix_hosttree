<?php

namespace Modules\HostTree\Classes;

use InvalidArgumentException;

class TsImportHelper {
    /**
     * Reads a transpiled JS file and prints it inside a script tag.
     *
     * @param object      $context   CPartial or CView instance.
     * @param string      $fileName  JavaScript file located in partial/view js directory.
     * @param array|null  $data      Optional data exposed when reading the file.
     */
    public static function import(object $context, string $fileName, ?array $data = null): void {
        if (!method_exists($context, 'readJsFile')) {
            throw new InvalidArgumentException('Context must provide readJsFile().');
        }

        $js = $context->readJsFile($fileName, $data);

        echo '<script type="text/javascript">'."\n";
        echo $js;
        echo "\n".'</script>';
    }
}
