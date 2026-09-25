<?php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Dto\Request\ChangePasswordRequest;
use App\Dto\Request\ForgotPasswordRequest;
use App\Dto\Request\LoginRequest;
use App\Dto\Request\RefreshRequest;
use App\Dto\Request\RegisterRequest;
use App\Dto\Request\ResetPasswordRequest;
use App\Dto\Request\SwitchOrgRequest;
use App\Dto\Request\AcceptInvitationRequest;
use App\Dto\Request\RegisterInvitedRequest;
use App\Dto\Request\UpdateProfileRequest;
use App\Dto\Request\VerifyEmailRequest;
use App\Entity\EmailVerificationToken;
use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use App\Entity\OrganizationMember;
use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\OrganizationMemberRepository;
use App\Repository\OrganizationRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\UserRepository;
use App\Service\AppMailer;
use App\Service\MailBuilder;
use App\Service\RefreshTokenService;
use App\Service\Storage\AttachmentStorageInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpKernel\Attribute\MapRequestPayload;
use Symfony\Component\Mime\MimeTypes;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;
use Nelmio\ApiDocBundle\Attribute\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[OA\Tag(name: 'Auth')]
#[Route('/api/auth', name: 'api_auth_')]
final class AuthController extends AbstractController
{
    use ProblemDetailsResponseTrait;

