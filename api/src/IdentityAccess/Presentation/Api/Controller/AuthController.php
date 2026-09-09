<?php

namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordHandler;
use App\IdentityAccess\Application\Command\Login\LoginHandler;
use App\IdentityAccess\Application\Query\GetMe\GetMeHandler;
use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use App\IdentityAccess\Domain\Repository\UtilisateurRepositoryInterface;
use App\SharedKernel\Application\Service\AvatarUploadService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api')]
final class AuthController extends AbstractController
{
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request, LoginHandler $handler): JsonResponse
    {
        $data = $request->toArray();
        $login = $data['login'] ?? '';
        $password = $data['password'] ?? '';

        if ('' === $login || '' === $password) {
            return $this->json(['error' => 'login et password requis'], 400);
        }

        $pair = $handler->handle($login, $password);
        if (null === $pair) {
            return $this->json(['error' => 'Identifiants invalides'], 401);
        }

        return $this->json($pair);
    }

    #[Route('/me', name: 'api_me', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function me(GetMeHandler $handler): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        return $this->json($handler->handle($user));
    }

    #[Route('/me/change-password', name: 'api_me_change_password', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function changePassword(Request $request, ChangePasswordHandler $handler): JsonResponse
    {
        /** @var Utilisateur $user */
        $user = $this->getUser();
        $data = $request->toArray();

        $handler->handle($user, new ChangePasswordCommand(
            currentPassword: (string) ($data['currentPassword'] ?? ''),
            newPassword: (string) ($data['newPassword'] ?? ''),
        ));

        return $this->json(['ok' => true], Response::HTTP_OK);
    }

    #[Route('/me/photo', name: 'api_me_photo_upload', methods: ['POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function uploadMyPhoto(
        Request $request,
        UtilisateurRepositoryInterface $utilisateurRepository,
        AvatarUploadService $avatarUploadService,
        GetMeHandler $getMeHandler,
    ): JsonResponse {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $file = $request->files->get('file');
        if (!$file instanceof UploadedFile) {
            return $this->json(['error' => 'Fichier image requis (champ file).'], Response::HTTP_BAD_REQUEST);
        }

        try {
            $photoUrl = $avatarUploadService->upload(AvatarUploadService::TYPE_UTILISATEURS, $user->getId(), $file);
        } catch (\InvalidArgumentException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_BAD_REQUEST);
        } catch (\RuntimeException $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        $user->setPhotoUrl($photoUrl);
        $utilisateurRepository->save($user);

        return $this->json($getMeHandler->handle($user));
    }

    #[Route('/me/photo', name: 'api_me_photo_delete', methods: ['DELETE'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function deleteMyPhoto(
        UtilisateurRepositoryInterface $utilisateurRepository,
        AvatarUploadService $avatarUploadService,
        GetMeHandler $getMeHandler,
    ): JsonResponse {
        /** @var Utilisateur $user */
        $user = $this->getUser();

        $avatarUploadService->clear(AvatarUploadService::TYPE_UTILISATEURS, $user->getId());
        $user->setPhotoUrl(null);
        $utilisateurRepository->save($user);

        return $this->json($getMeHandler->handle($user));
    }

    #[Route('/token/refresh', name: 'api_token_refresh', methods: ['POST'])]
    public function refresh(Request $request, RefreshTokenService $refreshTokenService): JsonResponse
    {
        $data = $request->toArray();
        $refreshToken = $data['refresh_token'] ?? '';
        if ('' === $refreshToken) {
            return $this->json(['error' => 'refresh_token requis'], 400);
        }

        $pair = $refreshTokenService->refresh($refreshToken);
        if (null === $pair) {
            return $this->json(['error' => 'Token invalide'], 401);
        }

        return $this->json($pair);
    }
}
