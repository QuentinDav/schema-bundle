<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

/**
 * Analyzes natural language queries to extract entities, fields, and relations.
 *
 * This is the backend equivalent of the frontend NLP logic (frontend/src/utils/nlp).
 * It tokenizes the user's prompt and identifies:
 * - Which entities are mentioned
 * - Which fields are referenced
 * - What relations need to be joined
 */
final class NlQueryAnalyzer
{
    /**
     * Common SQL keywords to ignore during entity/field detection.
     */
    private const SQL_KEYWORDS = [
        'select', 'from', 'where', 'join', 'left', 'right', 'inner', 'outer',
        'on', 'and', 'or', 'not', 'in', 'like', 'is', 'null', 'order', 'by',
        'group', 'having', 'limit', 'offset', 'as', 'asc', 'desc', 'distinct',
        'count', 'sum', 'avg', 'max', 'min', 'all', 'any', 'between', 'case',
        'when', 'then', 'else', 'end', 'exists', 'union', 'except', 'intersect',
    ];

    /**
     * Common words to ignore (articles, prepositions, etc.).
     */
    private const STOP_WORDS = [
        'a', 'an', 'the', 'of', 'to', 'for', 'with', 'at', 'by', 'from',
        'in', 'into', 'on', 'onto', 'off', 'out', 'over', 'under', 'again',
        'further', 'then', 'once', 'here', 'there', 'when', 'where', 'why',
        'how', 'all', 'both', 'each', 'few', 'more', 'most', 'other', 'some',
        'such', 'only', 'own', 'same', 'so', 'than', 'too', 'very', 'can',
        'will', 'just', 'should', 'now', 'my', 'your', 'his', 'her', 'its',
        'our', 'their', 'what', 'which', 'who', 'whom', 'this', 'that', 'these',
        'those', 'am', 'is', 'are', 'was', 'were', 'be', 'been', 'being',
        'have', 'has', 'had', 'having', 'do', 'does', 'did', 'doing', 'get',
        'show', 'list', 'find', 'give', 'give me', 'retrieve', 'fetch',
    ];

    /**
     * Analyze a natural language prompt to extract relevant entities and fields.
     *
     * @param string $prompt The user's natural language query
     * @param array<int, array<string, mixed>> $allEntities All available entities
     * @return array{
     *     tokens: array<string>,
     *     mentionedEntities: array<array<string, mixed>>,
     *     mentionedFields: array<array{entity: array<string, mixed>, field: string}>,
     *     lexicon: array<string, array<string>>
     * }
     */
    public function analyze(string $prompt, array $allEntities): array
    {
        // 1. Tokenize the prompt
        $tokens = $this->tokenize($prompt);

        // 2. Build lexicon (entity name variations)
        $lexicon = $this->buildLexicon($allEntities);

        // 3. Resolve entities mentioned in the prompt
        $mentionedEntities = $this->resolveEntities($tokens, $allEntities, $lexicon);

        // 4. Resolve fields mentioned in the prompt
        $mentionedFields = $this->resolveFields($tokens, $allEntities, $mentionedEntities);

        return [
            'tokens' => $tokens,
            'mentionedEntities' => $mentionedEntities,
            'mentionedFields' => $mentionedFields,
            'lexicon' => $lexicon,
        ];
    }

    /**
     * Tokenize a natural language string into words.
     *
     * @return array<string>
     */
    private function tokenize(string $text): array
    {
        // Convert to lowercase and split by spaces/punctuation
        $text = mb_strtolower($text);

        // Split by whitespace and common punctuation, keeping the text
        $tokens = preg_split('/[\s,;.!?()]+/', $text, -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_filter($tokens ?? []));
    }

    /**
     * Build a lexicon mapping entity names to their variations.
     *
     * For each entity, create variations like:
     * - Singular/plural forms
     * - With/without underscores
     * - Table name variants
     *
     * @param array<int, array<string, mixed>> $entities
     * @return array<string, array<string>> Map of entity name to variations
     */
    private function buildLexicon(array $entities): array
    {
        $lexicon = [];

        foreach ($entities as $entity) {
            $name = $entity['name'] ?? '';
            $tableName = $entity['tableName'] ?? '';

            if (empty($name)) {
                continue;
            }

            $variations = [
                mb_strtolower($name),
                mb_strtolower($tableName),
            ];

            // Add plural forms (simple english pluralization)
            $variations[] = $this->pluralize(mb_strtolower($name));
            $variations[] = $this->pluralize(mb_strtolower($tableName));

            // Add singular forms
            $variations[] = $this->singularize(mb_strtolower($name));

            // Remove underscores
            $variations[] = str_replace('_', '', mb_strtolower($name));
            $variations[] = str_replace('_', '', mb_strtolower($tableName));

            // Remove duplicates and empty strings
            $variations = array_unique(array_filter($variations));

            $lexicon[$name] = array_values($variations);
        }

        return $lexicon;
    }

