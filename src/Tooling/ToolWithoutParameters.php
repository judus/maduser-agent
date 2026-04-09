<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use stdClass;

trait ToolWithoutParameters
{
    /**
     * @return array{type: 'object', properties: stdClass}
     */
    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new stdClass(),
        ];
    }
}
