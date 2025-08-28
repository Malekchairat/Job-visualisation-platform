<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use App\Service\AuthenticationService;

class SecurityController extends AbstractController
{
    private AuthenticationService $authService;

    public function __construct(AuthenticationService $authService)
    {
        $this->authService = $authService;
    }

    #[Route(path: '/', name: 'app_home')]
    public function home(): Response
    {
        // Si l'utilisateur est connecté, rediriger vers la visualisation
        if ($this->authService->isAuthenticated()) {
            return $this->redirectToRoute('app_job_status_visualization');
        }
        
        // Sinon, rediriger vers la page de connexion
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/login', name: 'app_login')]
    public function login(Request $request, SessionInterface $session, AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, rediriger vers le tableau de bord
        if ($this->authService->isAuthenticated()) {
            return $this->redirectToRoute('app_job_status_visualization');
        }

        // Traitement du formulaire de connexion
        if ($request->isMethod('POST')) {
            $username = $request->request->get('_username');
            $password = $request->request->get('_password');
            $rememberMe = $request->request->get('_remember_me') !== null;

            // Vérification des identifiants via le service
            if ($this->authService->authenticate($username, $password, $rememberMe)) {
                // Connexion réussie
                $this->addFlash('success', 'Connexion réussie ! Bienvenue ' . $username);

                // Redirection vers la page demandée ou le tableau de bord
                $targetPath = $session->get('_security.main.target_path');
                if ($targetPath) {
                    $session->remove('_security.main.target_path');
                    return $this->redirect($targetPath);
                }

                return $this->redirectToRoute('app_job_status_visualization');
            } else {
                // Connexion échouée
                $this->addFlash('error', 'Nom d\'utilisateur ou mot de passe incorrect.');
                
                // Log de tentative de connexion échouée
                $this->logFailedLogin($username, $request->getClientIp());
            }
        }

        // Récupération des erreurs d'authentification
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    #[Route(path: '/logout', name: 'app_logout')]
    public function logout(): Response
    {
        // Déconnexion via le service
        $username = $this->authService->logout();
        
        $this->addFlash('info', 'Vous avez été déconnecté avec succès. À bientôt ' . $username . ' !');
        
        return $this->redirectToRoute('app_login');
    }

    #[Route(path: '/check-auth', name: 'app_check_auth', methods: ['GET'])]
    public function checkAuth(): Response
    {
        $userInfo = $this->authService->getUserInfo();
        
        return $this->json([
            'authenticated' => $userInfo !== null,
            'username' => $userInfo ? $userInfo['username'] : null,
            'login_time' => $userInfo ? $userInfo['login_time']->format('Y-m-d H:i:s') : null,
            'session_id' => $userInfo ? $userInfo['session_id'] : null
        ]);
    }

    #[Route(path: '/extend-session', name: 'app_extend_session', methods: ['POST'])]
    public function extendSession(): Response
    {
        if ($this->authService->isAuthenticated()) {
            $this->authService->extendSession();
            return $this->json(['success' => true, 'message' => 'Session étendue']);
        }
        
        return $this->json(['success' => false, 'message' => 'Non authentifié'], 401);
    }

    /**
     * Log des tentatives de connexion échouées
     */
    private function logFailedLogin(string $username, string $ip): void
    {
        // Ici vous pourriez logger dans un fichier ou une base de données
        error_log(sprintf(
            '[%s] Failed login attempt - Username: %s, IP: %s',
            date('Y-m-d H:i:s'),
            $username,
            $ip
        ));
    }
}

