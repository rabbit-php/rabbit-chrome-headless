<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

class Message
{
    private ?array $result = null;

    public function __construct(public readonly int $id, public readonly string $method, public readonly array $params = [])
    {
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

    public function getResult(): ?array
    {
        return $this->result;
    }
}
