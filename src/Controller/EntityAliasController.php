<?php

declare(strict_types=1);

namespace Qd\SchemaBundle\Controller;

use Qd\SchemaBundle\Entity\EntityAlias;
use Qd\SchemaBundle\Repository\EntityAliasRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/schema-doc/api/aliases', name: 'qd_schema_alias_')]
class EntityAliasController extends AbstractController
{
    public function __construct(
        private readonly EntityAliasRepository $aliasRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Get all aliases grouped by entity.
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(): JsonResponse
    {
        $aliases = $this->aliasRepository->findAllGroupedByEntity();

        return new JsonResponse($aliases);
    }

    /**
     * Get aliases for a specific entity.
     */
    #[Route('/entity/{fqcn}', name: 'by_entity', methods: ['GET'])]
    public function byEntity(string $fqcn): JsonResponse
    {
        $fqcn = urldecode($fqcn);

        $aliases = $this->aliasRepository->findByEntity($fqcn);

        return new JsonResponse(array_map(fn($a) => $a->toArray(), $aliases));
    }

    /**
     * Create a new alias.
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['entityFqcn']) || !isset($data['alias'])) {
            return new JsonResponse(
                ['error' => 'entityFqcn and alias are required'],
                Response::HTTP_BAD_REQUEST
            );
        }

        if ($this->aliasRepository->aliasExists($data['alias'])) {
            return new JsonResponse(
                ['error' => 'This alias is already used by another entity'],
                Response::HTTP_CONFLICT
            );
        }

        $alias = new EntityAlias();
        $alias->setEntityFqcn($data['entityFqcn']);
        $alias->setAlias($data['alias']);

        if (isset($data['language'])) {
            $alias->setLanguage($data['language']);
        }

        if (isset($data['description'])) {
            $alias->setDescription($data['description']);
        }

        $errors = $this->validator->validate($alias);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->aliasRepository->save($alias);

        return new JsonResponse($alias->toArray(), Response::HTTP_CREATED);
    }

    /**
     * Update an existing alias.
     */
    #[Route('/{id}', name: 'update', methods: ['PUT', 'PATCH'])]
    public function update(int $id, Request $request): JsonResponse
    {
        $alias = $this->aliasRepository->find($id);

        if (!$alias) {
            return new JsonResponse(
                ['error' => 'Alias not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $data = json_decode($request->getContent(), true);

        if (isset($data['alias'])) {
            if ($this->aliasRepository->aliasExists($data['alias'], $id)) {
                return new JsonResponse(
                    ['error' => 'This alias is already used by another entity'],
                    Response::HTTP_CONFLICT
                );
            }
            $alias->setAlias($data['alias']);
        }

        if (isset($data['language'])) {
            $alias->setLanguage($data['language']);
        }

        if (isset($data['description'])) {
            $alias->setDescription($data['description']);
        }

        $errors = $this->validator->validate($alias);
        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[$error->getPropertyPath()] = $error->getMessage();
            }
            return new JsonResponse(
                ['errors' => $errorMessages],
                Response::HTTP_BAD_REQUEST
            );
        }

        $this->aliasRepository->save($alias);

        return new JsonResponse($alias->toArray());
    }

    /**
     * Delete an alias.
     */
    #[Route('/{id}', name: 'delete', methods: ['DELETE'])]
    public function delete(int $id): JsonResponse
    {
        $alias = $this->aliasRepository->find($id);

        if (!$alias) {
            return new JsonResponse(
                ['error' => 'Alias not found'],
                Response::HTTP_NOT_FOUND
            );
        }

        $this->aliasRepository->remove($alias);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get alias to entity map for NL query resolution.
     */
    #[Route('/map', name: 'map', methods: ['GET'])]
    public function getMap(): JsonResponse
    {
        $map = $this->aliasRepository->getAliasToEntityMap();

        return new JsonResponse($map);
    }
}
