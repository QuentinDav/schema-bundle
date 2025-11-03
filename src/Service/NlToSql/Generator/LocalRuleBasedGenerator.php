<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql\Generator;

use Qd\SchemaBundle\Dto\NlToSql\CostEstimate;
use Qd\SchemaBundle\Dto\NlToSql\NlToSqlResult;
use Qd\SchemaBundle\Service\NlToSql\Interface\NlToSqlGeneratorInterface;

/**
 * Local rule-based SQL generator (placeholder).
 *
 * The actual rule-based NLP engine is implemented in JavaScript (frontend/src/utils/nlpEngine.js).
 * This backend service serves as a placeholder and delegates to the frontend engine.
 *
 * In a hybrid scenario, the frontend first tries the local engine, and if confidence is low,
 * it can call the backend AI generator.
 */
final class LocalRuleBasedGenerator implements NlToSqlGeneratorInterface
{
    public function generate(string $prompt, array $entities): NlToSqlResult
    {
        // This is a placeholder implementation for backend testing
        // The real local engine runs on the frontend (nlpEngine.js)

        return NlToSqlResult::failure(
            error: 'LOCAL_ENGINE_FRONTEND_ONLY',
            message: 'The local rule-based engine runs on the frontend. Please use the AI generator from the backend, or call the local engine from the frontend.',
            suggestions: [
                'Use the AI generator for backend processing',
                'Call the frontend nlpEngine.js for local generation',
            ],
            provider: 'local'
        );
    }

    public function estimateCost(string $prompt): CostEstimate
    {
        // Local generation is always free
        return new CostEstimate(
            amount: 0.0,
            currency: 'USD',
            model: 'local-rule-based',
            estimatedInputTokens: 0,
            estimatedOutputTokens: 0,
        );
    }

    public function isAvailable(): bool
    {
        // Local generator is technically always available (on frontend)
        return true;
    }

    public function getModelName(): string
    {
        return 'local-rule-based';
    }
}
