<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Exception\InvalidArgumentException;

class Manager implements InitInterface
{
    private ?string $uri = null;
    public function __construct(string $uri = null)
    {
        $this->uri = $uri;
    }

    public function init(): void
    {
        if ($this->uri) {
            $arr = explode(',', $this->uri);
            foreach ($arr as $uri) {
                [$name, $uri] = explode('=', $uri);
                $this->getBrowser($name, $uri);
            }
        }
    }

    public function getBrowser(string $name, string $uri): Chrome
    {
        if (isset($this->pool[$name])) {
            throw new InvalidArgumentException("$name is exist!");
        }
        $this->pool[$name] = new Chrome($uri);
        return $this->pool[$name];
    }

    public function del(string $name): void
    {
        if (isset($this->pool[$name])) {
            unset($this->pool[$name]);
        }
    }
}
