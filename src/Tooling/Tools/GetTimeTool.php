<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Tools;

use DateTimeImmutable;
use DateTimeZone;
use Exception;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolInterface;
use Override;
use stdClass;

final class GetTimeTool implements ToolInterface
{
    #[Override]
    public static function name(): string
    {
        return 'get_time';
    }

    #[Override]
    public static function description(): string
    {
        return 'Returns the current date and time in ISO 8601 format.';
    }

    #[Override]
    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => new stdClass(),
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @throws Exception
     */
    #[Override]
    public function execute(array $args, ?AgentContext $context = null): string|array
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format(DATE_RFC3339);
    }
}
