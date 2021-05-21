<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\Exception\InvalidArgumentException;

class Message
{
    private int $id;
    private string $method;
    private array $params = [];
    private array $result = [];

    public function __construct(int $id, string $method, array $params = [])
    {
        $this->id = $id;
        $this->method = $method;
        $this->params = $params;
    }

    public function __get($name)
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }
        throw new InvalidArgumentException("Message has no property $name!");
    }

    public function getRequest(): string
    {
        return json_encode([
            'id' => $this->id,
            'method' => $this->method,
            'params' => $this->params,
        ]);
    }

    public function setResult(array $result): void
    {
        $this->result = $result['result'];
    }

    public function getResult(): array
    {
        return $this->result;
    }
}
