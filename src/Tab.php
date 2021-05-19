<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Rabbit\Base\Core\Exception;
use Swlib\Saber;
use Swlib\Saber\WebSocket;

class Tab
{
    public string $id;
    public string $title;
    public ?string $description = null;
    public string $type;
    public string $url;
    public ?string $faviconUrl = null;
    public string $webSocketDebuggerUrl;
    public string $devtoolsFrontendUrl;
    public WebSocket $client;
    public int $timeout = 30;
    private int $msgId = 0;
    private bool $isLoop = false;
    private bool $isFirst = true;
    private bool $working = false;

    /**
     * Tab constructor.
     * @param array $attributes
     */
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

    public function checkHeadless(): array
    {
        $result = [];
        if ($this->isFirst) {
            $this->isFirst = false;
            foreach ([
                ['execute', ["Page.enable"]],
                [
                    'execute',
                    [
                        'Page.addScriptToEvaluateOnNewDocument',
                        [
                            'source' => trim(file_get_contents(__DIR__ . '/headless.js'))
                        ]
                    ]
                ]
            ] as $step) {
                [$method, $params] = $step;
                if ($method === 'call') {
                    $result = $this->call($params, []);
                } else {
                    $result = $this->$method(...$params);
                }
            }
        }
        return $result;
    }

    /**
     *
     */
    public function __destruct()
    {
        if ($this->client !== null) {
            $this->client->close();
        }
    }

    /**
     * @return bool
     */
    public function getLoop(): bool
    {
        return $this->isLoop;
    }

    /**
     * @param bool $isLoop
     */
    public function setLoop(bool $isLoop): void
    {
        $this->isLoop = $isLoop;
    }

    /**
     * @param string $method
     * @param array $params
     * @param string|null $event
     * @return array|null
     * @throws Exception
     */
    public function execute(string $method, array $params = [], array $config = [], ?string $event = null): ?array
    {
        if ($this->isLoop) {
            App::warning("The tab $this->id Already looping...");
            return null;
        }
        if ($this->working) {
            App::warning("The tab $this->id is working...");
            return null;
        }
        $this->working = true;
        if (!empty($config) && !empty($params)) {
            foreach ($params as $name => $param) {
                is_string($param) && $params[$name] = strtr($param, $config);
            }
        }
        $data = json_encode([
            'id' => ++$this->msgId,
            'method' => $method,
            'params' => $params,
        ]);
        App::debug("Execute $method with params=$data", "Headless");
        $this->client->push($data);
        while (true) {
            $res = $this->client->recv($this->timeout);
            if ($res === false) {
                App::error("Execute $method return false!", "Headless");
                throw new Exception("Execute $method return false!");
            }
            $data = json_decode($res->data, true);
            if (isset($data['error'])) {
                App::error("Execute $method error msg=$res->data", "Headless");
            }
            if (isset($data['id']) && $data['id'] === $this->msgId) {
                break;
            }
        }
        App::debug("Finish $method with result=$res->data", "Headless");
        if ($event !== null) {
            $this->event($event);
        }
        $this->working = false;
        return $data;
    }

    /**
     * @param string $method
     * @param array $params
     * @return array
     * @throws Exception
     */
    private function event(string $method, array $params = []): void
    {
        App::debug("Start awaiting event $method...");
        $i = 0;
        while (true) {
            $i++;
            $res = $this->client->recv($this->timeout);
            if ($res === false) {
                throw new Exception("Awaiting event $method return false!");
            }
            $data = json_decode($res->data, true);
            if (isset($data['error'])) {
                throw new Exception("Awaiting event $method error msg=$res->data");
            }

            if (isset($data['method'])) {
                if ($data['method'] === $method) {
                    App::debug("Finish awaiting event $method");
                    return;
                }
            } else {
                App::warning("Awaiting event $method... the $i event is not a event result=" . $res->data);
            }
        }
    }

    /**
     * @param array $time
     * @param array $steps
     * @throws \Exception
     */
    public function loop(array $time, array $steps): void
    {
        if ($this->isLoop) {
            App::warning("The tab $this->id Already looping...");
            return;
        }
        $this->isLoop = true;
        rgo(function () use ($time, $steps) {
            while ($this->isLoop) {
                foreach ($steps as $step) {
                    [$method, $params] = $step;
                    $this->$method(...$params);
                }
                [$min, $max] = $time;
                $sleepTime = rand($min, $max);
                sleep($sleepTime);
            }
        });
    }

    /**
     * @param callable $callback
     * @return mixed
     */
    public function call(callable $callback, array $params)
    {
        return $callback($params);
    }
}
