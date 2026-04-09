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
        private string $defaultSize = '1024x1024',
        private string $defaultQuality = 'medium',
        private string $defaultBackground = 'auto',
        private string $defaultFormat = 'png',
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
            . 'image model instructions and "caption" for the short user-facing text shown '
            . 'with the image.';
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
                'size' => [
                    'type' => 'string',
                    'description' => 'Optional image size, e.g. 1024x1024.',
                ],
                'quality' => [
                    'type' => 'string',
                    'description' => 'Optional image quality.',
                ],
                'background' => [
                    'type' => 'string',
                    'description' => 'Optional image background mode.',
                ],
                'format' => [
                    'type' => 'string',
                    'description' => 'Optional image output format.',
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

        $result = $this->imageClient->generate(
            prompt: $prompt,
            options: [
                'size' => (string) ($args['size'] ?? $this->defaultSize),
                'quality' => (string) ($args['quality'] ?? $this->defaultQuality),
                'background' => (string) ($args['background'] ?? $this->defaultBackground),
                'output_format' => (string) ($args['format'] ?? $this->defaultFormat),
            ],
        );

        $image = $result->first();

        return [
            'output' => 'Image generated successfully and displayed to the user with this caption: ' . $caption,
            'artifact' => [
                'type' => 'image',
                'prompt' => $prompt,
                'caption' => $caption,
                'base64' => $image->base64,
                'url' => $image->url,
                'revised_prompt' => $image->revisedPrompt,
                'mime_type' => $image->mimeType,
            ],
        ];
    }
}
