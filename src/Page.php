<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Rabbit\Base\Core\Exception;
use Rabbit\Base\Exception\InvalidArgumentException;
use RuntimeException;
use Swlib\Saber;
use Swlib\Saber\WebSocket;

class Page
{
    public int $timeout = 30;

    private string $id;
    private string $title;
    private string $url;
    private int $msgId = 0;
    private WebSocket $client;
    private string $webSocketDebuggerUrl;
    private string $devtoolsFrontendUrl;
    private ?string $description = null;
    private string $type;
    private ?string $faviconUrl = null;

    private array $msgs = [];

    public function __construct(array $attributes)
    {
        foreach ($attributes as $name => $value) {
            $this->$name = $value;
        }
        $this->client = Saber::websocket(str_replace(
            ['http', 'https'],
            ['ws', 'wss'],
            $this->webSocketDebuggerUrl
        ));
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

    public function __destruct()
    {
        if ($this->client !== null) {
            $this->client->close();
        }
    }

    public function clear(): void
    {
        $this->msgs = [];
    }

    public function execute(string $method, array $params = []): ?Message
    {
        $msg = new Message(++$this->msgId, $method, $params);
        $this->msgs[$msg->id] = $msg;
        $data = $msg->getRequest();
        App::debug("Execute $method with params=$data", "Headless");
        $this->client->push($data);
        $start = time();
        while (time() - $start < $this->timeout) {
            $res = $this->client->recv($this->timeout);
            if ($res === false) {
                App::error("Execute $method return false!", "Headless");
                throw new Exception("Execute $method return false!");
            }
            $data = json_decode($res->data, true);
            if (isset($data['error'])) {
                App::error("Execute $method error msg=$res->data", "Headless");
            }
            if (isset($data['id']) && array_key_exists($data['id'], $this->msgs)) {
                $this->msgs[$data['id']]->setResult($data);
                App::debug("Finish $method with result=$res->data", "Headless");
                return $this->msgs[$data['id']];
            }
        }
        throw new RuntimeException("Awaiting execute $method at {$this->timeout}s timeout");
    }

    public function event(string $method, int $timeout = null): array
    {
        App::debug("Start awaiting event $method...");
        $i = 0;
        $start = time();
        $timeout = $timeout ?? $this->timeout;
        while (time() - $start < $timeout) {
            $i++;
            $res = $this->client->recv($timeout);
            if ($res === false) {
                throw new Exception("Awaiting event $method return false!");
            }
            $data = json_decode($res->data, true);
            if (isset($data['error'])) {
                throw new Exception("Awaiting event $method error msg=$res->data");
            }

            if (($data['method'] ?? false) && $data['method'] === $method) {
                App::debug("Finish awaiting event $method");
                return $data;
            }
        }
        throw new RuntimeException("Awaiting event $method at {$timeout}s timeout, the {$i} times event is " . ($res->data['method'] ?? 'null'));
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

    public function waitForNavigation(string $evnet = 'Page.loadEventFired', int $timeout = 30): ?array
    {
        return $this->event($evnet, $timeout);
    }

    public function evaluate(string $expression): self
    {
        $this->execute("Runtime.evaluate", ['expression' => $expression]);
        return $this;
    }

    public function content(string $cmd = 'document.documentElement.innerHTML', int $wait = 0): ?string
    {
        if ($wait > 0) {
            $now = time();
            while (time() - $now < $wait) {
                $res = $this->execute("Runtime.evaluate", ['expression' => $cmd]);
                if ($ret = $res->getResult()['result']['value'] ?? false) {
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

    public function waitForSelector(string $query, int $timeout = 30): ?Dom
    {
        $start = time();
        while (time() - $start < $timeout) {
            $res = $this->execute("Runtime.evaluate", ['expression' => "document.querySelector('{$query}')"])->getResult();
            if ($res['result']['type'] ?? false) {
                return new Dom($query, $this);
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
