<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use HeadlessChromium\Browser\ProcessAwareBrowser;
use HeadlessChromium\BrowserFactory;

class Manager
{
    private BrowserFactory $factory;

    protected array $pool = [];

    public function __construct(string $path = null)
    {
        $this->factory = new BrowserFactory($path);
    }

    public function getBrowser(string $name, array $options = []): ProcessAwareBrowser
    {
        if (!isset($this->pool[$name])) {
            $this->pool[$name] = $this->factory->createBrowser($options);
        }
        return $this->pool[$name];
    }
}
