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
 * OpenAI generator using direct HTTP client.
 *
 * Supports GPT-4, GPT-3.5-turbo and other OpenAI models.
 */
final class OpenAiGenerator implements NlToSqlGeneratorInterface
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ?HttpClientInterface $httpClient,
        private readonly PromptBuilder $promptBuilder,
        private readonly CostEstimator $costEstimator,
        private readonly string $apiKey,
        private readonly string $baseUrl = 'https://api.openai.com/v1',
        private readonly string $model = 'gpt-4-turbo',
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
                error: 'OPENAI_NOT_CONFIGURED',
                message: 'OpenAI is not configured. Please set your API key in the bundle configuration.',
                suggestions: [
                    'Configure qd_schema.nl_to_sql.ai.provider: "openai"',
                    'Configure qd_schema.nl_to_sql.ai.api_key: "%env(OPENAI_API_KEY)%"',
                    'Set OPENAI_API_KEY in your .env file',
                ],
                provider: 'openai-unavailable'
            );
        }

        try {
            // Build prompt with analyzed schema context
            $promptData = $this->promptBuilder->buildPrompt($prompt, $entities);
            $systemPrompt = $promptData['system'];
            $userPrompt = $promptData['user'];

            $this->logger->debug('Generated prompt for OpenAI', [
                'system_prompt_length' => strlen($systemPrompt),
                'user_prompt_length' => strlen($userPrompt),
                'analysis' => $promptData['analysis'],
            ]);

            // Estimate cost
            $contextTokens = $this->estimateContextTokens($systemPrompt);
            $estimate = $this->costEstimator->estimateCost($prompt, $this->model, $contextTokens);

            if ($this->costEstimator->exceedsMaximum($estimate)) {
                $this->logger->warning('OpenAI generation blocked: cost exceeds maximum', [
                    'estimate' => $estimate->amount,
                ]);

                return NlToSqlResult::failure(
                    error: 'COST_EXCEEDED',
                    message: sprintf('Estimated cost ($%.4f) exceeds maximum allowed', $estimate->amount),
                    provider: $this->model
                );
            }

            if ($this->costEstimator->shouldWarn($estimate)) {
                $this->logger->warning('OpenAI generation cost warning', [
                    'estimate' => $estimate->amount,
                    'model' => $this->model,
                ]);
            }

            // Call OpenAI API
            $response = $this->callOpenAiApi($systemPrompt, $userPrompt);

            // Parse response
            $parsed = $this->parseResponse($response);

            // Calculate cost
            $costInfo = $this->calculateCost($estimate, $response);

            // Extract entities
            $usedEntities = $this->extractEntitiesFromSql($parsed['sql'] ?? '', $entities);

            $this->logger->info('OpenAI SQL generation successful', [
                'model' => $this->model,
                'cost' => $costInfo->actual,
                'confidence' => $parsed['confidence'] ?? 0.0,
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
            $this->logger->error('OpenAI SQL generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return NlToSqlResult::failure(
                error: 'OPENAI_ERROR',
                message: 'OpenAI generation failed: ' . $e->getMessage(),
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
     * Call OpenAI API directly.
     */
    private function callOpenAiApi(string $systemPrompt, string $userPrompt): array
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
                'response_format' => ['type' => 'json_object'],
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
            $this->logger->error('Failed to parse OpenAI response', [
                'content' => $content,
                'content_length' => strlen($content),
            ]);
            throw new \RuntimeException('Failed to parse OpenAI response as JSON. Response: ' . substr($content, 0, 500));
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
