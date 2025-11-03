<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql\Generator;

use Qd\SchemaBundle\Dto\NlToSql\CostEstimate;
use Qd\SchemaBundle\Dto\NlToSql\CostInfo;
use Qd\SchemaBundle\Dto\NlToSql\NlToSqlResult;
use Qd\SchemaBundle\Service\NlToSql\CostEstimator;
use Qd\SchemaBundle\Service\NlToSql\Interface\NlToSqlGeneratorInterface;
use Qd\SchemaBundle\Service\NlToSql\PromptBuilder;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Grok AI generator using direct HTTP client.
 *
 * Grok API is OpenAI-compatible but uses "xai-" prefixed keys instead of "sk-".
 * This generator bypasses Symfony AI's OpenAI bridge to avoid key validation.
 */
final class GrokAiGenerator implements NlToSqlGeneratorInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ?HttpClientInterface $httpClient,
        private readonly PromptBuilder $promptBuilder,
        private readonly CostEstimator $costEstimator,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.x.ai/v1',
        private readonly string $model = 'grok-beta',
        private readonly int $maxTokens = 1000,
        private readonly float $temperature = 0.2,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function generate(string $prompt, array $entities): NlToSqlResult
    {
        if (!$this->isAvailable()) {
            return NlToSqlResult::failure(
                error: 'GROK_NOT_CONFIGURED',
                message: 'Grok AI is not configured. Please set GROK_API_KEY in your .env file.',
                provider: 'grok-unavailable'
            );
        }

        try {
            // Build prompt with analyzed schema context
            $promptData = $this->promptBuilder->buildPrompt($prompt, $entities);
            $systemPrompt = $promptData['system'];
            $userPrompt = $promptData['user'];

            // Estimate cost
            $contextTokens = $this->estimateContextTokens($systemPrompt);
            $estimate = $this->costEstimator->estimateCost($prompt, $this->model, $contextTokens);

            if ($this->costEstimator->exceedsMaximum($estimate)) {
                return NlToSqlResult::failure(
                    error: 'COST_EXCEEDED',
                    message: sprintf('Estimated cost ($%.4f) exceeds maximum', $estimate->amount),
                    provider: $this->model
                );
            }

            // Call Grok API
            $response = $this->callGrokApi($systemPrompt, $userPrompt);

            // Parse response
            $parsed = $this->parseResponse($response);

            // Calculate cost
            $costInfo = $this->calculateCost($estimate, $response);

            // Extract entities
            $usedEntities = $this->extractEntitiesFromSql($parsed['sql'] ?? '', $entities);

            $this->logger->info('Grok SQL generation successful', [
                'model' => $this->model,
                'cost' => $costInfo->actual,
            ]);

            return new NlToSqlResult(
                success: true,
                sql: $parsed['sql'] ?? '',
                confidence: (float) ($parsed['confidence'] ?? 0.8),
                explanation: $parsed['explanation'] ?? '',
                entities: $usedEntities,
                paths: [],
                provider: $this->model,
                costInfo: $costInfo,
            );
        } catch (\Exception $e) {
            $this->logger->error('Grok SQL generation failed', [
                'error' => $e->getMessage(),
            ]);

            return NlToSqlResult::failure(
                error: 'GROK_ERROR',
                message: 'Grok generation failed: ' . $e->getMessage(),
                provider: $this->model
            );
        }
    }

    public function estimateCost(string $prompt): CostEstimate
    {
        return $this->costEstimator->estimateCost($prompt, $this->model, 2000);
    }

    public function isAvailable(): bool
    {
        return $this->httpClient !== null && !empty($this->apiKey);
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Call Grok API directly.
     */
    private function callGrokApi(string $systemPrompt, string $userPrompt): array
    {
        $response = $this->httpClient->request('POST', $this->baseUrl . '/chat/completions', [
            'headers' => [
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'model' => $this->model,
                'messages' => [
                    ['role' => 'system', 'content' => $systemPrompt],
                    ['role' => 'user', 'content' => $userPrompt],
                ],
                'max_tokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ],
        ]);

        $data = $response->toArray();

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage' => $data['usage'] ?? [],
        ];
    }

    private function parseResponse(array $response): array
    {
        $content = $response['content'] ?? '';
        $json = $this->extractJson($content);

        if ($json === null) {
            throw new \RuntimeException('Failed to parse Grok response as JSON');
        }

        return $json;
    }

    private function extractJson(string $content): ?array
    {
        // Try direct decode
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try extracting from code block
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    private function calculateCost(CostEstimate $estimate, array $response): CostInfo
    {
        $usage = $response['usage'] ?? [];

        if (empty($usage)) {
            return new CostInfo(
                estimated: $estimate->amount,
                actual: $estimate->amount,
            );
        }

        return $this->costEstimator->calculateActualCost($usage, $this->model);
    }

    private function extractEntitiesFromSql(string $sql, array $entities): array
    {
        $usedEntities = [];

        foreach ($entities as $entity) {
            $tableName = $entity['tableName'] ?? strtolower($entity['name'] ?? '');
            if (stripos($sql, $tableName) !== false) {
                $usedEntities[] = $entity;
            }
        }

        return $usedEntities;
    }

    private function estimateContextTokens(string $systemPrompt): int
    {
        return (int) ceil(mb_strlen($systemPrompt) / 4);
    }
}
