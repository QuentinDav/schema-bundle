<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Controller;

use Qd\SchemaBundle\Service\NlToSql\NlToSqlOrchestrator;
use Qd\SchemaBundle\Service\SchemaExtractor;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * API controller for Natural Language to SQL generation.
 */
final class NlToSqlController extends AbstractController
{
    public function __construct(
        private readonly NlToSqlOrchestrator $orchestrator,
        private readonly SchemaExtractor $schemaExtractor,
        private readonly bool $enabled = true,
    ) {
    }

    /**
     * Generate SQL from natural language prompt.
     *
     * POST /api/nl-to-sql/generate
     *
     * Body:
     * {
     *   "prompt": "Get all users with their addresses",
     *   "strategy": "ai"
     * }
     */
    public function generate(Request $request): JsonResponse
    {
        if (!$this->enabled) {
            return $this->json([
                'success' => false,
                'error' => 'FEATURE_DISABLED',
                'message' => 'Natural Language to SQL feature is disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'INVALID_REQUEST',
                'message' => 'Invalid JSON in request body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prompt = $data['prompt'] ?? '';
        $strategy = $data['strategy'] ?? null;

        if (empty(trim($prompt))) {
            return $this->json([
                'success' => false,
                'error' => 'EMPTY_PROMPT',
                'message' => 'Prompt cannot be empty.',
            ], Response::HTTP_BAD_REQUEST);
        }

        if ($strategy !== null && !in_array($strategy, ['local', 'ai', 'hybrid'], true)) {
            return $this->json([
                'success' => false,
                'error' => 'INVALID_STRATEGY',
                'message' => 'Strategy must be one of: local, ai, hybrid.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $entities = $this->schemaExtractor->extract();

            $result = $this->orchestrator->generate($prompt, $entities, $strategy);

            return $this->json($result->toArray());
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'GENERATION_FAILED',
                'message' => 'Failed to generate SQL: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Estimate cost for a prompt.
     *
     * POST /api/nl-to-sql/estimate-cost
     *
     * Body:
     * {
     *   "prompt": "Get all users with their addresses",
     *   "strategy": "ai"
     * }
     */
    public function estimateCost(Request $request): JsonResponse
    {
        if (!$this->enabled) {
            return $this->json([
                'success' => false,
                'error' => 'FEATURE_DISABLED',
                'message' => 'Natural Language to SQL feature is disabled.',
            ], Response::HTTP_FORBIDDEN);
        }

        $data = json_decode($request->getContent(), true);

        if (!is_array($data)) {
            return $this->json([
                'success' => false,
                'error' => 'INVALID_REQUEST',
                'message' => 'Invalid JSON in request body.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $prompt = $data['prompt'] ?? '';
        $strategy = $data['strategy'] ?? 'ai';

        if (empty(trim($prompt))) {
            return $this->json([
                'success' => false,
                'error' => 'EMPTY_PROMPT',
                'message' => 'Prompt cannot be empty.',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $estimate = $this->orchestrator->estimateCost($prompt, $strategy);

            if ($estimate === null) {
                return $this->json([
                    'success' => false,
                    'error' => 'ESTIMATION_UNAVAILABLE',
                    'message' => 'Cost estimation not available for this strategy.',
                ]);
            }

            return $this->json([
                'success' => true,
                'estimate' => $estimate->toArray(),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'error' => 'ESTIMATION_FAILED',
                'message' => 'Failed to estimate cost: ' . $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get NL to SQL configuration status.
     *
     * GET /api/nl-to-sql/status
     */
    public function status(): JsonResponse
    {
        $aiAvailable = $this->orchestrator->isAiAvailable();

        return $this->json([
            'enabled' => $this->enabled,
            'ai_available' => $aiAvailable,
            'ai_model' => $aiAvailable ? $this->orchestrator->getAiModelName() : null,
        ]);
    }
}
