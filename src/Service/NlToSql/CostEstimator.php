<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

use Qd\SchemaBundle\Dto\NlToSql\CostEstimate;
use Qd\SchemaBundle\Dto\NlToSql\CostInfo;

/**
 * Estimates and calculates costs for AI-based SQL generation.
 *
 * Pricing is based on token usage per provider. Prices are per 1,000 tokens.
 */
final class CostEstimator
{
    /**
     * Pricing per 1,000 tokens (USD).
     *
     * @var array<string, array{input: float, output: float}>
     */
    private const PRICING = [
        // OpenAI
        'gpt-4o' => ['input' => 0.0025, 'output' => 0.01],
        'gpt-4o-mini' => ['input' => 0.00015, 'output' => 0.0006],
        'gpt-4-turbo' => ['input' => 0.01, 'output' => 0.03],
        'gpt-4' => ['input' => 0.03, 'output' => 0.06],
        'gpt-3.5-turbo' => ['input' => 0.0005, 'output' => 0.0015],

        // Anthropic Claude
        'claude-3-opus' => ['input' => 0.015, 'output' => 0.075],
        'claude-3-sonnet' => ['input' => 0.003, 'output' => 0.015],
        'claude-3-haiku' => ['input' => 0.00025, 'output' => 0.00125],

        // Google Gemini 2.x
        'gemini-2.5-pro' => ['input' => 0.00125, 'output' => 0.005],
        'gemini-2.5-flash' => ['input' => 0.000075, 'output' => 0.0003],
        'gemini-2.0-flash' => ['input' => 0.000075, 'output' => 0.0003],
        'gemini-2.0-flash-lite' => ['input' => 0.000035, 'output' => 0.00014],

        // Mistral AI
        'mistral-large' => ['input' => 0.004, 'output' => 0.012],
        'mistral-medium' => ['input' => 0.0027, 'output' => 0.0081],
        'mistral-small' => ['input' => 0.0010, 'output' => 0.0030],

        // Grok (xAI) - OpenAI compatible API
        'grok-beta' => ['input' => 0.005, 'output' => 0.015],
        'grok-2' => ['input' => 0.005, 'output' => 0.015],

        // Ollama (local, free)
        'ollama' => ['input' => 0.0, 'output' => 0.0],

        // Default fallback
        'default' => ['input' => 0.001, 'output' => 0.003],
    ];

    /**
     * Average characters per token (rough estimate).
     */
    private const CHARS_PER_TOKEN = 4;

    /**
     * Estimated output tokens for SQL generation.
     */
    private const ESTIMATED_OUTPUT_TOKENS = 500;

    public function __construct(
        private readonly float $warnThreshold = 0.10,
        private readonly float $maxPerRequest = 0.50,
    ) {
    }

    /**
     * Estimate cost before making an API call.
     */
    public function estimateCost(string $prompt, string $model, int $contextTokens = 0): CostEstimate
    {
        $pricing = $this->getPricing($model);

        // Estimate input tokens (prompt + schema context)
        $promptTokens = $this->estimateTokens($prompt);
        $inputTokens = $promptTokens + $contextTokens;

        // Estimate output tokens
        $outputTokens = self::ESTIMATED_OUTPUT_TOKENS;

        // Calculate costs (pricing is per 1,000 tokens)
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        $totalCost = $inputCost + $outputCost;

        return new CostEstimate(
            amount: $totalCost,
            currency: 'USD',
            model: $model,
            estimatedInputTokens: $inputTokens,
            estimatedOutputTokens: $outputTokens,
        );
    }

    /**
     * Calculate actual cost from usage data.
     *
     * @param array{prompt_tokens?: int, completion_tokens?: int, total_tokens?: int} $usage
     */
    public function calculateActualCost(array $usage, string $model): CostInfo
    {
        $pricing = $this->getPricing($model);

        $inputTokens = $usage['prompt_tokens'] ?? 0;
        $outputTokens = $usage['completion_tokens'] ?? 0;
        $totalTokens = $usage['total_tokens'] ?? ($inputTokens + $outputTokens);

        // Calculate actual cost
        $inputCost = ($inputTokens / 1000) * $pricing['input'];
        $outputCost = ($outputTokens / 1000) * $pricing['output'];
        $actualCost = $inputCost + $outputCost;

        // Get estimate for comparison
        $estimate = $this->estimateCost('', $model, $inputTokens);

        return new CostInfo(
            estimated: $estimate->amount,
            actual: $actualCost,
            currency: 'USD',
            inputTokens: $inputTokens,
            outputTokens: $outputTokens,
            totalTokens: $totalTokens,
        );
    }

    /**
     * Check if estimated cost exceeds warning threshold.
     */
    public function shouldWarn(CostEstimate $estimate): bool
    {
        return $estimate->amount > $this->warnThreshold;
    }

    /**
     * Check if estimated cost exceeds maximum allowed.
     */
    public function exceedsMaximum(CostEstimate $estimate): bool
    {
        return $estimate->amount > $this->maxPerRequest;
    }

    /**
     * Estimate number of tokens from text.
     *
     * This is a rough approximation. For production, consider using
     * tiktoken or similar library for accurate token counting.
     */
    private function estimateTokens(string $text): int
    {
        if (empty($text)) {
            return 0;
        }

        // Rough estimate: ~4 characters per token
        $charCount = mb_strlen($text);
        return (int) ceil($charCount / self::CHARS_PER_TOKEN);
    }

    /**
     * Get pricing for a specific model.
     *
     * @return array{input: float, output: float}
     */
    private function getPricing(string $model): array
    {
        // Try exact match first
        if (isset(self::PRICING[$model])) {
            return self::PRICING[$model];
        }

        // Try partial match (e.g., "gpt-4-turbo-preview" -> "gpt-4-turbo")
        foreach (self::PRICING as $key => $pricing) {
            if (str_starts_with($model, $key)) {
                return $pricing;
            }
        }

        // Fallback to default
        return self::PRICING['default'];
    }
}
