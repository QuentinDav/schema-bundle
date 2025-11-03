<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql\Interface;

use Qd\SchemaBundle\Dto\NlToSql\CostEstimate;
use Qd\SchemaBundle\Dto\NlToSql\NlToSqlResult;

/**
 * Interface for Natural Language to SQL generators.
 *
 * Implementations can be rule-based (local), AI-based, or hybrid.
 */
interface NlToSqlGeneratorInterface
{
    /**
     * Generate SQL from a natural language prompt.
     *
     * @param string $prompt   Natural language query
     * @param array<int, array<string, mixed>> $entities  Array of entity metadata
     */
    public function generate(string $prompt, array $entities): NlToSqlResult;

    /**
     * Estimate the cost of generating SQL for a given prompt.
     *
     * For local/rule-based generators, this will typically return zero cost.
     */
    public function estimateCost(string $prompt): CostEstimate;

    /**
     * Check if this generator is available/configured.
     */
    public function isAvailable(): bool;

    /**
     * Get the model/provider name (e.g., 'gpt-4', 'gemini-2.5-flash', 'local').
     */
    public function getModelName(): string;
}
