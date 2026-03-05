<?php
namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use GuzzleHttp\Client as GuzzleClient;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/api/auth', name: 'api_auth_')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly UserPasswordHasherInterface  $hasher,
        private readonly JWTTokenManagerInterface     $jwtManager,
        private readonly ValidatorInterface           $validator,
    ) {}

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data  = json_decode($request->getContent(), true) ?? [];
        $email = trim($data['email'] ?? '');
        $pass  = $data['password'] ?? '';

        $errors = [];
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Email invalide.';
        }
        if (strlen($pass) < 8) {
            $errors[] = 'Mot de passe trop court (8 caractères minimum).';
        }
        if ($errors) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $existing = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existing) {
            return $this->json(['error' => 'Email déjà utilisé.'], Response::HTTP_CONFLICT);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setPassword($this->hasher->hashPassword($user, $pass));

        $this->em->persist($user);
        $this->em->flush();

        $token = $this->jwtManager->create($user);

        return $this->json([
            'token' => $token,
            'user'  => ['id' => $user->getId(), 'email' => $user->getEmail()],
        ], Response::HTTP_CREATED);
    }

    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Non authentifié'], Response::HTTP_UNAUTHORIZED);
        }
        return $this->json([
            'id'    => $user->getId(),
            'email' => $user->getEmail(),
            'roles' => $user->getRoles(),
        ]);
    }

    #[Route('/github', name: 'github', methods: ['GET'])]
    public function githubLogin(ClientRegistry $clientRegistry): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_main')
            ->redirect(['read:user', 'user:email'], []);
    }

    #[Route('/github/callback', name: 'github_callback', methods: ['GET'])]
    public function githubCallback(ClientRegistry $clientRegistry, Request $request): Response
    {
        try {
            // Désactive SSL pour dev Windows
            $httpClient = new GuzzleClient(['verify' => false]);

            $client = $clientRegistry->getClient('github_main');
            $provider = $client->getOAuth2Provider();
            $provider->setHttpClient($httpClient);

            $accessToken = $provider->getAccessToken('authorization_code', [
                'code' => $request->query->get('code'),
            ]);

            $githubUser = $provider->getResourceOwner($accessToken);
    // GET /api/auth/github/callback
    // Cree/associe un utilisateur, emet un JWT puis redirige vers le frontend avec #token=...
    #[Route('/github/callback', name: 'github_callback', methods: ['GET'])]
    public function githubCallback(ClientRegistry $clientRegistry): Response
    {
        try {
            $client = $clientRegistry->getClient('github_main');
            $accessToken = $client->getAccessToken();
            $githubUser = $client->fetchUserFromToken($accessToken);

            $githubId = (string) $githubUser->getId();
            $email = $githubUser->getEmail() ?: sprintf('github_%s@users.noreply.local', $githubId);

            /** @var User|null $user */
            $user = $this->em->getRepository(User::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $user = new User();
                $user->setEmail($email);
                // Mot de passe aleatoire pour les comptes OAuth uniquement.
                $user->setPassword($this->hasher->hashPassword($user, bin2hex(random_bytes(32))));
                $this->em->persist($user);
                $this->em->flush();
            }

            $jwt = $this->jwtManager->create($user);
            $payload = [
                'token' => $jwt,
                'user' => [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                ],
            ];

            $frontendSuccessUrl = $_ENV['FRONTEND_GITHUB_SUCCESS_URL']
                ?? $_SERVER['FRONTEND_GITHUB_SUCCESS_URL']
                ?? getenv('FRONTEND_GITHUB_SUCCESS_URL')
                ?? '';

            if (is_string($frontendSuccessUrl) && $frontendSuccessUrl !== '') {
            if (is_string($frontendSuccessUrl) && $frontendSuccessUrl !== '') {
                // Utilise le fragment (#token=...) pour eviter l'exposition du JWT dans les logs/referers.
                $tokenFragment = 'token=' . urlencode($jwt);
                $redirectUrl = str_contains($frontendSuccessUrl, '#')
                    ? ($frontendSuccessUrl . '&' . $tokenFragment)
                    : ($frontendSuccessUrl . '#' . $tokenFragment);

                return $this->redirect($redirectUrl, Response::HTTP_FOUND);
            }

            return $this->json([
                'token' => $jwt,
                'user' => ['id' => $user->getId(), 'email' => $user->getEmail()],
            ]);

            return $this->json($payload);
        } catch (\Throwable $e) {
            return $this->json([
                'error' => 'Echec de l authentification GitHub',
                'details' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        }
    }
}
}