    /**
     * Resolve which entities are mentioned in the tokens.
     *
     * @param array<string> $tokens
     * @param array<int, array<string, mixed>> $allEntities
     * @param array<string, array<string>> $lexicon
     * @return array<array<string, mixed>> Entities that were found in the prompt
     */
    private function resolveEntities(array $tokens, array $allEntities, array $lexicon): array
    {
        $found = [];
        $foundNames = [];

        foreach ($tokens as $token) {
            $token = mb_strtolower($token);

            // Skip SQL keywords and stop words
            if ($this->isIgnoredWord($token)) {
                continue;
            }

            // Check if token matches any entity variation
            foreach ($allEntities as $entity) {
                $entityName = $entity['name'] ?? '';
                if (empty($entityName) || in_array($entityName, $foundNames, true)) {
                    continue;
                }

                $variations = $lexicon[$entityName] ?? [];
                if (in_array($token, $variations, true)) {
                    $found[] = $entity;
                    $foundNames[] = $entityName;
                    break;
                }
            }
        }

        return $found;
    }

    /**
     * Resolve which fields are mentioned in the tokens.
     *
     * @param array<string> $tokens
     * @param array<int, array<string, mixed>> $allEntities
     * @param array<array<string, mixed>> $mentionedEntities
     * @return array<array{entity: array<string, mixed>, field: string}>
     */
    private function resolveFields(array $tokens, array $allEntities, array $mentionedEntities): array
    {
        $fields = [];
        $seenFields = [];

        foreach ($tokens as $i => $token) {
            $token = mb_strtolower($token);

            // Skip SQL keywords and stop words
            if ($this->isIgnoredWord($token)) {
                continue;
            }

            // Try to match field patterns like "email", "user email", "email of user"
            // Pattern 1: "field of entity" (e.g., "email of user")
            if (isset($tokens[$i + 1], $tokens[$i + 2]) && mb_strtolower($tokens[$i + 1]) === 'of') {
                $fieldName = $token;
                $entityToken = mb_strtolower($tokens[$i + 2]);

                $entity = $this->findEntityByToken($entityToken, $allEntities);
                if ($entity && $this->entityHasField($entity, $fieldName)) {
                    $key = $entity['name'] . '.' . $fieldName;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $fieldName];
                        $seenFields[] = $key;
                    }
                }
            }

            // Pattern 2: "entity field" or "entity.field" (e.g., "user email")
            if (isset($tokens[$i + 1])) {
                $entityToken = $token;
                $fieldName = mb_strtolower($tokens[$i + 1]);

                $entity = $this->findEntityByToken($entityToken, $allEntities);
                if ($entity && $this->entityHasField($entity, $fieldName)) {
                    $key = $entity['name'] . '.' . $fieldName;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $fieldName];
                        $seenFields[] = $key;
                    }
                }
            }

            // Pattern 3: Field alone (check in mentioned entities first, then all)
            $entitiesToCheck = !empty($mentionedEntities) ? $mentionedEntities : $allEntities;
            foreach ($entitiesToCheck as $entity) {
                if ($this->entityHasField($entity, $token)) {
                    $key = $entity['name'] . '.' . $token;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $token];
                        $seenFields[] = $key;
                        break; // Only take first match per field
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Find an entity by a token (could be entity name or variation).
     *
     * @param string $token
     * @param array<int, array<string, mixed>> $entities
     * @return array<string, mixed>|null
     */
    private function findEntityByToken(string $token, array $entities): ?array
    {
        $token = mb_strtolower($token);

        foreach ($entities as $entity) {
            $name = mb_strtolower($entity['name'] ?? '');
            $tableName = mb_strtolower($entity['tableName'] ?? '');

            if ($token === $name || $token === $tableName) {
                return $entity;
            }

            // Check pluralized forms
            if ($token === $this->pluralize($name) || $token === $this->singularize($name)) {
                return $entity;
            }
        }

        return null;
    }

    /**
     * Check if an entity has a specific field.
     *
     * @param array<string, mixed> $entity
     * @param string $fieldName
     */
    private function entityHasField(array $entity, string $fieldName): bool
    {
        $fields = $entity['fields'] ?? [];
        $fieldName = mb_strtolower($fieldName);

        foreach ($fields as $field) {
            if (mb_strtolower($field['name'] ?? '') === $fieldName) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if a word should be ignored (SQL keyword or stop word).
     */
    private function isIgnoredWord(string $word): bool
    {
        $word = mb_strtolower($word);
        return in_array($word, self::SQL_KEYWORDS, true)
            || in_array($word, self::STOP_WORDS, true);
    }

    /**
     * Simple English pluralization.
     */
    private function pluralize(string $word): string
    {
        if (empty($word)) {
            return $word;
        }

        // Simple rules
        if (str_ends_with($word, 'y')) {
            return substr($word, 0, -1) . 'ies';
        }

        if (str_ends_with($word, 's') || str_ends_with($word, 'x') || str_ends_with($word, 'ch') || str_ends_with($word, 'sh')) {
            return $word . 'es';
        }

        return $word . 's';
    }

    /**
     * Simple English singularization.
     */
    private function singularize(string $word): string
    {
        if (empty($word)) {
            return $word;
        }

        if (str_ends_with($word, 'ies')) {
            return substr($word, 0, -3) . 'y';
        }

        if (str_ends_with($word, 'es')) {
            return substr($word, 0, -2);
        }

        if (str_ends_with($word, 's')) {
            return substr($word, 0, -1);
        }

        return $word;
    }
}
