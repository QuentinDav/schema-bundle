<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Dto\NlToSql;

/**
 * Represents an estimated cost for an AI generation request.
 */
final readonly class CostEstimate
{
    public function __construct(
        public float $amount,
        public string $currency = 'USD',
        public string $model = '',
        public int $estimatedInputTokens = 0,
        public int $estimatedOutputTokens = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'currency' => $this->currency,
            'model' => $this->model,
            'estimated_input_tokens' => $this->estimatedInputTokens,
            'estimated_output_tokens' => $this->estimatedOutputTokens,
        ];
    }
}
