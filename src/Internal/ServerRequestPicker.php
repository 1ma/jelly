<?php

declare(strict_types=1);

namespace Jelly\Internal;

use GuzzleHttp\Psr7\HttpFactory;
use Laminas\Diactoros;
use LogicException;
use Nyholm\Psr7Server\ServerRequestCreator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ServerRequestInterface;
use Slim\Psr7\Factory as Slim;
use function class_exists;

final class ServerRequestPicker
{
    /**
     * @throws LogicException If none of the supported PSR-7 implementations
     *                        is installed along the framework.
     */
    public static function fromGlobals(): ServerRequestInterface
    {
        return self::chooseImplementation()->fromGlobals();
    }

    private static function chooseImplementation(): ServerRequestCreator
    {
        if (class_exists(Psr17Factory::class)) {
            $factory = new Psr17Factory();
            return new ServerRequestCreator($factory, $factory, $factory, $factory);
        }

        // Fix until https://github.com/Nyholm/psr7-server/pull/51 is merged
        $_SERVER['SERVER_PORT'] = (int) $_SERVER['SERVER_PORT'];

        if (class_exists(Slim\RequestFactory::class)) {
            return new ServerRequestCreator(
                new Slim\ServerRequestFactory(),
                new Slim\UriFactory(),
                new Slim\UploadedFileFactory(),
                new Slim\StreamFactory(),
            );
        }

        if (class_exists(Diactoros\RequestFactory::class)) {
            return new ServerRequestCreator(
                new Diactoros\ServerRequestFactory(),
                new Diactoros\UriFactory(),
                new Diactoros\UploadedFileFactory(),
                new Diactoros\StreamFactory()
            );
        }

        if (class_exists(HttpFactory::class)) {
            $factory = new HttpFactory();
            return new ServerRequestCreator($factory, $factory, $factory, $factory);
        }

        throw new LogicException('Need at least one psr-7 implementation');
    }
}
