<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

use Qd\SchemaBundle\Dto\NlToSql\NlToSqlResult;
use Qd\SchemaBundle\Service\NlToSql\Interface\NlToSqlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates Natural Language to SQL generation.
 *
 * Supports three strategies:
 * - 'local': Use rule-based engine only (frontend)
 * - 'ai': Use AI generator only (backend)
 * - 'hybrid': Try local first, fallback to AI if confidence is low
 */
final class NlToSqlOrchestrator
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly NlToSqlGeneratorInterface $localGenerator,
        private readonly ?NlToSqlGeneratorInterface $aiGenerator,
        private readonly string $strategy = 'local',
        private readonly float $confidenceThreshold = 0.7,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();

        // Validate strategy
        if (!in_array($this->strategy, ['local', 'ai', 'hybrid'], true)) {
            throw new \InvalidArgumentException(
                "Invalid strategy '{$this->strategy}'. Must be 'local', 'ai', or 'hybrid'."
            );
        }
    }

    /**
     * Generate SQL from natural language prompt.
     *
     * @param string $prompt Natural language query
     * @param array<int, array<string, mixed>> $entities Entity metadata
     * @param string|null $overrideStrategy Optional strategy override for this request
     */
    public function generate(string $prompt, array $entities, ?string $overrideStrategy = null): NlToSqlResult
    {
        $strategy = $overrideStrategy ?? $this->strategy;

        $this->logger->info('NL to SQL generation started', [
            'strategy' => $strategy,
            'prompt_length' => mb_strlen($prompt),
            'entity_count' => count($entities),
        ]);

        return match ($strategy) {
            'local' => $this->generateLocal($prompt, $entities),
            'ai' => $this->generateAi($prompt, $entities),
            'hybrid' => $this->generateHybrid($prompt, $entities),
            default => NlToSqlResult::failure(
                error: 'INVALID_STRATEGY',
                message: "Invalid strategy: {$strategy}"
            ),
        };
    }

    /**
     * Estimate cost for a prompt.
     *
     * Only relevant for AI strategies.
     */
    public function estimateCost(string $prompt, string $strategy = 'ai'): ?\Qd\SchemaBundle\Dto\NlToSql\CostEstimate
    {
        if ($strategy === 'local') {
            return $this->localGenerator->estimateCost($prompt);
        }

        if ($this->aiGenerator === null || !$this->aiGenerator->isAvailable()) {
            return null;
        }

        return $this->aiGenerator->estimateCost($prompt);
    }

    /**
     * Check if AI generator is available.
     */
    public function isAiAvailable(): bool
    {
        return $this->aiGenerator !== null && $this->aiGenerator->isAvailable();
    }

    /**
     * Get the AI model name if available.
     */
    public function getAiModelName(): ?string
    {
        if ($this->aiGenerator === null || !$this->aiGenerator->isAvailable()) {
            return null;
        }

        return $this->aiGenerator->getModelName();
    }

    /**
     * Generate using local rule-based engine.
     */
    private function generateLocal(string $prompt, array $entities): NlToSqlResult
    {
        $this->logger->debug('Using local generator');
        return $this->localGenerator->generate($prompt, $entities);
    }

    /**
     * Generate using AI engine.
     */
    private function generateAi(string $prompt, array $entities): NlToSqlResult
    {
        if ($this->aiGenerator === null || !$this->aiGenerator->isAvailable()) {
            return NlToSqlResult::failure(
                error: 'AI_NOT_AVAILABLE',
                message: 'AI generator is not configured or available.',
                suggestions: [
                    'Install symfony/ai package',
                    'Configure AI provider in qd_schema.yaml',
                    'Use "local" or "hybrid" strategy instead',
                ]
            );
        }

        $this->logger->debug('Using AI generator');
        return $this->aiGenerator->generate($prompt, $entities);
    }

    /**
     * Generate using hybrid approach.
     *
     * Try local first, then fallback to AI if confidence is below threshold.
     */
    private function generateHybrid(string $prompt, array $entities): NlToSqlResult
    {
        $this->logger->debug('Using hybrid strategy');

        // Step 1: Try local generator
        $localResult = $this->localGenerator->generate($prompt, $entities);

        // Step 2: Check if we should enhance with AI
        if ($localResult->success && $localResult->confidence >= $this->confidenceThreshold) {
            $this->logger->info('Local generator succeeded with good confidence', [
                'confidence' => $localResult->confidence,
            ]);
            return $localResult;
        }

        // Step 3: Fallback to AI if available
        if ($this->aiGenerator !== null && $this->aiGenerator->isAvailable()) {
            $this->logger->info('Local confidence too low, trying AI', [
                'local_confidence' => $localResult->confidence,
                'threshold' => $this->confidenceThreshold,
            ]);

            $aiResult = $this->aiGenerator->generate($prompt, $entities);

            // Compare results and return the better one
            if ($aiResult->success && $aiResult->confidence > $localResult->confidence) {
                $this->logger->info('AI generator provided better result', [
                    'ai_confidence' => $aiResult->confidence,
                    'local_confidence' => $localResult->confidence,
                ]);
                return $aiResult;
            }
        }

        // Step 4: Return local result as fallback
        $this->logger->info('Returning local result (AI not available or not better)');
        return $localResult;
    }
}
