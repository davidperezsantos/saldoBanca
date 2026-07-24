<?php

namespace App\Controller\Api;

use App\Http\ApiResponse;
use App\Repository\UserRepository;
use App\Services\LoggableSystemActor;
use App\Services\PasswordResetService;
use App\Services\RoleSeedService;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

#[OA\Tag(name: 'Auth', description: 'Autenticación de usuarios')]
class AuthController extends AbstractController
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
        private JWTTokenManagerInterface $jwtManager,
        private UserRepository $userRepository,
        private PasswordResetService $passwordResetService,
        #[Autowire(service: 'limiter.api_login')]
        private RateLimiterFactory $apiLoginLimiter,
        #[Autowire(service: 'limiter.password_reset')]
        private RateLimiterFactory $passwordResetLimiter,
        private LoggableSystemActor $systemActor,
        private RoleSeedService $roleSeedService,
    ) {
    }

    #[OA\Post(
        path: '/api/v1/login',
        summary: 'Iniciar sesión',
        description: 'Autentica un usuario y devuelve un token JWT. Acepta username o email como identificador.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['password'],
            properties: [
                new OA\Property(property: 'username', description: 'Nombre de usuario', type: 'string', example: 'juanperez'),
                new OA\Property(property: 'email', description: 'Correo electrónico (alternativo si no se envía username)', type: 'string', example: 'juan@ejemplo.com'),
                new OA\Property(property: 'password', description: 'Contraseña del usuario', type: 'string', example: 'miPassword123'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'Login exitoso',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Login successful'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJSUzI1NiJ9...'),
                    new OA\Property(property: 'user', properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'a1b2c3d4-...'),
                        new OA\Property(property: 'username', type: 'string', example: 'juanperez'),
                        new OA\Property(property: 'email', type: 'string', example: 'juan@ejemplo.com'),
                        new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                        new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string', example: 'ROLE_USER')),
                    ], type: 'object'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Username/email and password are required')]
    #[OA\Response(response: 401, description: 'Invalid credentials')]
    #[OA\Response(response: 403, description: 'Account is disabled')]
    #[Route('/login', name: 'api_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $limiter = $this->apiLoginLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return ApiResponse::error('Demasiados intentos. Probá de nuevo en un minuto.', 429);
        }

        $data = json_decode($request->getContent(), true);
        $username = $data['username'] ?? $data['email'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($username) || empty($password)) {
            return ApiResponse::error('Username/email and password are required', 400);
        }

        $user = $this->userRepository->findByUsername($username);
        if (!$user) {
            $user = $this->userRepository->findByEmail($username);
        }

        if (!$user) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        if (!$user->isActive()) {
            return ApiResponse::error('Account is disabled', 403);
        }

        if (!$this->passwordHasher->isPasswordValid($user, $password)) {
            return ApiResponse::error('Invalid credentials', 401);
        }

        $user->setLastLoginAt(new \DateTimeImmutable());
        $this->systemActor->actAsSystem('login');
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
                'roles' => $user->getRoles(),
            ],
        ], 'Login successful');
    }

    #[OA\Post(
        path: '/api/v1/register',
        summary: 'Registrar nuevo usuario',
        description: 'Crea una nueva cuenta de usuario y devuelve un token JWT.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['email', 'password'],
            properties: [
                new OA\Property(property: 'username', description: 'Nombre de usuario (opcional)', type: 'string', example: 'juanperez'),
                new OA\Property(property: 'email', description: 'Correo electrónico', type: 'string', example: 'juan@ejemplo.com'),
                new OA\Property(property: 'password', description: 'Contraseña', type: 'string', example: 'miPassword123'),
                new OA\Property(property: 'name', description: 'Nombre completo (opcional)', type: 'string', example: 'Juan Pérez'),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'Usuario registrado exitosamente',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Registration successful'),
                new OA\Property(property: 'data', properties: [
                    new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJSUzI1NiJ9...'),
                    new OA\Property(property: 'user', properties: [
                        new OA\Property(property: 'id', type: 'string', example: 'a1b2c3d4-...'),
                        new OA\Property(property: 'username', type: 'string', example: 'juanperez'),
                        new OA\Property(property: 'email', type: 'string', example: 'juan@ejemplo.com'),
                        new OA\Property(property: 'name', type: 'string', example: 'Juan Pérez'),
                    ], type: 'object'),
                ], type: 'object'),
            ]
        )
    )]
    #[OA\Response(response: 400, description: 'Email and password are required')]
    #[OA\Response(response: 409, description: 'Email already exists o Username already exists')]
    #[Route('/register', name: 'api_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $name = $data['name'] ?? '';
        $username = $data['username'] ?? '';

        if (empty($email) || empty($password)) {
            return ApiResponse::error('Email and password are required', 400);
        }

        $existingUser = $this->userRepository->findByEmail($email);
        if ($existingUser) {
            return ApiResponse::error('Email already exists', 409);
        }

        if ($username) {
            $existingUsername = $this->userRepository->findByUsername($username);
            if ($existingUsername) {
                return ApiResponse::error('Username already exists', 409);
            }
        }

        $user = new User();
        $user->setEmail($email);
        $user->setUsername($username ?: null);
        $user->setName($name);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);
        $user->setIsActive(true);
        // Mismo rol "cliente" que /register/client — para que allowPanelLogin=false lo cubra
        // igual (ver RestrictPanelLoginListener), en vez de quedar sin Role y en un estado ambiguo.
        $user->setRole($this->roleSeedService->ensureRoleExists('cliente'));

        $this->entityManager->persist($user);
        $this->systemActor->actAsSystem('autorregistro');
        $this->entityManager->flush();

        $token = $this->jwtManager->create($user);

        return ApiResponse::success([
            'token' => $token,
            'user' => [
                'id' => $user->getId(),
                'username' => $user->getUsername(),
                'email' => $user->getEmail(),
                'name' => $user->getName(),
            ],
        ], 'Registration successful', 201);
    }

    #[OA\Post(
        path: '/api/v1/password-reset/request',
        summary: 'Solicitar restablecimiento de contraseña',
        description: 'Envía un enlace de restablecimiento por WhatsApp si el username/email existe. ' .
            'Responde siempre el mismo mensaje genérico, exista o no la cuenta, para no revelar su ' .
            'existencia.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['username'],
            properties: [
                new OA\Property(property: 'username', description: 'Username o email', type: 'string', example: 'juanperez'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Si la cuenta existe, se envió un enlace de restablecimiento')]
    #[OA\Response(response: 400, description: 'username es requerido')]
    #[Route('/password-reset/request', name: 'api_password_reset_request', methods: ['POST'])]
    public function requestPasswordReset(Request $request): JsonResponse
    {
        $limiter = $this->passwordResetLimiter->create($request->getClientIp());
        if (!$limiter->consume(1)->isAccepted()) {
            return ApiResponse::error('Demasiados intentos. Probá de nuevo más tarde.', 429);
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $username = $data['username'] ?? '';

        if (empty($username)) {
            return ApiResponse::error('username is required', 400);
        }

        try {
            $this->passwordResetService->requestReset($username);
        } catch (\Exception $e) {
            // Best-effort: si falla el envío (ej. WhatsApp caído) no revelamos detalles al llamador.
        }

        return ApiResponse::success(null, 'If the account exists, a reset link was sent');
    }

    #[OA\Post(
        path: '/api/v1/password-reset/confirm',
        summary: 'Confirmar restablecimiento de contraseña',
        description: 'Establece la nueva contraseña usando el token recibido por WhatsApp.',
        tags: ['Auth'],
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            required: ['token', 'password'],
            properties: [
                new OA\Property(property: 'token', description: 'Token de restablecimiento', type: 'string'),
                new OA\Property(property: 'password', description: 'Nueva contraseña', type: 'string', example: 'nuevaPassword123'),
            ]
        )
    )]
    #[OA\Response(response: 200, description: 'Contraseña actualizada')]
    #[OA\Response(response: 400, description: 'Token inválido/expirado o password vacío')]
    #[Route('/password-reset/confirm', name: 'api_password_reset_confirm', methods: ['POST'])]
    public function confirmPasswordReset(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true) ?? [];
        $token = $data['token'] ?? '';
        $password = $data['password'] ?? '';

        if (empty($token) || empty($password)) {
            return ApiResponse::error('token and password are required', 400);
        }

        try {
            $this->passwordResetService->confirmReset($token, $password);
        } catch (\Exception $e) {
            return ApiResponse::fromException($e);
        }

        return ApiResponse::success(null, 'Password updated successfully');
    }
}
