<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use HeadlessChromium\Browser;
use HeadlessChromium\BrowserFactory;
use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Exception\InvalidArgumentException;
use Throwable;

class Manager implements InitInterface
{
    private BrowserFactory $factory;

    private ?string $uri = null;

    protected array $options = [];

    public function __construct(string $uri = null, array $options = [], string $path = null)
    {
        $this->factory = new BrowserFactory($path);
        $this->uri = $uri;
        $this->options = $options;
    }

    public function init(): void
    {
        if ($this->uri) {
            $arr = explode(',', $this->uri);
            foreach ($arr as $uri) {
                [$name, $uri] = explode('=', $uri);
                $this->add($name, $uri, $this->options);
            }
        }
    }

    public function add(string $name, string $uri, array $options = []): Browser
    {
        if (isset($this->pool[$name])) {
            throw new InvalidArgumentException("$name is exist!");
        }
        $this->pool[$name] = BrowserFactory::connectToBrowser($uri, $options);
        return $this->pool[$name];
    }

    public function del(string $name): void
    {
        if (isset($this->pool[$name])) {
            $this->pool[$name]->close();
            unset($this->pool[$name]);
        }
    }

    public function getBrowser(string $name, array $options = []): Browser
    {
        if (!isset($this->pool[$name])) {
            $this->pool[$name] = $this->factory->createBrowser($options);
        } else {
            $browser = $this->pool[$name];
            $conn = $browser->getConnection();
            if (!$conn->isConnected()) {
                try {
                    $conn->connect();
                } catch (Throwable $e) {
                    $browser->close();
                    $this->pool[$name] = $this->factory->createBrowser($options);
                }
            }
        }
        return $this->pool[$name];
    }
}
