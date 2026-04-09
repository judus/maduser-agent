<?php

declare(strict_types=1);

namespace Maduser\Agent;

use JsonSerializable;
use Maduser\Agent\LLM\LLMResponse;

final readonly class AgentResponse implements JsonSerializable
{
    /**
     * @param list<array<string, mixed>> $trace
     * @param list<array<string, mixed>> $artifacts
     */
    public function __construct(
        public string $text,
        public array $trace,
        public ?LLMResponse $response = null,
        public array $artifacts = [],
    ) {
    }

    /**
     * @return array{
     *     text: string,
     *     trace: list<array<string, mixed>>,
     *     artifacts: list<array<string, mixed>>,
     *     response?: LLMResponse
     * }
     */
    public function toArray(): array
    {
        $payload = [
            'text' => $this->text,
            'trace' => $this->trace,
            'artifacts' => $this->artifacts,
        ];

        if ($this->response instanceof LLMResponse) {
            $payload['response'] = $this->response;
        }

        return $payload;
    }

    #[\Override]
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
