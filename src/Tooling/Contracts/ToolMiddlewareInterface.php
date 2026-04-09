<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Contracts;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolCall;
use Maduser\Agent\Tooling\ToolResult;

interface ToolMiddlewareInterface
{
    /**
     * @param callable(ToolCall): ToolResult $next
     */
    public function process(ToolCall $call, callable $next, ?AgentContext $context = null): ToolResult;
}
