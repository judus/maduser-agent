<?php

declare(strict_types=1);

namespace Maduser\Agent\Tooling\Tools;

use Maduser\Agent\Context\AgentContext;
use Maduser\Agent\Tooling\ToolInterface;
use Override;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Throwable;

use function array_slice;
use function curl_close;
use function curl_error;
use function curl_exec;
use function curl_getinfo;
use function curl_init;
use function curl_setopt;
use function date;
use function http_build_query;
use function implode;
use function is_array;
use function is_string;
use function json_decode;
use function sprintf;

use const CURLINFO_HTTP_CODE;
use const CURLOPT_CONNECTTIMEOUT;
use const CURLOPT_RETURNTRANSFER;
use const CURLOPT_TIMEOUT;
use const CURLOPT_URL;

final class GetWeatherTool implements ToolInterface
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly ?string $apiKey = null,
        ?LoggerInterface $logger = null,
        private readonly string $baseUrl = 'https://api.openweathermap.org/data/3.0/onecall',
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    #[Override]
    public static function name(): string
    {
        return 'get_weather';
    }

    #[Override]
    public static function description(): string
    {
        return 'Returns current weather and a short forecast for given coordinates using OpenWeatherMap.';
    }

    #[Override]
    public static function parameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'lat' => [
                    'type' => 'number',
                    'description' => 'Latitude for the weather forecast.',
                ],
                'lon' => [
                    'type' => 'number',
                    'description' => 'Longitude for the weather forecast.',
                ],
                'hours' => [
                    'type' => 'integer',
                    'description' => 'How many upcoming hourly forecast entries to include (0-6). Defaults to 3.',
                    'minimum' => 0,
                    'maximum' => 6,
                ],
                'days' => [
                    'type' => 'integer',
                    'description' => 'How many upcoming daily forecast entries to include (0-3). Defaults to 2.',
                    'minimum' => 0,
                    'maximum' => 3,
                ],
            ],
            'required' => ['lat', 'lon'],
            'additionalProperties' => false,
        ];
    }

     /**
      * @param array<string, mixed> $args
      */
    #[Override]
    public function execute(array $args, ?AgentContext $context = null): string|array
    {
        if ($this->apiKey === null || $this->apiKey === '') {
            return 'Weather API is not configured.';
        }

        try {
            $lat = (float) ($args['lat'] ?? 0);
            $lon = (float) ($args['lon'] ?? 0);
            $hours = max(0, min(6, (int) ($args['hours'] ?? 3)));
            $days = max(0, min(3, (int) ($args['days'] ?? 2)));

            $query = http_build_query([
                'lat' => $lat,
                'lon' => $lon,
                'appid' => $this->apiKey,
                'exclude' => 'minutely,alerts',
                'units' => 'metric',
            ]);

            $ch = curl_init();

            if ($ch === false) {
                return 'Unable to initialize weather request.';
            }

            curl_setopt($ch, CURLOPT_URL, $this->baseUrl . '?' . $query);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 20);

            $raw = curl_exec($ch);
            $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if (!is_string($raw) || $raw === '') {
                if ($error !== '') {
                    $this->logger->error('Weather request failed.', ['error' => $error]);
                }

                return 'Unable to retrieve weather data at this time.';
            }

            if ($status < 200 || $status >= 300) {
                $this->logger->error('Weather request returned a non-success status.', [
                    'status' => $status,
                    'body' => $raw,
                ]);

                return 'Unable to retrieve weather data at this time.';
            }

            $decoded = json_decode($raw, true);

            if (!is_array($decoded)) {
                return 'Unable to decode weather data.';
            }

            /** @var array<string, mixed> $data */
            $data = $decoded;

            $temperature = (string) ($data['current']['temp'] ?? 'unknown');
            $description = (string) ($data['current']['weather'][0]['description'] ?? 'no description available');
            $parts = [
                sprintf(
                    'Weather for coordinates (%.2f, %.2f): %s°C, %s.',
                    $lat,
                    $lon,
                    $temperature,
                    $description
                ),
            ];

            $hourly = $this->formatHourlyForecast($data, $hours);
            if ($hourly !== '') {
                $parts[] = 'Hourly forecast: ' . $hourly . '.';
            }

            $daily = $this->formatDailyForecast($data, $days);
            if ($daily !== '') {
                $parts[] = 'Daily forecast: ' . $daily . '.';
            }

            return implode(' ', $parts);
        } catch (Throwable $e) {
            $this->logger->error('Weather request failed.', ['exception' => $e]);

            return 'Unable to retrieve weather data at this time.';
        }
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatHourlyForecast(array $data, int $hours): string
    {
        if ($hours <= 0 || !isset($data['hourly']) || !is_array($data['hourly'])) {
            return '';
        }

        $entries = [];

        foreach (array_slice($data['hourly'], 0, $hours) as $hour) {
            if (!is_array($hour)) {
                continue;
            }

            $time = isset($hour['dt']) ? date('H:i', (int) $hour['dt']) : 'unknown';
            $temp = (string) ($hour['temp'] ?? 'unknown');
            $desc = (string) ($hour['weather'][0]['description'] ?? 'no description');
            $entries[] = sprintf('%s %s°C %s', $time, $temp, $desc);
        }

        return implode('; ', $entries);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function formatDailyForecast(array $data, int $days): string
    {
        if ($days <= 0 || !isset($data['daily']) || !is_array($data['daily'])) {
            return '';
        }

        $entries = [];

        foreach (array_slice($data['daily'], 0, $days) as $day) {
            if (!is_array($day)) {
                continue;
            }

            $label = isset($day['dt']) ? date('D', (int) $day['dt']) : 'unknown';
            $min = (string) ($day['temp']['min'] ?? 'unknown');
            $max = (string) ($day['temp']['max'] ?? 'unknown');
            $desc = (string) ($day['weather'][0]['description'] ?? 'no description');
            $entries[] = sprintf('%s %s to %s°C %s', $label, $min, $max, $desc);
        }

        return implode('; ', $entries);
    }
}
