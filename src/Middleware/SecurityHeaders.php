<?php

declare(strict_types=1);

namespace ABC\Middleware;

use Psr\Http\Message;
use Psr\Http\Server;

/**
 * Middleware that set the outgoing headers based on the following recommendations:
 *
 * @see https://paragonie.com/blog/2017/12/2018-guide-building-secure-php-software
 * @see https://security.stackexchange.com/a/147559/70983
 * @see https://www.owasp.org/index.php/REST_Security_Cheat_Sheet
 */
final class SecurityHeaders implements Server\MiddlewareInterface
{
    /**
     * @var int
     */
    private $maxAge;

    /**
     * @var string|null
     */
    private $serverName;

    public function __construct(int $maxAge = 30, string $serverName = null)
    {
        $this->maxAge = $maxAge;
        $this->serverName = $serverName;
    }

    /**
     * {@inheritdoc}
     */
    public function process(Message\ServerRequestInterface $request, Server\RequestHandlerInterface $handler): Message\ResponseInterface
    {
        \header_remove('X-Powered-By');

        $response = $handler->handle($request);

        if (null !== $this->serverName) {
            $response = $response->withHeader('Server', $this->serverName);
        }

        return $response
            ->withHeader('Expect-CT', \sprintf('enforce,max-age=%s', $this->maxAge))
            ->withHeader('Strict-Transport-Security', \sprintf('max-age=%s', $this->maxAge))
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-XSS-Protection', '1; mode=block');
    }
}
