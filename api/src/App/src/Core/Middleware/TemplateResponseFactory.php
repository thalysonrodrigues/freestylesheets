<?php

declare(strict_types=1);

namespace App\Core\Middleware;

use App\Middleware\HtmlResponse;
use Interop\Container\ContainerInterface;
use Zend\Expressive\Template\TemplateRendererInterface;

final class TemplateResponseFactory
{
    public function __invoke(ContainerInterface $container, string $name)
    {
        return new $name(
            $container->get(TemplateRendererInterface::class)
        );
    }
}