    private const AVATAR_MAX_BYTES = 2 * 1024 * 1024; // 2 MB
    private const AVATAR_ALLOWED_MIME = ['image/jpeg', 'image/png', 'image/webp'];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly UserRepository $userRepo,
        private readonly OrganizationRepository $orgRepo,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly JWTTokenManagerInterface $jwtManager,
        private readonly RefreshTokenService $refreshTokens,
        private readonly PasswordResetTokenRepository $resetTokenRepo,
        private readonly EmailVerificationTokenRepository $verificationTokenRepo,
        private readonly OrganizationInvitationRepository $invitationRepo,
        private readonly AppMailer $mailer,
        private readonly MailBuilder $mailBuilder,
        private readonly SluggerInterface $slugger,
        private readonly ValidatorInterface $validator,
        private readonly RateLimiterFactory $loginLimiter,
        private readonly RateLimiterFactory $refreshLimiter,
        private readonly RateLimiterFactory $forgotPasswordLimiter,
        private readonly LoggerInterface $logger,
        private readonly AttachmentStorageInterface $storage,
    ) {
    }

    #[OA\Get(
        summary: 'Is open self-registration available?',
        description: 'Public. True only while the instance has no users (first-run bootstrap of the instance admin); afterwards new users can only join through an invitation.',
        security: [],
        responses: [new OA\Response(response: 200, description: '{open: bool}')],
    )]
    #[Route('/registration', name: 'registration_status', methods: ['GET'])]
    public function registrationStatus(): JsonResponse
    {
        return $this->json(['open' => !$this->userRepo->hasUsers()]);
    }

    #[OA\Post(
        summary: 'Register the first user + personal organization (first-run only)',
        description: 'Open ONLY while the instance has no users: the first account becomes the instance admin. From then on it answers 403 auth.registration_closed and new users join exclusively via invitation (POST /api/auth/invitation/register). Header X-Client-Type discriminates web (cookie) vs mobile (Bearer body).',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: RegisterRequest::class)),
        ),
        responses: [
            new OA\Response(response: 201, description: 'User created + JWT issued'),
            new OA\Response(response: 403, description: 'auth.registration_closed'),
            new OA\Response(response: 409, description: 'auth.email_taken (concurrent first-run race)'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(
        Request $request,
        #[MapRequestPayload] RegisterRequest $payload,
    ): JsonResponse {
        // Registrazione libera solo per il primo utente (bootstrap dell'admin di
        // istanza). Dopo, chiunque si registrasse potrebbe entrare nell'istanza:
        // si entra solo su invito (invitation/register), che ha un token.
        if ($this->userRepo->hasUsers()) {
            return $this->problem('auth.registration_closed', 403);
        }

        $user = (new User())
            ->setEmail($payload->email)
            ->setFirstName($payload->firstName)
            ->setLastName($payload->lastName)
            ->setLocale($payload->locale);
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));

        // Validazione completa (UniqueEntity etc.)
        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->validationErrorResponse($errors);
        }

        $this->em->persist($user);
        try {
            $this->em->flush(); // necessario per ottenere $user->getId() prima dello slug
        } catch (UniqueConstraintViolationException) {
            // Race del bootstrap: due prime registrazioni concorrenti con la stessa email.
            return $this->problem('auth.email_taken', 409);
        }

        // Crea Organization personale
        $orgName = $payload->organizationName
            ?? sprintf('%s %s', $payload->firstName, $payload->lastName);
        $org = (new Organization())
            ->setName($orgName)
            ->setSlug($this->buildUniqueSlug($orgName, $user->getId()));
        $this->em->persist($org);

        $membership = (new OrganizationMember())
            ->setUser($user)
            ->setOrganization($org)
            ->setRole(\App\Enum\OrgRole::OWNER)
            ->setAcceptedAt(new \DateTimeImmutable());
        $this->em->persist($membership);

        $this->em->flush();

        // Invio email di verifica (best-effort: un SMTP giù non deve far fallire il registro).
        $this->issueAndSendVerification($user);

        $clientType = $this->resolveClientType($request);

        return $this->buildAuthResponse($user, $clientType, statusCode: 201);
    }

    #[OA\Post(
        summary: 'Login with email + password',
        description: 'Rate limited (5/min per email+IP). Header X-Client-Type: web → refresh in HttpOnly cookie. mobile → refresh in body.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: LoginRequest::class)),
        ),
        responses: [
            new OA\Response(response: 200, description: 'JWT issued'),
            new OA\Response(response: 401, description: 'auth.invalid_credentials'),
            new OA\Response(response: 429, description: 'auth.too_many_attempts'),
        ],
    )]
    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(
        Request $request,
        #[MapRequestPayload] LoginRequest $payload,
    ): JsonResponse {
        $limiter = $this->loginLimiter->create($this->loginLimiterKey($request, $payload->email));
        if (!$limiter->consume()->isAccepted()) {
            return $this->problem('auth.too_many_attempts', 429);
        }

        $user = $this->userRepo->findOneByEmail($payload->email);
        if (!$user || !$this->passwordHasher->isPasswordValid($user, $payload->password)) {
            return $this->problem('auth.invalid_credentials', 401);
        }

        return $this->buildAuthResponse($user, $this->resolveClientType($request));
    }

    #[OA\Post(
        summary: 'Rotate access + refresh token',
        description: 'Web reads cookie. Mobile sends refresh in body. Old refresh is revoked. Rate limited (30/min per IP).',
        security: [],
        requestBody: new OA\RequestBody(
            required: false,
            content: new OA\JsonContent(ref: new Model(type: RefreshRequest::class)),
        ),
        responses: [
            new OA\Response(response: 200, description: 'New tokens'),
            new OA\Response(response: 400, description: 'auth.refresh_token_required'),
            new OA\Response(response: 401, description: 'auth.invalid_refresh_token'),
            new OA\Response(response: 429, description: 'auth.too_many_attempts'),
        ],
    )]
    #[Route('/refresh', name: 'refresh', methods: ['POST'])]
    public function refresh(
        Request $request,
        #[MapRequestPayload(acceptFormat: 'json')] ?RefreshRequest $payload = null,
    ): JsonResponse {
        $limiter = $this->refreshLimiter->create('refresh-'.$request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->problem('auth.too_many_attempts', 429);
        }

        $cookieToken = $request->cookies->get(RefreshTokenService::COOKIE_NAME);
        $bodyToken = $payload?->refreshToken;

        // Il canale di ingresso determina il canale di uscita
        $clientType = $cookieToken !== null ? 'web' : 'mobile';
        $rawToken = $cookieToken ?? $bodyToken;

        if (!$rawToken) {
            return $this->problem('auth.refresh_token_required', 400);
        }

        $refresh = $this->refreshTokens->findValid($rawToken);
        if (!$refresh) {
            $response = $this->problem('auth.invalid_refresh_token', 401);
            // Se era un cookie invalido, lo cancelliamo
            if ($cookieToken !== null) {
                $response->headers->clearCookie(RefreshTokenService::COOKIE_NAME, '/api/auth');
            }
            return $response;
        }

        $rotated = $this->refreshTokens->rotate($refresh);
        return $this->buildAuthResponse($refresh->getUser(), $clientType, existingRefresh: $rotated);
    }

    #[OA\Post(
        summary: 'Logout — revoke refresh + clear cookie',
        description: 'Accepts refresh token from cookie or body {refresh_token}. Returns 204 even if token missing.',
        security: [],
        responses: [new OA\Response(response: 204, description: 'Logged out')],
    )]
    #[Route('/logout', name: 'logout', methods: ['POST'])]
    public function logout(Request $request): JsonResponse
    {
        $rawToken = $request->cookies->get(RefreshTokenService::COOKIE_NAME)
            ?? (json_decode($request->getContent() ?: '{}', true)['refresh_token'] ?? null);

        if ($rawToken && ($refresh = $this->refreshTokens->findValid($rawToken))) {
            $this->refreshTokens->revoke($refresh);
        }

        $response = new JsonResponse(null, 204);
        $response->headers->clearCookie(RefreshTokenService::COOKIE_NAME, '/api/auth');
        return $response;
    }

    /**
     * Cambia l'organization attiva: emette un nuovo JWT con `active_org_id` aggiornato.
     * Il client deve sostituire il vecchio access token nello state e
     * mantenere lo stesso refresh token (la sessione non cambia).
     */
    #[OA\Post(
        summary: 'Switch active organization',
        description: 'Issues new JWT with updated active_org_id claim. Refresh token unchanged.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: SwitchOrgRequest::class)),
        ),
        responses: [
            new OA\Response(response: 200, description: 'New access_token + active_org_id'),
            new OA\Response(response: 403, description: 'auth.not_member'),
            new OA\Response(response: 404, description: 'org.not_found'),
        ],
    )]
    #[Route('/switch-org', name: 'switch_org', methods: ['POST'])]
    public function switchOrg(#[MapRequestPayload] SwitchOrgRequest $payload): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        $org = $this->orgRepo->find($payload->organizationId);
        if (!$org) {
            return $this->problem('org.not_found', 404);
        }

        $membership = $this->memberRepo->findMembership($user, $org);
        if (!$membership || !$membership->isAccepted()) {
            return $this->problem('auth.not_member', 403);
        }

        $accessToken = $this->jwtManager->createFromPayload($user, [
            'user_id' => $user->getId(),
            'active_org_id' => $org->getId(),
        ]);

        return $this->json([
            'access_token' => $accessToken,
            'active_org_id' => $org->getId(),
        ]);
    }

    #[OA\Get(
        summary: 'Current user profile + organization memberships',
        responses: [
            new OA\Response(response: 200, description: 'User + memberships array'),
            new OA\Response(response: 401, description: 'auth.not_authenticated'),
        ],
    )]
    #[Route('/me', name: 'me', methods: ['GET'])]
    public function me(): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->problem('auth.not_authenticated', 401);
        }

        return $this->json([
            ...$this->userPayload($user),
            'memberships' => array_map(static fn (OrganizationMember $m) => [
                'id' => $m->getId(),
                'role' => $m->getRole()->value,
                'organization' => [
                    'id' => $m->getOrganization()->getId(),
                    'name' => $m->getOrganization()->getName(),
                    'slug' => $m->getOrganization()->getSlug(),
                ],
            ], $this->memberRepo->findAllForUser($user)),
        ]);
    }

    #[OA\Put(
        summary: 'Update current user profile (name, email, locale)',
        description: 'Email must stay unique. Re-issues a fresh JWT because the identifier (email) may change.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: UpdateProfileRequest::class)),
        ),
        responses: [
            new OA\Response(response: 200, description: 'Updated user + fresh access_token'),
            new OA\Response(response: 409, description: 'auth.email_taken'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/profile', name: 'update_profile', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function updateProfile(#[MapRequestPayload] UpdateProfileRequest $payload): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $existing = $this->userRepo->findOneByEmail($payload->email);
        if ($existing && $existing->getId() !== $user->getId()) {
            return $this->problem('auth.email_taken', 409);
        }

        $user
            ->setFirstName($payload->firstName)
            ->setLastName($payload->lastName)
            ->setEmail($payload->email)
            ->setLocale($payload->locale);

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->validationErrorResponse($errors);
        }

        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            // Race: un'altra richiesta ha preso l'email tra il check e il flush.
            return $this->problem('auth.email_taken', 409);
        }

        // L'email è l'identifier JWT: se cambia, il token in memoria diventa stale.
        // Riemettiamo un access_token fresco così il client resta autenticato.
        return $this->json([
            'access_token' => $this->jwtManager->create($user),
            'user' => $this->userPayload($user),
        ]);
    }

    #[OA\Put(
        summary: 'Change current user password',
        description: 'Verifies currentPassword, then sets newPassword. Revokes all refresh tokens (logs out other devices) and issues a fresh session for the current client.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ChangePasswordRequest::class)),
        ),
        responses: [
            new OA\Response(response: 200, description: 'New access_token + rotated refresh'),
            new OA\Response(response: 400, description: 'auth.invalid_current_password'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/password', name: 'change_password', methods: ['PUT'])]
    #[IsGranted('ROLE_USER')]
    public function changePassword(
        Request $request,
        #[MapRequestPayload] ChangePasswordRequest $payload,
    ): JsonResponse {
        /** @var User $user */
        $user = $this->getUser();

        if (!$this->passwordHasher->isPasswordValid($user, $payload->currentPassword)) {
            return $this->problem('auth.invalid_current_password', 400);
        }

        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->newPassword));
        $this->em->flush();

        // Sicurezza: invalida tutte le sessioni esistenti, poi riapri quella corrente.
        $this->refreshTokens->revokeAllForUser($user);

        return $this->buildAuthResponse($user, $this->resolveClientType($request));
    }

    #[OA\Post(
        summary: 'Upload current user avatar (multipart/form-data, field `file`)',
        description: 'Max 2MB. Whitelist: image/jpeg, image/png, image/webp (MIME verified server-side). Replaces the previous avatar.',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\MediaType(
                mediaType: 'multipart/form-data',
                schema: new OA\Schema(
                    required: ['file'],
                    properties: [new OA\Property(property: 'file', type: 'string', format: 'binary')],
                ),
            ),
        ),
        responses: [
            new OA\Response(response: 200, description: '{hasAvatar: true}'),
            new OA\Response(response: 400, description: 'upload.file_required / upload.invalid_file'),
            new OA\Response(response: 413, description: 'upload.file_too_large'),
            new OA\Response(response: 415, description: 'upload.mime_not_allowed'),
        ],
    )]
    #[Route('/avatar', name: 'avatar_upload', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function uploadAvatar(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $file = $request->files->get('file');
        if (!$file) {
            return $this->problem('upload.file_required', 400);
        }
        if (!$file->isValid()) {
            return $this->problem('upload.invalid_file', 400);
        }
        if ($file->getSize() > self::AVATAR_MAX_BYTES) {
            return $this->problem('upload.file_too_large', 413);
        }
        // MIME verificato server-side: mai fidarsi del client
        $mime = $file->getMimeType() ?: '';
        if (!in_array($mime, self::AVATAR_ALLOWED_MIME, true)) {
            return $this->problem('upload.mime_not_allowed', 415);
        }

        $previousPath = $user->getAvatarPath();
        $storedPath = $this->storage->store($file, 'avatar');

        try {
            $user->setAvatarPath($storedPath);
            $this->em->flush();
        } catch (\Throwable $e) {
            $this->storage->delete($storedPath);
            throw $e;
        }

        if ($previousPath !== null) {
            $this->storage->delete($previousPath);
        }

        return $this->json(['hasAvatar' => true]);
    }

    #[OA\Get(
        summary: 'Download current user avatar',
        description: 'Returns avatar bytes, streamed directly by the app.',
        responses: [
            new OA\Response(response: 200, description: 'Binary image'),
            new OA\Response(response: 404, description: 'auth.no_avatar'),
        ],
    )]
    #[Route('/avatar', name: 'avatar_download', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function downloadAvatar(): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        $path = $user->getAvatarPath();
        if ($path === null || !$this->storage->exists($path)) {
            return $this->problem('auth.no_avatar', 404);
        }

        $absolutePath = $this->storage->absolutePath($path);
        // Content-Type rilevato dal contenuto: i file avatar sono salvati senza estensione.
        $mime = MimeTypes::getDefault()->guessMimeType($absolutePath) ?? 'application/octet-stream';

        $response = new BinaryFileResponse($absolutePath);
        $response->headers->set('Content-Type', $mime);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'avatar');
        return $response;
    }

    #[OA\Delete(
        summary: 'Remove current user avatar',
        responses: [new OA\Response(response: 204, description: 'Removed (idempotent)')],
    )]
    #[Route('/avatar', name: 'avatar_delete', methods: ['DELETE'])]
    #[IsGranted('ROLE_USER')]
    public function deleteAvatar(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();

        $path = $user->getAvatarPath();
        if ($path !== null) {
            $user->setAvatarPath(null);
            $this->em->flush();
            $this->storage->delete($path);
        }

        return new JsonResponse(null, 204);
    }

    #[OA\Post(
        summary: 'Request a password reset email',
        description: 'Always returns 200 (no user enumeration). If the email exists, sends a reset link valid 1h.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ForgotPasswordRequest::class)),
        ),
        responses: [new OA\Response(response: 200, description: 'Email sent if account exists')],
    )]
    #[Route('/forgot-password', name: 'forgot_password', methods: ['POST'])]
    public function forgotPassword(
        Request $request,
        #[MapRequestPayload] ForgotPasswordRequest $payload,
    ): JsonResponse {
        $limiter = $this->forgotPasswordLimiter->create('forgot-'.strtolower($payload->email).'-'.$request->getClientIp());
        if (!$limiter->consume()->isAccepted()) {
            return $this->problem('auth.too_many_attempts', 429);
        }

        $user = $this->userRepo->findOneByEmail($payload->email);
        if ($user) {
            // Un solo token attivo per utente: invalida i precedenti.
            $this->resetTokenRepo->invalidateAllForUser($user);

            $rawToken = bin2hex(random_bytes(32));
            $resetToken = new PasswordResetToken(
                $user,
                hash('sha256', $rawToken),
                (new \DateTimeImmutable())->modify('+1 hour'),
            );
            $this->em->persist($resetToken);
            $this->em->flush();

            // Il fallimento SMTP non deve diventare un 500: il client riceve comunque
            // 200 (no enumeration) e può ritentare; il token verrà invalidato dal prossimo forgot.
            try {
                $this->mailer->send($this->mailBuilder->resetPassword($user, $rawToken)->to($user->getEmail()));
            } catch (\Throwable $e) {
                $this->logger->error('Password reset email failed', [
                    'user_id' => $user->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->json(['status' => 'ok']);
    }

    #[OA\Post(
        summary: 'Reset password with a token from the email',
        description: 'Consumes a valid (unexpired, unused) token, sets the new password, invalidates all sessions.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: ResetPasswordRequest::class)),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Password reset'),
            new OA\Response(response: 400, description: 'auth.invalid_reset_token'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/reset-password', name: 'reset_password', methods: ['POST'])]
    public function resetPassword(#[MapRequestPayload] ResetPasswordRequest $payload): JsonResponse
    {
        $resetToken = $this->resetTokenRepo->findValidByHash(hash('sha256', $payload->token));
        if (!$resetToken) {
            return $this->problem('auth.invalid_reset_token', 400);
        }

        $user = $resetToken->getUser();
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));
        $resetToken->markUsed();
        $this->em->flush();

        // Tutte le sessioni esistenti vanno invalidate dopo un reset.
        $this->refreshTokens->revokeAllForUser($user);

        return new JsonResponse(null, 204);
    }

    #[OA\Post(
        summary: 'Confirm email address with a token from the verification email',
        description: 'Consumes a valid (unexpired, unused) token and marks the user email as verified.',
        security: [],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: new Model(type: VerifyEmailRequest::class)),
        ),
        responses: [
            new OA\Response(response: 204, description: 'Email verified'),
            new OA\Response(response: 400, description: 'auth.invalid_verification_token'),
        ],
    )]
    #[Route('/verify-email', name: 'verify_email', methods: ['POST'])]
    public function verifyEmail(#[MapRequestPayload] VerifyEmailRequest $payload): JsonResponse
    {
        $token = $this->verificationTokenRepo->findValidByHash(hash('sha256', $payload->token));
        if (!$token) {
            return $this->problem('auth.invalid_verification_token', 400);
        }

        $token->getUser()->markEmailVerified();
        $token->markUsed();
        $this->em->flush();

        return new JsonResponse(null, 204);
    }

    #[OA\Post(
        summary: 'Resend the email verification link to the current user',
        description: 'No-op (still 204) if the email is already verified.',
        responses: [new OA\Response(response: 204, description: 'Verification email sent if needed')],
    )]
    #[Route('/resend-verification', name: 'resend_verification', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function resendVerification(): JsonResponse
    {
        /** @var User $user */
        $user = $this->getUser();
        if (!$user->isEmailVerified()) {
            $this->issueAndSendVerification($user);
        }

        return new JsonResponse(null, 204);
    }

    #[OA\Get(
        summary: 'Preview an organization invitation by token (for the accept page)',
        description: 'Public. Returns org name, target email, role and whether an account already exists for that email.',
        security: [],
        parameters: [new OA\Parameter(name: 'token', in: 'path', required: true, schema: new OA\Schema(type: 'string'))],
        responses: [
            new OA\Response(response: 200, description: '{organizationName, email, role, accountExists}'),
            new OA\Response(response: 400, description: 'invitation.invalid'),
        ],
    )]
    #[Route('/invitation/{token}', name: 'invitation_preview', methods: ['GET'], requirements: ['token' => '[a-f0-9]{64}'])]
    public function previewInvitation(string $token): JsonResponse
    {
        $invitation = $this->invitationRepo->findValidByHash(hash('sha256', $token));
        if (!$invitation) {
            return $this->problem('invitation.invalid', 400);
        }

        return $this->json([
            'organizationName' => $invitation->getOrganization()->getName(),
            'email' => $invitation->getEmail(),
            'role' => $invitation->getRole()->value,
            'accountExists' => $this->userRepo->findOneByEmail($invitation->getEmail()) !== null,
        ]);
    }

    #[OA\Post(
        summary: 'Accept an organization invitation (existing, logged-in user)',
        description: 'Authenticated. The current user email must match the invitation email. Creates the accepted membership.',
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: AcceptInvitationRequest::class))),
        responses: [
            new OA\Response(response: 200, description: '{status, organizationId}'),
            new OA\Response(response: 400, description: 'invitation.invalid'),
            new OA\Response(response: 403, description: 'invitation.not_for_you'),
        ],
    )]
    #[Route('/invitation/accept', name: 'invitation_accept', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function acceptInvitation(#[MapRequestPayload] AcceptInvitationRequest $payload): JsonResponse
    {
        $invitation = $this->invitationRepo->findValidByHash(hash('sha256', $payload->token));
        if (!$invitation) {
            return $this->problem('invitation.invalid', 400);
        }

        /** @var User $user */
        $user = $this->getUser();
        if (mb_strtolower($user->getEmail()) !== mb_strtolower($invitation->getEmail())) {
            return $this->problem('invitation.not_for_you', 403);
        }

        $this->acceptInvitationFor($user, $invitation);
        $this->em->flush();

        return $this->json(['status' => 'ok', 'organizationId' => $invitation->getOrganization()->getId()]);
    }

    #[OA\Post(
        summary: 'Register a new account from an invitation and accept it',
        description: 'Public. Creates the user (email taken from the invitation, marked verified), a personal organization, and the accepted membership in the inviting org. Issues JWT.',
        security: [],
        requestBody: new OA\RequestBody(required: true, content: new OA\JsonContent(ref: new Model(type: RegisterInvitedRequest::class))),
        responses: [
            new OA\Response(response: 201, description: 'User created + joined + JWT issued'),
            new OA\Response(response: 400, description: 'invitation.invalid'),
            new OA\Response(response: 409, description: 'auth.email_taken (account exists → log in instead)'),
            new OA\Response(response: 422, description: 'validation_failed'),
        ],
    )]
    #[Route('/invitation/register', name: 'invitation_register', methods: ['POST'])]
    public function registerInvited(
        Request $request,
        #[MapRequestPayload] RegisterInvitedRequest $payload,
    ): JsonResponse {
        $invitation = $this->invitationRepo->findValidByHash(hash('sha256', $payload->token));
        if (!$invitation) {
            return $this->problem('invitation.invalid', 400);
        }

        $email = $invitation->getEmail();
        if ($this->userRepo->findOneByEmail($email) !== null) {
            // Esiste già: deve accedere e usare l'accept autenticato.
            return $this->problem('auth.email_taken', 409);
        }

        $user = (new User())
            ->setEmail($email)
            ->setFirstName($payload->firstName)
            ->setLastName($payload->lastName)
            ->setLocale($payload->locale);
        $user->setPassword($this->passwordHasher->hashPassword($user, $payload->password));
        // L'invito è arrivato a quell'indirizzo: il possesso dell'email è provato.
        $user->markEmailVerified();

        $errors = $this->validator->validate($user);
        if (count($errors) > 0) {
            return $this->validationErrorResponse($errors);
        }

        $this->em->persist($user);
        try {
            $this->em->flush();
        } catch (UniqueConstraintViolationException) {
            return $this->problem('auth.email_taken', 409);
        }

        // Niente org personale: l'invitato ENTRA nell'organizzazione che lo ha
        // invitato (modello family-sharing self-host) col ruolo dell'invito, non
        // ne possiede una propria. Resta quindi un'unica membership → l'org attiva
        // (JWT active_org_id e display frontend) è sempre quella dell'invito.
        $this->acceptInvitationFor($user, $invitation);
        $this->em->flush();

        return $this->buildAuthResponse($user, $this->resolveClientType($request), statusCode: 201);
    }

    // ----- helpers -----

    /**
     * Crea (o conferma) la membership accettata di $user nell'org dell'invito e
     * marca l'invito come usato. Idempotente se già membro.
     */
    private function acceptInvitationFor(User $user, OrganizationInvitation $invitation): void
    {
        $org = $invitation->getOrganization();
        $existing = $this->memberRepo->findMembership($user, $org);
        if ($existing === null) {
            $this->em->persist(
                (new OrganizationMember())
                    ->setOrganization($org)
                    ->setUser($user)
                    ->setRole($invitation->getRole())
                    ->setInvitedBy($invitation->getInvitedBy())
                    ->setAcceptedAt(new \DateTimeImmutable()),
            );
        } elseif (!$existing->isAccepted()) {
            $existing->setAcceptedAt(new \DateTimeImmutable());
        }
        $invitation->markUsed();
    }

    /**
     * Genera un token di verifica (un solo attivo per utente) e invia l'email.
     * Best-effort: un errore SMTP viene loggato ma non propagato.
     */
    private function issueAndSendVerification(User $user): void
    {
        $this->verificationTokenRepo->invalidateAllForUser($user);

        $rawToken = bin2hex(random_bytes(32));
        $token = new EmailVerificationToken(
            $user,
            hash('sha256', $rawToken),
            (new \DateTimeImmutable())->modify('+24 hours'),
        );
        $this->em->persist($token);
        $this->em->flush();

        try {
            $this->mailer->send($this->mailBuilder->verifyEmail($user, $rawToken)->to($user->getEmail()));
        } catch (\Throwable $e) {
            $this->logger->error('Email verification send failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @return array{id: int|null, email: string, firstName: string, lastName: string, locale: string, hasAvatar: bool, emailVerified: bool}
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'firstName' => $user->getFirstName(),
            'lastName' => $user->getLastName(),
            'locale' => $user->getLocale(),
            'hasAvatar' => $user->hasAvatar(),
            'emailVerified' => $user->isEmailVerified(),
            // Amministratore di istanza (primo utente registrato): unico autorizzato
            // a toccare la config VAPID di istanza (SMTP è solo env, niente UI). Il
            // frontend lo usa per mostrare/nascondere quella sezione invece di
            // basarsi (erroneamente) sul ruolo OWNER, che chiunque si registri
            // ottiene sulla propria org personale.
            'isInstanceAdmin' => $this->userRepo->isInstanceAdmin($user),
        ];
    }

    private function buildAuthResponse(
        User $user,
        string $clientType,
        ?RefreshToken $existingRefresh = null,
        int $statusCode = 200,
    ): JsonResponse {
        $accessToken = $this->jwtManager->create($user);
        $refresh = $existingRefresh ?? $this->refreshTokens->issue($user);

        $body = [
            'access_token' => $accessToken,
            'user' => $this->userPayload($user),
        ];

        if ($clientType === 'mobile') {
            $body['refresh_token'] = $refresh->getToken();
            return new JsonResponse($body, $statusCode);
        }

        // Web: refresh nel cookie HttpOnly
        $response = new JsonResponse($body, $statusCode);
        $response->headers->setCookie(Cookie::create(
            name: RefreshTokenService::COOKIE_NAME,
            value: $refresh->getToken(),
            expire: $refresh->getExpiresAt(),
            path: '/api/auth',
            secure: $this->getParameter('kernel.environment') !== 'dev',
            httpOnly: true,
            sameSite: Cookie::SAMESITE_LAX,
        ));
        return $response;
    }

    private function resolveClientType(Request $request): string
    {
        $header = strtolower((string) $request->headers->get('X-Client-Type', 'web'));
        return in_array($header, ['web', 'mobile'], true) ? $header : 'web';
    }

    private function loginLimiterKey(Request $request, string $email): string
    {
        return 'login-'.strtolower($email).'-'.$request->getClientIp();
    }

    private function buildUniqueSlug(string $name, int $userId): string
    {
        $base = strtolower((string) $this->slugger->slug($name));
        if ($base === '') {
            $base = 'workspace';
        }
        $candidate = $base;
        $suffix = 1;
        while ($this->orgRepo->findOneBySlug($candidate) !== null) {
            $candidate = $base.'-'.$userId.'-'.$suffix;
            $suffix++;
        }
        return $candidate;
    }

    private function validationErrorResponse(\Symfony\Component\Validator\ConstraintViolationListInterface $errors): JsonResponse
    {
        $details = [];
        foreach ($errors as $error) {
            $details[] = [
                'field' => $error->getPropertyPath(),
                'message' => $error->getMessage(),
            ];
        }
        return $this->json(
            ['type' => 'about:blank', 'title' => 'validation_failed', 'status' => 422, 'errors' => $details],
            422,
        );
    }
}
