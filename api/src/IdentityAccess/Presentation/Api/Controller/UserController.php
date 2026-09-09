<?php

namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Application\Command\CreateUser\CreateUserCommand;
use App\IdentityAccess\Application\Command\CreateUser\CreateUserHandler;
use App\IdentityAccess\Application\Command\SuspendUser\SuspendUserCommand;
use App\IdentityAccess\Application\Command\SuspendUser\SuspendUserHandler;
use App\IdentityAccess\Application\Command\UpdateUser\UpdateUserCommand;
use App\IdentityAccess\Application\Command\UpdateUser\UpdateUserHandler;
use App\IdentityAccess\Application\Query\GetUser\GetUserHandler;
use App\IdentityAccess\Application\Query\GetUser\GetUserQuery;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersHandler;
use App\IdentityAccess\Application\Query\ListUsers\ListUsersQuery;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Application\Service\AvatarUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

#[Route('/api/users')]
final class UserController extends AbstractController
{
    #[Route('', name: 'api_users_list', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function list(Request $request, ListUsersHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new ListUsersQuery(
            from: $request->query->get('from'),
            to: $request->query->get('to'),
        )));
    }

    #[Route('', name: 'api_users_create', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function create(Request $request, CreateUserHandler $handler): JsonResponse
    {
        $data = $request->toArray();

        $result = $handler->handle(new CreateUserCommand(
            prenom: $data['prenom'] ?? '',
            nom: $data['nom'] ?? '',
            telephone: $data['telephone'] ?? '',
            login: $data['login'] ?? '',
            password: $data['password'] ?? '',
            roleCode: $data['role'] ?? 'AGENT',
        ));

        return $this->json($result->toArray(), Response::HTTP_CREATED);
    }

    #[Route('/{id}/photo', name: 'api_users_photo_upload', methods: ['POST'], priority: 10)]
    #[IsGranted('ROLE_ADMIN')]
    public function uploadPhoto(
        string $id,
        Request $request,
        UtilisateurRepositoryInterface $utilisateurRepository,
        AvatarUploadService $avatarUploadService,
        GetUserHandler $getUserHandler,
    ): JsonResponse {
        $utilisateur = $utilisateurRepository->findById(Uuid::fromString($id));
        if (null === $utilisateur || !$utilisateur->isEnabled()) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Fichier image requis (champ file).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $photoUrl = $avatarUploadService->upload(AvatarUploadService::TYPE_UTILISATEURS, $utilisateur->getId(), $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $utilisateur->setPhotoUrl($photoUrl);
        $utilisateurRepository->save($utilisateur);

        return $this->json($getUserHandler->handle(new GetUserQuery($id))->toArray());
    }

    #[Route('/{id}/photo', name: 'api_users_photo_delete', methods: ['DELETE'], priority: 10)]
    #[IsGranted('ROLE_ADMIN')]
    public function deletePhoto(
        string $id,
        UtilisateurRepositoryInterface $utilisateurRepository,
        AvatarUploadService $avatarUploadService,
        GetUserHandler $getUserHandler,
    ): JsonResponse {
        $utilisateur = $utilisateurRepository->findById(Uuid::fromString($id));
        if (null === $utilisateur || !$utilisateur->isEnabled()) {
            return $this->json(['error' => 'Utilisateur introuvable'], Response::HTTP_NOT_FOUND);
        }

        $avatarUploadService->clear(AvatarUploadService::TYPE_UTILISATEURS, $utilisateur->getId());
        $utilisateur->setPhotoUrl(null);
        $utilisateurRepository->save($utilisateur);

        return $this->json($getUserHandler->handle(new GetUserQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_users_get', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function get(string $id, GetUserHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new GetUserQuery($id))->toArray());
    }

    #[Route('/{id}', name: 'api_users_update', methods: ['PUT'])]
    #[IsGranted('ROLE_ADMIN')]
    public function update(string $id, Request $request, UpdateUserHandler $handler): JsonResponse
    {
        $data = $request->toArray();

        $result = $handler->handle(new UpdateUserCommand(
            id: $id,
            prenom: $data['prenom'] ?? null,
            nom: $data['nom'] ?? null,
            telephone: $data['telephone'] ?? null,
            login: $data['login'] ?? null,
            password: $data['password'] ?? null,
            roleCode: isset($data['role']) ? (string) $data['role'] : null,
            isActive: isset($data['isActive']) ? (bool) $data['isActive'] : null,
        ));

        return $this->json($result->toArray());
    }

    #[Route('/{id}/suspend', name: 'api_users_suspend', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function suspend(string $id, SuspendUserHandler $handler): JsonResponse
    {
        return $this->json($handler->handle(new SuspendUserCommand($id))->toArray());
    }
}
