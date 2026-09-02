<?php

namespace App\IdentityAccess\Presentation\Api\Controller;

use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordCommand;
use App\IdentityAccess\Application\Command\ChangePassword\ChangePasswordHandler;
use App\IdentityAccess\Application\Command\Login\LoginHandler;
use App\IdentityAccess\Application\Query\GetMe\GetMeHandler;
use App\IdentityAccess\Application\Service\RefreshTokenService;
use App\IdentityAccess\Domain\Entity\Utilisateur;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
