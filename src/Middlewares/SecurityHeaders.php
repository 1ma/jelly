<?php

declare(strict_types=1);

namespace Jelly\Middlewares;

use Psr\Http\Message;
use Psr\Http\Server;
use function sprintf;

/**
 * Middleware that set the outgoing headers based on the following recommendations:
 *
 * @see https://paragonie.com/blog/2017/12/2018-guide-building-secure-php-software
 * @see https://security.stackexchange.com/a/147559/70983
 * @see https://www.owasp.org/index.php/REST_Security_Cheat_Sheet
 * @see https://paramdeo.com/blog/opting-your-website-out-of-googles-floc-network
 */
final class SecurityHeaders implements Server\MiddlewareInterface
{
    private readonly int $maxAge;

    public function __construct(int $maxAge = 30)
    {
        $this->maxAge = $maxAge;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        return $handler->handle($request)
            ->withHeader('Expect-CT', sprintf('enforce,max-age=%s', $this->maxAge))
            ->withHeader('Permissions-Policy', 'interest-cohort=()')
            ->withHeader('Strict-Transport-Security', sprintf('max-age=%s', $this->maxAge))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block');
    }
}
