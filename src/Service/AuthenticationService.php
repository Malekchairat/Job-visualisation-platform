<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Session; // Import Session

class AuthenticationService
{
    private RequestStack $requestStack;
    private ?SessionInterface $session = null;

    // Identifiants codés en dur
    private const VALID_USERNAME = 'Zied Enneifer';
    private const VALID_PASSWORD = '123Wimbee@';

    public function __construct(RequestStack $requestStack)
    {
        $this->requestStack = $requestStack;
    }

    private function getSession(): SessionInterface
    {
        if ($this->session === null) {
            try {
                $this->session = $this->requestStack->getSession();
            } catch (\Symfony\Component\HttpFoundation\Exception\SessionNotFoundException $e) {
                // For CLI or non-request contexts, create a mock/empty session
                $this->session = new Session();
            }
        }
        return $this->session;
    }

    // ... The rest of your file is PERFECT. No more changes are needed below this line.
    // All calls to $this->getSession() will now work safely.

    /**
     * Authentifie un utilisateur avec les identifiants fournis
     */
    public function authenticate(string $username, string $password, bool $rememberMe = false): bool
    {
        if ($this->validateCredentials($username, $password)) {
            $this->createUserSession($username, $rememberMe);
            return true;
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur est actuellement connecté
     */
    public function isAuthenticated(): bool
    {
        $isAuthenticated = $this->getSession()->get('user_authenticated', false);
        
        if ($isAuthenticated) {
            return $this->isSessionValid();
        }

        return false;
    }

    /**
     * Déconnecte l'utilisateur
     */
    public function logout(): string
    {
        $username = $this->getSession()->get('user_username', 'Utilisateur');
        $this->getSession()->clear();
        
        return $username;
    }

    /**
     * Récupère les informations de l'utilisateur connecté
     */
    public function getUserInfo(): ?array
    {
        if (!$this->isAuthenticated()) {
            return null;
        }

        return [
            'username' => $this->getSession()->get('user_username'),
            'login_time' => $this->getSession()->get('login_time'),
            'remember_me' => $this->getSession()->get('remember_me', false),
            'session_id' => $this->getSession()->getId()
        ];
    }

    /**
     * Étend la session de l'utilisateur
     */
    public function extendSession(): void
    {
        if ($this->isAuthenticated()) {
            $this->getSession()->set('login_time', new \DateTime());
        }
    }

    /**
     * Valide les identifiants fournis
     */
    private function validateCredentials(string $username, string $password): bool
    {
        return $username === self::VALID_USERNAME && $password === self::VALID_PASSWORD;
    }

    /**
     * Crée une session utilisateur
     */
    private function createUserSession(string $username, bool $rememberMe): void
    {
        $session = $this->getSession();
        $session->set('user_authenticated', true);
        $session->set('user_username', $username);
        $session->set('login_time', new \DateTime());
        $session->set('remember_me', $rememberMe);

        if ($rememberMe) {
            $session->migrate(false, 86400 * 30); // 30 jours
        }
    }

    /**
     * Vérifie la validité de la session
     */
    private function isSessionValid(): bool
    {
        $session = $this->getSession();
        $loginTime = $session->get('login_time');
        $rememberMe = $session->get('remember_me', false);
        
        if (!$loginTime instanceof \DateTime) {
            return false;
        }

        $now = new \DateTime();
        $sessionDuration = $rememberMe ? 86400 * 30 : 86400;
        
        if (($now->getTimestamp() - $loginTime->getTimestamp()) > $sessionDuration) {
            $session->clear();
            return false;
        }

        return true;
    }

    /**
     * Récupère les identifiants valides
     */
    public static function getValidCredentials(): array
    {
        return [
            'username' => self::VALID_USERNAME,
            'password' => self::VALID_PASSWORD
        ];
    }
}
