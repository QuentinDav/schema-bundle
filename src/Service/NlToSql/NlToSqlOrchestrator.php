<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

use Qd\SchemaBundle\Dto\NlToSql\NlToSqlResult;
use Qd\SchemaBundle\Service\NlToSql\Interface\NlToSqlGeneratorInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

/**
 * Orchestrates Natural Language to SQL generation using AI.
 *
 * Requires an AI provider to be configured (OpenAI, Anthropic, Mistral, or Grok).
 * If no AI provider is configured, the feature is disabled.
 */
final class NlToSqlOrchestrator
{
    private LoggerInterface $logger;

    public function __construct(
        private readonly ?NlToSqlGeneratorInterface $aiGenerator,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    /**
     * Generate SQL from natural language prompt.
     *
     * @param string $prompt Natural language query
     * @param array<int, array<string, mixed>> $entities Entity metadata
     */
    public function generate(string $prompt, array $entities): NlToSqlResult
    {
        $this->logger->info('NL to SQL generation started', [
            'prompt_length' => mb_strlen($prompt),
            'entity_count' => count($entities),
        ]);

        if ($this->aiGenerator === null || !$this->aiGenerator->isAvailable()) {
            return NlToSqlResult::failure(
                error: 'AI_NOT_CONFIGURED',
                message: 'Natural Language to SQL requires an AI provider to be configured.',
                suggestions: [
                    'Configure an AI provider in config/packages/qd_schema.yaml',
                    'Example: qd_schema.nl_to_sql.ai.provider: "openai"',
                    'Set the corresponding API key: qd_schema.nl_to_sql.ai.api_key: "%env(OPENAI_API_KEY)%"',
                    'Supported providers: openai, anthropic, mistral, grok',
                ]
            );
        }

        $this->logger->debug('Using AI generator', [
            'provider' => $this->aiGenerator->getModelName(),
        ]);

        return $this->aiGenerator->generate($prompt, $entities);
    }

    /**
     * Estimate cost for a prompt.
     */
    public function estimateCost(string $prompt): ?\Qd\SchemaBundle\Dto\NlToSql\CostEstimate
    {
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
}
