# Usage Examples

This file shows how `maduser/agent` is intended to be used today, and how it is likely to be used later in `companion-ai`.

## 1. Smallest Useful Example

Use Agent with the built-in defaults:
- `llm-client` provides the OpenAI Responses client and built-in cURL transport
- Agent provides the default workflow and an empty tool pipeline if you do not need tools yet

```php
<?php

declare(strict_types=1);

use Maduser\Agent\Agent;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\LLM\LLMClient;

$llm = LLMClient::openAiResponses(
    apiKey: $_ENV['LLM_API_KEY'],
    defaultModel: $_ENV['LLM_MODEL'] ?? 'gpt-5.4',
    baseUrl: $_ENV['LLM_BASE_URL'] ?? 'https://api.openai.com',
);

$alix = Agent::createDefault($llm);

$response = $alix
    ->withContext(new AgentContext(
        conversationStateRepository: new InMemoryConversationStateRepository(),
        id: 'demo-session',
    ))
    ->ask('Send me one inspiring quote.');

echo $response->text . PHP_EOL;
```

## 2. Add a System Prompt

```php
<?php

declare(strict_types=1);

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;

$context = new AgentContext(
    conversationStateRepository: new InMemoryConversationStateRepository(),
    id: 'poet-session',
    systemPrompt: 'Answer in a concise, poetic tone.',
);

$response = $alix
    ->withContext($context)
    ->ask('Describe a sunrise.');
```

## 3. Reuse a Session Across Turns

The conversation-state repository owns the evolving history and summary.
Reuse the same repository and `id` across multiple queries.

```php
<?php

declare(strict_types=1);

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;

$repository = new InMemoryConversationStateRepository();

$context = new AgentContext(
    conversationStateRepository: $repository,
    id: 'chat-1',
    systemPrompt: 'Be friendly and conversational.',
);

$chat = $alix->withContext($context);

$first = $chat->ask('Hello there.');
$second = $chat->ask('Now tell me a joke.');

print_r($repository->load('chat-1')->history);
```

## 4. Choose a Workflow

Right now Agent ships with one real built-in workflow id:
- `default`

The workflow id is still part of the API so custom providers can add more later.

```php
<?php

declare(strict_types=1);

$response = $alix
    ->withContext(new AgentContext(
        conversationStateRepository: new InMemoryConversationStateRepository(),
        id: 'summary-demo',
    ))
    ->withWorkflow('default')
    ->ask('Summarize what makes a good friend.');
```

## 5. Inspect the Agent Trace

`AgentResponse` includes the final text plus a trace of what the workflow did.

```php
<?php

declare(strict_types=1);

$response = $chat->ask('Explain courage in one paragraph.');

var_dump($response->text);
var_dump($response->trace);
```

## 6. Add Tools

When you need tools, prefer a small per-run tool set.

```php
<?php

declare(strict_types=1);

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Conversation\InMemoryConversationStateRepository;
use Maduser\Agent\Tooling\ToolInterface;

final class LookupWeatherTool implements ToolInterface
{
    public static function name(): string
    {
        return 'lookup_weather';
    }

    public static function description(): string
    {
        return 'Return a short fake weather summary.';
    }

    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string',
                ],
            ],
            'required' => ['location'],
        ];
    }

    public function execute(array $args, ?AgentContext $context = null): string|array
    {
        return [
            'summary' => 'Sunny and mild in ' . ($args['location'] ?? 'unknown location'),
        ];
    }
}

$context = new AgentContext(
    conversationStateRepository: new InMemoryConversationStateRepository(),
    id: 'tools-demo',
    systemPrompt: 'Use tools when needed.',
);

$response = $alix
    ->withContext($context)
    ->withTools([
    LookupWeatherTool::class,
    ])
    ->ask('What is the weather in Zurich?');
```

## 7. CLI Probe

`maduser-agent` ships with a tiny CLI harness outside `src/`.

```bash
php cliapp/console agent:query "Send me a random inspiring quote"
php cliapp/console agent:query "Explain courage" --json
php cliapp/console agent:query "Describe a sunrise" --system="Answer like a poet"
php cliapp/console agent:chat --system="Be a sharp but friendly assistant"
```

The CLI wiring lives in:
- `cliapp/AppServices.php`

That is the intended place to look when you want to understand how objects are built.

## 8. Likely Companion AI Usage

The current idea for `companion-ai` is:
- `llm-client` stays the provider/protocol layer
- `alix` becomes the workflow-backed agent runtime
- `companion-ai` contributes product-specific context, handlers, policies, and tools

So Companion AI would likely use Agent more like this:

```php
<?php

declare(strict_types=1);

$llm = LLMClient::openAiResponses(
    apiKey: $apiKey,
    defaultModel: 'gpt-5.4',
);

$alix = Agent::createDefault($llm);

$context = new AgentContext(
    conversationStateRepository: $companionConversationStateRepository,
    id: $conversationId,
    systemPrompt: $characterPrompt,
    vendorHints: [
        'reasoning' => ['effort' => 'low'],
    ],
);

$response = $alix
    ->withContext($context)
    ->withWorkflow('companion-turn')
    ->withTools([
    LookupWeatherTool::class,
    ])
    ->ask($userInput);
```

Longer term, Companion AI would likely stop using the generic default workflow and instead provide:
- a companion-specific workflow id
- companion-specific handlers
- memory and summary steps
- image-generation steps
- turn finalization and persistence logic

## 9. Current Boundary Summary

Today, the package split is intended to be:
- `maduser/llm-client`: provider client, DTOs, transport, structured output, tool-call protocol
- `maduser/agent`: workflow-backed runtime, agent entrypoint, tool execution loop
- `maduser/companion-ai`: product-specific logic

That means:
- `LLMRequest` should stay a low-level concern
- normal package users should prefer `LLMClient` and `Agent`
- app/framework code should mostly wire services and provide product-specific adapters
