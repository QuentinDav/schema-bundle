<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Service\NlToSql;

/**
 * Builds optimized prompts for LLM-based SQL generation.
 *
 * Uses structured JSON schema format and analyzes prompts to send only relevant entities.
 */
final class PromptBuilder
{
    public function __construct(
        private readonly NlQueryAnalyzer $queryAnalyzer,
        private readonly PathFinder $pathFinder,
    ) {
    }

    /**
     * Build complete prompt with analyzed schema context.
     *
     * This method analyzes the user's prompt to extract mentioned entities,
     * finds relevant relations, and builds a structured JSON schema.
     *
     * @param string $userPrompt User's natural language query
     * @param array<int, array<string, mixed>> $allEntities All available entities
     * @return array{system: string, user: string, analysis: array}
     */
    public function buildPrompt(string $userPrompt, array $allEntities): array
    {
        $analysis = $this->queryAnalyzer->analyze($userPrompt, $allEntities);

        $relevantEntities = $this->getRelevantEntities($analysis, $allEntities);

        $schemaJson = $this->buildSchemaJson($relevantEntities);

        $systemPrompt = $this->buildSystemPrompt($schemaJson);

        $userPromptFormatted = $this->buildUserPrompt($userPrompt);

        return [
            'system' => $systemPrompt,
            'user' => $userPromptFormatted,
            'analysis' => $analysis,
        ];
    }

    /**
     * Build system prompt (for backward compatibility).
     *
     * @param array<int, array<string, mixed>> $entities Entity metadata
     */
    public function buildSystemPrompt(string $schemaJson): string
    {
        return <<<PROMPT
You are an SQL query generator.

Given the following database schema (JSON format) and a user request, your task is to produce a single, valid SQL query.

**Instructions:**
1. Analyze the user's request to identify:
   - Which tables (entities) are involved
   - Which fields should be selected
   - What conditions (WHERE clauses) are needed
   - What JOINs are required to connect entities

2. Generate clean SQL:
   - Use proper table aliases (e.g., `u` for User, `a` for Address)
   - Include only necessary JOINs
   - Use correct field names from the schema
   - Follow standard SQL syntax (MySQL/PostgreSQL compatible)

3. Return your response as a valid JSON object with this structure:
{
  "sql": "SELECT ... FROM ... WHERE ...",
  "explanation": "Brief explanation of what this query does",
  "confidence": 0.85
}

**Database Schema:**

{$schemaJson}

**Important:**
- Return ONLY the JSON object, nothing else
- Use table aliases for readability
- Ensure all field names match the schema exactly
- If the request is ambiguous, provide your best interpretation with lower confidence (< 0.7)
PROMPT;
    }

    /**
     * Build user prompt with the natural language query.
     */
    public function buildUserPrompt(string $naturalLanguageQuery): string
    {
        return <<<USERPROMPT
User request:
"{$naturalLanguageQuery}"

Generate the SQL query for this request based on the provided schema.
USERPROMPT;
    }

    /**
     * Build JSON schema from entities.
     *
     * @param array<int, array<string, mixed>> $entities
     */
    private function buildSchemaJson(array $entities): string
    {
        $schemaData = ['entities' => []];

        foreach ($entities as $entity) {
            $entityData = [
                'name' => $entity['name'] ?? 'Unknown',
                'table' => $entity['tableName'] ?? strtolower($entity['name'] ?? 'unknown'),
                'fields' => [],
                'relations' => [],
            ];

            // Add fields
            foreach ($entity['fields'] ?? [] as $field) {
                $entityData['fields'][] = [
                    'name' => $field['name'] ?? '',
                    'type' => $field['type'] ?? 'string',
                    'nullable' => $field['nullable'] ?? false,
                    'primary' => $field['isPrimaryKey'] ?? false,
                ];
            }

            foreach ($entity['associations'] ?? [] as $assoc) {
                $entityData['relations'][] = [
                    'field' => $assoc['fieldName'] ?? '',
                    'target' => $assoc['targetEntity'] ?? '',
                    'type' => $assoc['type'] ?? '',
                    'mappedBy' => $assoc['mappedBy'] ?? null,
                    'inversedBy' => $assoc['inversedBy'] ?? null,
                ];
            }

            $schemaData['entities'][] = $entityData;
        }

        return json_encode($schemaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Get relevant entities based on analysis.
     *
     * Uses NlQueryAnalyzer (with alias support) to detect mentioned entities.
     * Returns only relevant entities + their direct relations to reduce token usage.
     *
     * When entities are detected via aliases or direct mentions:
     * - Include the mentioned entities
     * - Include entities with direct relations (1-hop away)
     * - Optionally include entities in the join path between mentioned entities
     *
     * @param array{mentionedEntities: array, mentionedFields: array, resolvedAliases: array} $analysis
     * @param array<int, array<string, mixed>> $allEntities
     * @return array<int, array<string, mixed>>
     */
    private function getRelevantEntities(array $analysis, array $allEntities): array
    {
        $mentionedEntities = $analysis['mentionedEntities'] ?? [];
        $mentionedFields = $analysis['mentionedFields'] ?? [];
        $resolvedAliases = $analysis['resolvedAliases'] ?? [];

        if (empty($mentionedEntities) && empty($mentionedFields)) {
            return $allEntities;
        }

        $relevantEntityNames = [];
        foreach ($mentionedEntities as $entity) {
            $relevantEntityNames[] = $entity['name'] ?? '';
        }

        foreach ($mentionedFields as $fieldInfo) {
            $entity = $fieldInfo['entity'] ?? null;
            if ($entity) {
                $name = $entity['name'] ?? '';
                if (!in_array($name, $relevantEntityNames, true)) {
                    $relevantEntityNames[] = $name;
                }
            }
        }

        $extendedEntities = [];
        foreach ($allEntities as $entity) {
            $entityName = $entity['name'] ?? '';

            if (in_array($entityName, $relevantEntityNames, true)) {
                $extendedEntities[$entityName] = $entity;
                continue;
            }

            foreach ($entity['associations'] ?? [] as $assoc) {
                $target = $assoc['targetEntity'] ?? '';
                if (in_array($target, $relevantEntityNames, true)) {
                    $extendedEntities[$entityName] = $entity;
                    break;
                }
            }
        }

        if (empty($extendedEntities)) {
            return $allEntities;
        }

        return array_values($extendedEntities);
    }
}
