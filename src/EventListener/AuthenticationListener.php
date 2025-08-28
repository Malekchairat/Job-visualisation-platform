<?php

namespace App\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class AuthenticationListener implements EventSubscriberInterface
{
    private RouterInterface $router;

    // Routes qui ne nécessitent pas d'authentification
    private const PUBLIC_ROUTES = [
        'app_login',
        'app_logout',
        '_profiler',
        '_wdt'
    ];

    // Patterns d'URL publiques
    private const PUBLIC_PATTERNS = [
        '/login',
        '/logout',
        '/_profiler',
        '/_wdt',
        '/css/',
        '/js/',
        '/images/',
        '/favicon.ico'
    ];

    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 10],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        // Ne traiter que les requêtes principales
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $session = $request->getSession();

        // Vérifier si la route est publique
        if ($this->isPublicRoute($request)) {
            return;
        }

        // Vérifier l'authentification
        if (!$this->isUserAuthenticated($session)) {
            // Sauvegarder l'URL demandée pour redirection après connexion
            if ($request->getMethod() === 'GET' && !$request->isXmlHttpRequest()) {
                $session->set('_security.main.target_path', $request->getUri());
            }

            // Rediriger vers la page de connexion
            $loginUrl = $this->router->generate('app_login');
            $response = new RedirectResponse($loginUrl);
            $event->setResponse($response);
        }
    }

    /**
     * Vérifie si la route actuelle est publique
     */
    private function isPublicRoute($request): bool
    {
        $routeName = $request->attributes->get('_route');
        $pathInfo = $request->getPathInfo();

        // Vérifier les noms de routes publiques
        if (in_array($routeName, self::PUBLIC_ROUTES)) {
            return true;
        }

        // Vérifier les patterns d'URL publiques
        foreach (self::PUBLIC_PATTERNS as $pattern) {
            if (str_starts_with($pathInfo, $pattern)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Vérifie si l'utilisateur est authentifié
     */
    private function isUserAuthenticated(SessionInterface $session): bool
    {
        $isAuthenticated = $session->get('user_authenticated', false);
        
        // Vérification supplémentaire de la validité de la session
        if ($isAuthenticated) {
            $loginTime = $session->get('login_time');
            $rememberMe = $session->get('remember_me', false);
            
            if ($loginTime instanceof \DateTime) {
                $now = new \DateTime();
                $sessionDuration = $rememberMe ? 86400 * 30 : 86400; // 30 jours ou 1 jour
                
                // Vérifier si la session n'a pas expiré
                if (($now->getTimestamp() - $loginTime->getTimestamp()) > $sessionDuration) {
                    // Session expirée, nettoyer
                    $session->clear();
                    return false;
                }
            }
        }

        return $isAuthenticated;
    }
}

