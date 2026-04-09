<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling;

use Maduser\Agent\Context\AgentContext;

interface ToolInterface
{
    public static function name(): string;

    public static function description(): string;

    /**
     * @return array<string, mixed>
     */
    public static function parameters(): array;

    /**
     * @param array<string, mixed> $args
     */
    public function execute(array $args, ?AgentContext $context = null): string|array;
}
