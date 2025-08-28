<?php

namespace App\Twig;

use App\Service\AuthenticationService;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class AuthExtension extends AbstractExtension
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('is_authenticated', [$this, 'isAuthenticated']),
            new TwigFunction('get_current_user', [$this, 'getCurrentUser']),
            new TwigFunction('get_user_info', [$this, 'getUserInfo']),
        ];
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    public function isAuthenticated(): bool
    {
        return $this->authService->isAuthenticated();
    }

    /**
     * Récupère le nom d'utilisateur actuel
     */
    public function getCurrentUser(): ?string
    {
        $userInfo = $this->authService->getUserInfo();
        return $userInfo ? $userInfo['username'] : null;
    }

    /**
     * Récupère toutes les informations de l'utilisateur
     */
    public function getUserInfo(): ?array
    {
        return $this->authService->getUserInfo();
    }
}

