<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Rabbit\Base\Contract\InitInterface;
use Rabbit\Base\Core\Channel;
use Rabbit\Base\Core\LoopControl;
use Rabbit\Base\Exception\InvalidArgumentException;
use Swlib\Saber;
use Swlib\Saber\WebSocket;

class Page implements InitInterface
{
    public int $timeout = 30;

    public readonly string $id;
    public readonly string $title;
    public readonly string $url;
    public readonly string $webSocketDebuggerUrl;
    public readonly string $devtoolsFrontendUrl;
    public readonly ?string $description;
    public readonly string $type;
    public readonly ?string $faviconUrl;

    private array $msgs = [];
    private array $listens = [];
    private ?WebSocket $client = null;
    private ?LoopControl $lc = null;
    private ?Channel $channel = null;
    private int $wait = 0;

    public function __construct(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->$name = $value;
        }

        $this->channel = new Channel();
    }

    public function init(): void
    {
        $this->client = Saber::websocket(str_replace(
            ['http', 'https'],
            ['ws', 'wss'],
            $this->webSocketDebuggerUrl
        ));
        $this->lc = loop(function () {
            $res = $this->client->recv();
            if ($res === false) {
                return;
            }
            $data = json_decode($res->data, true);
            if ($data['error'] ?? false) {
                App::error(json_encode($data['error']));
            } elseif ((false !== $id = $data['id'] ?? false) && ($msg = $this->msgs[$id] ?? false)) {
                $msg->setResult($data);
            } elseif ($method = $data['method'] ?? false) {
                if ($func = $this->listens[$method] ?? false) {
                    $func($data['params'] ?? null);
                } elseif ($this->wait > 0) {
                    $this->channel->push($data);
                }
            }
        });
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new InvalidArgumentException("page has no property $name!");
    }

    public function fixHeadless(): self
    {
        $this->execute("Page.enable");
        $this->execute("Page.addScriptToEvaluateOnNewDocument", [
            'source' => trim(file_get_contents(__DIR__ . '/headless.js'))
        ]);
        return $this;
    }

    public function setPreScript(string $script): void
    {
        if ($this->isNew) {
            $this->isNew = false;
            $this->execute("Page.addScriptToEvaluateOnNewDocument", [
                'source' => $script
            ]);
        }
    }

    public function on(string $event, callable $func): void
    {
        $this->listens[$event] = $func;
    }

    public function __destruct()
    {
        $this->close();
    }

    public function close(): void
    {
        if ($this->client !== null) {
            $this->client->close();
            $this->client = null;
        }
        if ($this->lc !== null) {
            $this->lc->shutdown();
            $this->lc = null;
        }
        if ($this->channel !== null) {
            $this->channel->close();
            $this->channel = null;
        }
    }

    public function execute(string $method, array $params = [], ?int $timeout = null): Message
    {
        $msg = new Message($method, $params);
        $this->msgs[$msg->id] = $msg;
        $data = (string)$msg;
        $this->client->push($data);
        $timeout = $timeout ?? $this->timeout;
        $msg->channel->pop($timeout);
        unset($this->msgs[$msg->id]);
        return $msg;
    }

    public function call(callable $callback, array $params)
    {
        return $callback($params);
    }

    public function navigate(string $url): self
    {
        $this->execute("Page.navigate", ['url' => $url]);
        return $this;
    }

    public function waitForNavigation(string $event = 'Page.loadEventFired', int $timeout = 30): ?array
    {
        $this->wait = $timeout;
        while (true) {
            $ret = $this->channel->pop($timeout);
            if ($ret === false) {
                $this->wait = 0;
                return null;
            }
            if ($ret['method'] === $event) {
                $this->wait = 0;
                return $ret;
            }
        }
    }

    public function evaluate(string $expression, array $params = [], int $timeout = null): self
    {
        $this->execute("Runtime.evaluate", [...$params, 'expression' => $expression], $timeout);
        return $this;
    }

    public function content(string $cmd = 'document.documentElement.innerHTML', array $params = [], int $wait = 0): ?string
    {
        if ($wait > 0) {
            $now = time();
            while (time() - $now < $wait) {
                $res = $this->execute("Runtime.evaluate", [...$params, 'expression' => $cmd], $wait)->getResult();
                if ($ret = $res['result']['value'] ?? null) {
                    return $ret;
                }
                usleep(500 * 1000);
            }
            return null;
        } else {
            $res = $this->execute("Runtime.evaluate", ['expression' => $cmd]);
            return $res->getResult()['result']['value'] ?? null;
        }
    }

    public function waitForSelector(string $query, array $params = [], int $timeout = 30): ?Dom
    {
        $start = time();
        if (!str_contains($query, 'document.querySelector')) {
            $query = "document.querySelector('{$query}')";
        }
        while (time() - $start < $timeout) {
            $res = $this->execute("Runtime.evaluate", [...$params, 'expression' => $query], $timeout)->getResult();
            if (($res['result']['type'] ?? false) && (!isset($res['result']['subtype']) || $res['result']['subtype'] !== 'null')) {
                return new Dom($query, $this, $params);
            }
            usleep(500 * 1000);
        }
        return null;
    }

    public function reload(bool $ignoreCache = false): self
    {
        $this->execute("Page.reload", ['ignoreCache' => $ignoreCache]);
        return $this;
    }

    public function navigateOrReload(string $url, bool $ignoreCache = true, bool $auto = true): self
    {
        if ($this->url !== $url) {
            return $this->navigate($url);
        } elseif ($auto) {
            return $this;
        } else {
            return $this->reload($ignoreCache);
        }
    }
}
