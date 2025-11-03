<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Dto\NlToSql;

/**
 * Contains both estimated and actual cost information for an AI generation.
 */
final readonly class CostInfo
{
    public function __construct(
        public float $estimated,
        public float $actual,
        public string $currency = 'USD',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $totalTokens = 0,
    ) {
    }

    public function toArray(): array
    {
        return [
            'estimated' => $this->estimated,
            'actual' => $this->actual,
            'currency' => $this->currency,
            'input_tokens' => $this->inputTokens,
            'output_tokens' => $this->outputTokens,
            'total_tokens' => $this->totalTokens,
        ];
    }
}
