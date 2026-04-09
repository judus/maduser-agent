<?php

declare(strict_types=1);

namespace Maduser\AgentCli;

use Dotenv\Dotenv;
use OutOfBoundsException;
use UnexpectedValueException;

use function array_key_exists;
use function array_replace_recursive;
use function explode;
use function file_exists;
use function glob;
use function is_array;
use function is_bool;
use function is_dir;
use function is_file;
use function is_int;
use function is_string;
use function ltrim;
use function sprintf;

final class AppContext
{
    /** @var array<string, mixed> */
    private array $config = [];

    private function __construct(
        private readonly string $projectRoot,
        private readonly string $configDir,
    ) {
    }

    public static function fromProjectRoot(string $projectRoot, ?string $configDir = null): self
    {
        if (file_exists($projectRoot . '/.env')) {
            Dotenv::createImmutable($projectRoot)->safeLoad();
        }

        $context = new self(
            projectRoot: $projectRoot,
            configDir: $configDir ?? $projectRoot . '/config',
        );

        $context->loadConfigFiles();

        return $context;
    }

    public function basePath(string $suffix = ''): string
    {
        if ($suffix === '') {
            return $this->projectRoot;
        }

        return $this->projectRoot . '/' . ltrim($suffix, '/');
    }

    public function configPath(string $suffix = ''): string
    {
        if ($suffix === '') {
            return $this->configDir;
        }

        return $this->configDir . '/' . ltrim($suffix, '/');
    }

    public function env(string $key, ?string $default = null): ?string
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if (!is_string($value) || $value === '') {
            return $default;
        }

        return $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->config;
    }

    public function has(string $path): bool
    {
        $segments = explode('.', $path);
        $current = $this->config;

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $current)) {
                return false;
            }

            $value = $current[$segment];

            if ($segment === $segments[array_key_last($segments)]) {
                return true;
            }

            if (!is_array($value)) {
                return false;
            }

            /** @var array<string, mixed> $value */
            $current = $value;
        }

        return false;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        try {
            return $this->require($path);
        } catch (OutOfBoundsException | UnexpectedValueException) {
            return $default;
        }
    }

    public function require(string $path): mixed
    {
        $segments = explode('.', $path);
        $current = $this->config;

        foreach ($segments as $segment) {
            if (!array_key_exists($segment, $current)) {
                throw new OutOfBoundsException('Missing config key: ' . $path);
            }

            $value = $current[$segment];

            if ($segment === $segments[array_key_last($segments)]) {
                return $value;
            }

            if (!is_array($value)) {
                throw new UnexpectedValueException(
                    sprintf('Invalid config type for key "%s", expected %s', $path, 'array'),
                );
            }

            /** @var array<string, mixed> $value */
            $current = $value;
        }

        throw new OutOfBoundsException('Missing config key: ' . $path);
    }

    public function string(string $path): string
    {
        $value = $this->require($path);

        if (!is_string($value)) {
            throw new UnexpectedValueException(
                sprintf('Invalid config type for key "%s", expected %s', $path, 'string'),
            );
        }

        return $value;
    }

    public function bool(string $path): bool
    {
        $value = $this->require($path);

        if (!is_bool($value)) {
            throw new UnexpectedValueException(
                sprintf('Invalid config type for key "%s", expected %s', $path, 'bool'),
            );
        }

        return $value;
    }

    public function int(string $path): int
    {
        $value = $this->require($path);

        if (!is_int($value)) {
            throw new UnexpectedValueException(
                sprintf('Invalid config type for key "%s", expected %s', $path, 'int'),
            );
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|list<mixed>
     */
    public function array(string $path): array
    {
        $value = $this->require($path);

        if (!is_array($value)) {
            throw new UnexpectedValueException(
                sprintf('Invalid config type for key "%s", expected %s', $path, 'array'),
            );
        }

        return $value;
    }

    private function loadConfigFiles(): void
    {
        if (!is_dir($this->configDir)) {
            return;
        }

        /** @var list<string>|false $files */
        $files = glob($this->configDir . '/*.php');

        if ($files === false) {
            return;
        }

        foreach ($files as $file) {
            if (!is_file($file)) {
                continue;
            }

            $key = pathinfo($file, PATHINFO_FILENAME);
            $config = require $file;

            if (!is_array($config)) {
                continue;
            }

            $existing = $this->config[$key] ?? [];
            if (!is_array($existing)) {
                $existing = [];
            }

            $this->config[$key] = array_replace_recursive($existing, $config);
        }
    }
}
