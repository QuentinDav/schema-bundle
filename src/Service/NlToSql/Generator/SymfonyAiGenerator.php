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

/**
 * AI-powered SQL generator using Symfony AI component.
 *
 * Supports multiple providers: OpenAI, Anthropic, Azure, Gemini, VertexAI, Ollama.
 *
 * Requires: composer require symfony/ai-bundle
 */
final class SymfonyAiGenerator implements NlToSqlGeneratorInterface
{
    private LoggerInterface $logger;

    /**
     * @param object|null $aiPlatform Symfony AI Platform instance (e.g., from PlatformFactory)
     */
    public function __construct(
        private readonly ?object $aiPlatform,
        private readonly PromptBuilder $promptBuilder,
        private readonly CostEstimator $costEstimator,
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
                error: 'AI_NOT_CONFIGURED',
                message: 'AI provider is not configured. Please install symfony/ai and configure a provider.',
                suggestions: [
                    'Run: composer require symfony/ai',
                    'Configure provider in config/packages/qd_schema.yaml',
                    'Set environment variables (e.g., OPENAI_API_KEY)',
                ],
                provider: 'ai-unavailable'
            );
        }

        try {
            // Build prompt with analyzed schema context
            $promptData = $this->promptBuilder->buildPrompt($prompt, $entities);
            $systemPrompt = $promptData['system'];
            $userPrompt = $promptData['user'];

            $this->logger->debug('Generated prompt for AI', [
                'system_prompt_length' => strlen($systemPrompt),
                'user_prompt_length' => strlen($userPrompt),
                'analysis' => $promptData['analysis'],
            ]);

            // Estimate cost before call
            $contextTokens = $this->estimateContextTokens($systemPrompt);
            $estimate = $this->costEstimator->estimateCost($prompt, $this->model, $contextTokens);

            // Check if cost exceeds maximum
            if ($this->costEstimator->exceedsMaximum($estimate)) {
                $this->logger->warning('AI generation blocked: cost exceeds maximum', [
                    'estimate' => $estimate->amount,
                    'max' => 0.50,
                ]);

                return NlToSqlResult::failure(
                    error: 'COST_EXCEEDED',
                    message: sprintf('Estimated cost ($%.4f) exceeds maximum allowed ($0.50)', $estimate->amount),
                    provider: $this->model
                );
            }

            // Log warning if cost is high
            if ($this->costEstimator->shouldWarn($estimate)) {
                $this->logger->warning('AI generation cost warning', [
                    'estimate' => $estimate->amount,
                    'model' => $this->model,
                ]);
            }

            // Call AI provider
            $response = $this->callAiProvider($systemPrompt, $userPrompt);

            // Parse response
            $parsed = $this->parseResponse($response);

            // Calculate actual cost
            $costInfo = $this->calculateCost($estimate, $response);

            // Extract entities from SQL
            $usedEntities = $this->extractEntitiesFromSql($parsed['sql'] ?? '', $entities);

            $this->logger->info('AI SQL generation successful', [
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
            $this->logger->error('AI SQL generation failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);

            return NlToSqlResult::failure(
                error: 'AI_GENERATION_ERROR',
                message: 'AI generation failed: ' . $e->getMessage(),
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
        return $this->aiPlatform !== null;
    }

    public function getModelName(): string
    {
        return $this->model;
    }

    /**
     * Call the Symfony AI Platform.
     *
     * Uses Symfony AI's unified interface for chat completion.
     *
     * @return array{content: string, usage?: array}
     */
    private function callAiProvider(string $systemPrompt, string $userPrompt): array
    {
        if ($this->aiPlatform === null) {
            throw new \RuntimeException('AI Platform not configured');
        }

        // Check if Symfony AI classes are available
        if (!class_exists('Symfony\AI\Platform\Message\Message')) {
            throw new \RuntimeException(
                'Symfony AI not installed. Run: composer require symfony/ai-bundle'
            );
        }

        // Create messages using Symfony AI
        $messageBagClass = 'Symfony\AI\Platform\Message\MessageBag';
        $messageClass = 'Symfony\AI\Platform\Message\Message';

        $messages = new $messageBagClass(
            $messageClass::forSystem($systemPrompt),
            $messageClass::ofUser($userPrompt)
        );

        // Call the platform with options
        // Note: Different providers use different parameter names
        $options = $this->buildOptions();

        try {
            $result = $this->aiPlatform->invoke($this->model, $messages, $options);

            // Extract usage information if available
            $usage = [];
            if (method_exists($result, 'getUsage')) {
                $usageObj = $result->getUsage();
                if ($usageObj !== null) {
                    $usage = [
                        'prompt_tokens' => $usageObj->getPromptTokens(),
                        'completion_tokens' => $usageObj->getCompletionTokens(),
                        'total_tokens' => $usageObj->getTotalTokens(),
                    ];
                }
            }

            return [
                'content' => $result->asText(),
                'usage' => $usage,
            ];
        } catch (\Throwable $e) {
            $this->logger->error('Symfony AI Platform call failed', [
                'error' => $e->getMessage(),
                'model' => $this->model,
            ]);
            throw new \RuntimeException('AI Platform call failed: ' . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Parse AI response (expects JSON).
     *
     * @return array{sql?: string, explanation?: string, confidence?: float, entities?: array}
     */
    private function parseResponse(array $response): array
    {
        $content = $response['content'] ?? '';

        // Try to extract JSON from response
        $json = $this->extractJson($content);

        if ($json === null) {
            $this->logger->error('Failed to parse AI response', [
                'content' => $content,
                'content_length' => strlen($content),
            ]);
            throw new \RuntimeException('Failed to parse AI response as JSON. Response: ' . substr($content, 0, 500));
        }

        return $json;
    }

    /**
     * Extract JSON from response content.
     */
    private function extractJson(string $content): ?array
    {
        // Try direct JSON decode
        $decoded = json_decode($content, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            return $decoded;
        }

        // Try to extract JSON from markdown code blocks
        if (preg_match('/```(?:json)?\s*(\{.*?\})\s*```/s', $content, $matches)) {
            $decoded = json_decode($matches[1], true);
            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Calculate actual cost from response.
     */
    private function calculateCost(CostEstimate $estimate, array $response): CostInfo
    {
        $usage = $response['usage'] ?? [];

        if (empty($usage)) {
            // If no usage data, return estimate as actual
            return new CostInfo(
                estimated: $estimate->amount,
                actual: $estimate->amount,
                currency: 'USD',
            );
        }

        return $this->costEstimator->calculateActualCost($usage, $this->model);
    }

    /**
     * Extract entity names from SQL query.
     *
     * @param array<int, array<string, mixed>> $entities
     * @return array<string, mixed>
     */
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

    /**
     * Estimate tokens in system prompt.
     */
    private function estimateContextTokens(string $systemPrompt): int
    {
        // Rough estimate: 4 chars per token
        return (int) ceil(mb_strlen($systemPrompt) / 4);
    }

    /**
     * Build options array compatible with the current provider.
     *
     * Different AI providers use different parameter names:
     * - OpenAI: max_tokens, temperature
     * - Gemini: maxOutputTokens, temperature
     * - Anthropic: max_tokens, temperature
     */
    private function buildOptions(): array
    {
        // Detect provider from model name
        if (str_starts_with($this->model, 'gemini-')) {
            // Gemini uses different parameter names
            return [
                'maxOutputTokens' => $this->maxTokens,
                'temperature' => $this->temperature,
            ];
        }

        // Default (OpenAI, Anthropic, etc.)
        return [
            'max_tokens' => $this->maxTokens,
            'temperature' => $this->temperature,
        ];
    }
}
