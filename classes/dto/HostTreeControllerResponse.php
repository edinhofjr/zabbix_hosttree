<?php

namespace Modules\HostTree\Classes\Dto;

class HostTreeControllerResponse {
    /**
     * @var HostGroup[] $hostGroups
     */
    public function __construct(
        public string $status,
        public array $hostGroups
    ) {}
}