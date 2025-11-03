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
        // Analyze the user's prompt to extract entities and fields
        $analysis = $this->queryAnalyzer->analyze($userPrompt, $allEntities);

        // Determine which entities to include in the schema
        $relevantEntities = $this->getRelevantEntities($analysis, $allEntities);

        // Build JSON schema
        $schemaJson = $this->buildSchemaJson($relevantEntities);

        // Build system prompt with structured format
        $systemPrompt = $this->buildSystemPrompt($schemaJson);

        // Build user prompt
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

            // Add relations/associations
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
     * If entities are mentioned in the prompt, return those + their related entities.
     * Otherwise, return all entities (fallback for broad queries).
     *
     * @param array{mentionedEntities: array, mentionedFields: array} $analysis
     * @param array<int, array<string, mixed>> $allEntities
     * @return array<int, array<string, mixed>>
     */
    private function getRelevantEntities(array $analysis, array $allEntities): array
    {
        $mentionedEntities = $analysis['mentionedEntities'] ?? [];
        $mentionedFields = $analysis['mentionedFields'] ?? [];

        // If nothing was detected, return all entities (AI will figure it out)
        if (empty($mentionedEntities) && empty($mentionedFields)) {
            return $allEntities;
        }

        // Collect mentioned entity names
        $relevantEntityNames = [];
        foreach ($mentionedEntities as $entity) {
            $relevantEntityNames[] = $entity['name'] ?? '';
        }

        // Add entities from mentioned fields
        foreach ($mentionedFields as $fieldInfo) {
            $entity = $fieldInfo['entity'] ?? null;
            if ($entity) {
                $name = $entity['name'] ?? '';
                if (!in_array($name, $relevantEntityNames, true)) {
                    $relevantEntityNames[] = $name;
                }
            }
        }

        // Find related entities (entities that have relations to mentioned entities)
        $extendedEntities = [];
        foreach ($allEntities as $entity) {
            $entityName = $entity['name'] ?? '';

            // Include if mentioned
            if (in_array($entityName, $relevantEntityNames, true)) {
                $extendedEntities[$entityName] = $entity;
                continue;
            }

            // Include if has relation to a mentioned entity
            foreach ($entity['associations'] ?? [] as $assoc) {
                $target = $assoc['targetEntity'] ?? '';
                if (in_array($target, $relevantEntityNames, true)) {
                    $extendedEntities[$entityName] = $entity;
                    break;
                }
            }
        }

        // If we have too few entities (< 2), return all (better for AI to have full context)
        if (count($extendedEntities) < 2 && count($allEntities) <= 20) {
            return $allEntities;
        }

        return array_values($extendedEntities);
    }
}
