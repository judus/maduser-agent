<?php

declare(strict_types=1);

namespace Maduser\Agent\Support;

use Maduser\Agent\LLM\Message\AssistantMessage;
use Maduser\Agent\LLM\Message\Contracts\MessageInterface;
use Maduser\Agent\LLM\Message\SystemMessage;
use Maduser\Agent\Tooling\ToolResult;

use function array_map;
use function array_merge;
use function array_unshift;
use function count;
use function in_array;

final class HistoryTrimmer
{
    /**
     * @param list<MessageInterface> $history
     * @return list<MessageInterface>
     */
    public static function safeTail(array $history, int $maxTurns = 20, ?SystemMessage $system = null): array
    {
        $chunks = self::buildChunks($history);
        $result = [];
        $used = 0;

        for ($i = count($chunks) - 1; $i >= 0; $i--) {
            $chunk = $chunks[$i];
            $len = count($chunk);

            if ($len === 1 && $chunk[0] instanceof ToolResult) {
                continue;
            }

            if ($used + $len > $maxTurns) {
                break;
            }

            $result = array_merge($chunk, $result);
            $used += $len;

            if ($used >= $maxTurns) {
                break;
            }
        }

        if ($system !== null) {
            array_unshift($result, $system);
        }

        return $result;
    }

    /**
     * @param list<MessageInterface> $history
     * @return list<list<MessageInterface>>
     */
    private static function buildChunks(array $history): array
    {
        $chunks = [];
        $count = count($history);

        for ($i = 0; $i < $count; $i++) {
            $message = $history[$i];

            if ($message instanceof ToolResult) {
                continue;
            }

            if ($message instanceof AssistantMessage && $message->toolCalls !== null && $message->toolCalls !== []) {
                $chunk = [$message];
                $callIds = array_map(
                    static fn ($call): string => $call->id,
                    $message->toolCalls,
                );
                $matchedResults = 0;
                $nextIndex = $i + 1;

                while ($nextIndex < $count) {
                    $next = $history[$nextIndex];

                    if ($next instanceof ToolResult && in_array($next->callId, $callIds, true)) {
                        $chunk[] = $next;
                        $matchedResults++;
                        $nextIndex++;
                        continue;
                    }

                    break;
                }

                if ($matchedResults > 0) {
                    $chunks[] = $chunk;
                    $i = $nextIndex - 1;
                }

                continue;
            }

            $chunks[] = [$message];
        }

        return $chunks;
    }
}
