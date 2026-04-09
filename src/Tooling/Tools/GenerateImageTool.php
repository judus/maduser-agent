<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Tools;

use Maduser\Agent\Image\ImageGenerationClient;
use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolInterface;
use Override;

final readonly class GenerateImageTool implements ToolInterface
{
    public function __construct(
        private ImageGenerationClient $imageClient,
        private string $defaultOrientation = 'landscape',
    ) {
    }

    #[Override]
    public static function name(): string
    {
        return 'generate_image';
    }

    #[Override]
    public static function description(): string
    {
        return 'Generates an image from an internal image prompt, displays it to the user, '
            . 'and returns a private image artifact to the caller. Use "prompt" for the '
            . 'image model instructions, "caption" for the short user-facing text shown '
            . 'with the image, and "orientation" for the framing direction.';
    }

    #[Override]
    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'prompt' => [
                    'type' => 'string',
                    'description' => 'The internal image-generation prompt. This is optimized '
                        . 'for the image model and is not shown directly to the user.',
                ],
                'caption' => [
                    'type' => 'string',
                    'description' => 'A short user-facing caption shown alongside the generated '
                        . 'image. This should provide context to the user in-character.',
                ],
                'style' => [
                    'type' => 'string',
                    'description' => 'Optional style id. If style matters and you are unsure, discover valid styles first.',
                ],
                'orientation' => [
                    'type' => 'string',
                    'description' => 'Optional framing direction. Prefer landscape, portrait, or square.',
                    'enum' => ['landscape', 'portrait', 'square'],
                ],
            ],
            'required' => ['prompt', 'caption'],
            'additionalProperties' => false,
        ];
    }

    /**
     * @param array<string, mixed> $args
     * @return array{output: string, artifact: array<string, mixed>}
     */
    #[Override]
    public function execute(array $args, ?AgentContext $context = null): string|array
    {
        $prompt = (string) ($args['prompt'] ?? '');
        $caption = (string) ($args['caption'] ?? '');
        $style = (string) ($args['style'] ?? '');
        $orientation = (string) ($args['orientation'] ?? $this->defaultOrientation);

        $result = $this->imageClient->generate(
            prompt: $prompt,
            options: [
                'style' => $style,
                'orientation' => $orientation,
            ],
        );

        $image = $result->first();

        return [
            'output' => 'Image generated successfully and displayed to the user with this caption: ' . $caption,
            'artifact' => [
                'type' => 'image',
                'prompt' => $prompt,
                'caption' => $caption,
                'style' => $style !== '' ? $style : null,
                'orientation' => $orientation,
                'base64' => $image->base64,
                'url' => $image->url,
                'revised_prompt' => $image->revisedPrompt,
                'mime_type' => $image->mimeType,
            ],
        ];
    }
}
