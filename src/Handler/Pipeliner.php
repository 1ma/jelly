<?php

declare(strict_types=1);

namespace ABC\Handler;

use ABC\Constants;
use ABC\Util\Seam;
use Psr\Container;
use Psr\Http\Message;
use Psr\Http\Server;
use TypeError;

final class Pipeliner implements Server\RequestHandlerInterface
{
    /**
     * @var Container\ContainerInterface
     */
    private $container;

    /**
     * @var string[]
     */
    private $middlewares;

    public function __construct(
        Container\ContainerInterface $container,
        array $middlewares
    )
    {
        $this->container = $container;
        $this->middlewares = $middlewares;
    }

    /**
     * @throws Container\NotFoundExceptionInterface
     * @throws TypeError
     */
    public function handle(Message\ServerRequestInterface $request): Message\ResponseInterface
    {
        $pipe = $this->container->get(
            $request->getAttribute(Constants::HANDLER)
        );

        foreach ($this->middlewares as $service) {
            $pipe = new Seam($this->container->get($service), $pipe);
        }

        return $pipe->handle($request);
    }
}
