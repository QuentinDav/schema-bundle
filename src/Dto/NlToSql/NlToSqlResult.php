<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Dto\NlToSql;

/**
 * Result of a Natural Language to SQL generation.
 *
 * This DTO contains the generated SQL query, confidence score,
 * explanation, and optional cost information if AI was used.
 */
final readonly class NlToSqlResult
{
    /**
     * @param array<string, mixed> $entities    List of entities used in the query
     * @param array<array<string, mixed>> $paths Relation paths used
     */
    public function __construct(
        public bool $success,
        public string $sql = '',
        public float $confidence = 0.0,
        public string $explanation = '',
        public array $entities = [],
        public array $paths = [],
        public string $provider = 'local',
        public ?CostInfo $costInfo = null,
        public ?string $error = null,
        public ?string $message = null,
        public array $suggestions = [],
    ) {
    }

    public function toArray(): array
    {
        $result = [
            'success' => $this->success,
            'sql' => $this->sql,
            'confidence' => $this->confidence,
            'explanation' => $this->explanation,
            'entities' => $this->entities,
            'paths' => $this->paths,
            'provider' => $this->provider,
        ];

        if ($this->costInfo !== null) {
            $result['cost'] = $this->costInfo->toArray();
        }

        if (!$this->success) {
            $result['error'] = $this->error;
            $result['message'] = $this->message;
            $result['suggestions'] = $this->suggestions;
        }

        return $result;
    }

    /**
     * Create a failure result.
     */
    public static function failure(
        string $error,
        string $message,
        array $suggestions = [],
        string $provider = 'local'
    ): self {
        return new self(
            success: false,
            error: $error,
            message: $message,
            suggestions: $suggestions,
            provider: $provider
        );
    }
}
