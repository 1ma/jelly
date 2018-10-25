<?php

declare(strict_types=1);

namespace ABC\Middleware;

use ABC\Constants;
use ABC\Util\Assert;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use Throwable;
use TypeError;

final class ExceptionTrapper implements Server\MiddlewareInterface
{
    /**
     * @var Container\ContainerInterface
     */
    private $container;

    public function __construct(Container\ContainerInterface $container)
    {
        $this->container = $container;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function process(
        Message\ServerRequestInterface $request,
        Server\RequestHandlerInterface $handler
    ): Message\ResponseInterface
    {
        try {
            return $handler->handle($request);
        } catch (Throwable $exception) {
            $exceptionHandler = Assert::isARequestHandler(
                $this->container->get(Constants::EXCEPTION_HANDLER)
            );

            return $exceptionHandler->handle(
                $request
                    ->withAttribute(Constants::ERROR_TYPE, 500)
                    ->withAttribute(Constants::EXCEPTION, $exception)
            );
        }
    }
}
