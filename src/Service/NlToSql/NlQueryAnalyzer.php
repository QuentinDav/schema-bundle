<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

use Qd\SchemaBundle\Repository\EntityAliasRepository;

/**
 * Analyzes natural language queries to extract entities, fields, and relations.
 *
 * This is the backend equivalent of the frontend NLP logic (frontend/src/utils/nlp).
 * It tokenizes the user's prompt and identifies:
 * - Which entities are mentioned (including via aliases)
 * - Which fields are referenced
 * - What relations need to be joined
 *
 * Supports entity aliases to improve query understanding and reduce token usage.
 */
final class NlQueryAnalyzer
{
    public function __construct(
        private readonly EntityAliasRepository $aliasRepository
    ) {
    }
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
     *     lexicon: array<string, array<string>>,
     *     resolvedAliases: array<string, string>
     * }
     */
    public function analyze(string $prompt, array $allEntities): array
    {
        $aliasMap = $this->aliasRepository->getAliasToEntityMap();

        $tokens = $this->tokenize($prompt);

        $lexicon = $this->buildLexicon($allEntities, $aliasMap);

        $mentionedEntities = $this->resolveEntities($tokens, $allEntities, $lexicon, $aliasMap);

        $mentionedFields = $this->resolveFields($tokens, $allEntities, $mentionedEntities, $aliasMap);

        return [
            'tokens' => $tokens,
            'mentionedEntities' => $mentionedEntities,
            'mentionedFields' => $mentionedFields,
            'lexicon' => $lexicon,
            'resolvedAliases' => $this->getResolvedAliases($tokens, $aliasMap),
        ];
    }

    /**
     * Tokenize a natural language string into words.
     *
     * @return array<string>
     */
    private function tokenize(string $text): array
    {
        $text = mb_strtolower($text);

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
     * - User-defined aliases
     *
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, string> $aliasMap Map of alias => entityFqcn
     * @return array<string, array<string>> Map of entity name to variations
     */
    private function buildLexicon(array $entities, array $aliasMap): array
    {
        $lexicon = [];

        foreach ($entities as $entity) {
            $name = $entity['name'] ?? '';
            $tableName = $entity['tableName'] ?? '';
            $fqcn = $entity['fqcn'] ?? '';

            if (empty($name)) {
                continue;
            }

            $variations = [
                mb_strtolower($name),
                mb_strtolower($tableName),
            ];

            $variations[] = $this->pluralize(mb_strtolower($name));
            $variations[] = $this->pluralize(mb_strtolower($tableName));

            $variations[] = $this->singularize(mb_strtolower($name));

            $variations[] = str_replace('_', '', mb_strtolower($name));
            $variations[] = str_replace('_', '', mb_strtolower($tableName));

            foreach ($aliasMap as $alias => $entityFqcn) {
                if ($entityFqcn === $fqcn) {
                    $variations[] = mb_strtolower($alias);
                }
            }

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
     * @param array<string, string> $aliasMap Map of alias => entityFqcn
     * @return array<array<string, mixed>> Entities that were found in the prompt
     */
    private function resolveEntities(array $tokens, array $allEntities, array $lexicon, array $aliasMap): array
    {
        $found = [];
        $foundNames = [];

        foreach ($tokens as $token) {
            $token = mb_strtolower($token);

            if ($this->isIgnoredWord($token)) {
                continue;
            }

            if (isset($aliasMap[$token])) {
                $entityFqcn = $aliasMap[$token];

                foreach ($allEntities as $entity) {
                    if (($entity['fqcn'] ?? '') === $entityFqcn) {
                        $entityName = $entity['name'] ?? '';
                        if (!in_array($entityName, $foundNames, true)) {
                            $found[] = $entity;
                            $foundNames[] = $entityName;
                        }
                        break;
                    }
                }
                continue;
            }

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
     * @param array<string, string> $aliasMap Map of alias => entityFqcn
     * @return array<array{entity: array<string, mixed>, field: string}>
     */
    private function resolveFields(array $tokens, array $allEntities, array $mentionedEntities, array $aliasMap): array
    {
        $fields = [];
        $seenFields = [];

        foreach ($tokens as $i => $token) {
            $token = mb_strtolower($token);

            if ($this->isIgnoredWord($token)) {
                continue;
            }

            if (isset($tokens[$i + 1], $tokens[$i + 2]) && mb_strtolower($tokens[$i + 1]) === 'of') {
                $fieldName = $token;
                $entityToken = mb_strtolower($tokens[$i + 2]);

                $entity = $this->findEntityByToken($entityToken, $allEntities, $aliasMap);
                if ($entity && $this->entityHasField($entity, $fieldName)) {
                    $key = $entity['name'] . '.' . $fieldName;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $fieldName];
                        $seenFields[] = $key;
                    }
                }
            }

            if (isset($tokens[$i + 1])) {
                $entityToken = $token;
                $fieldName = mb_strtolower($tokens[$i + 1]);

                $entity = $this->findEntityByToken($entityToken, $allEntities, $aliasMap);
                if ($entity && $this->entityHasField($entity, $fieldName)) {
                    $key = $entity['name'] . '.' . $fieldName;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $fieldName];
                        $seenFields[] = $key;
                    }
                }
            }

            $entitiesToCheck = !empty($mentionedEntities) ? $mentionedEntities : $allEntities;
            foreach ($entitiesToCheck as $entity) {
                if ($this->entityHasField($entity, $token)) {
                    $key = $entity['name'] . '.' . $token;
                    if (!in_array($key, $seenFields, true)) {
                        $fields[] = ['entity' => $entity, 'field' => $token];
                        $seenFields[] = $key;
                        break;
                    }
                }
            }
        }

        return $fields;
    }

    /**
     * Find an entity by a token (could be entity name, variation, or alias).
     *
     * @param string $token
     * @param array<int, array<string, mixed>> $entities
     * @param array<string, string> $aliasMap Map of alias => entityFqcn
     * @return array<string, mixed>|null
     */
    private function findEntityByToken(string $token, array $entities, array $aliasMap): ?array
    {
        $token = mb_strtolower($token);

        if (isset($aliasMap[$token])) {
            $entityFqcn = $aliasMap[$token];
            foreach ($entities as $entity) {
                if (($entity['fqcn'] ?? '') === $entityFqcn) {
                    return $entity;
                }
            }
        }

        foreach ($entities as $entity) {
            $name = mb_strtolower($entity['name'] ?? '');
            $tableName = mb_strtolower($entity['tableName'] ?? '');

            if ($token === $name || $token === $tableName) {
                return $entity;
            }

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

    /**
     * Get which aliases were resolved in the tokens.
     * Useful for logging and debugging which aliases helped resolve entities.
     *
     * @param array<string> $tokens
     * @param array<string, string> $aliasMap Map of alias => entityFqcn
     * @return array<string, string> Map of resolved alias => entityFqcn
     */
    private function getResolvedAliases(array $tokens, array $aliasMap): array
    {
        $resolved = [];

        foreach ($tokens as $token) {
            $token = mb_strtolower($token);

            if ($this->isIgnoredWord($token)) {
                continue;
            }

            if (isset($aliasMap[$token])) {
                $resolved[$token] = $aliasMap[$token];
            }
        }

        return $resolved;
    }
}
