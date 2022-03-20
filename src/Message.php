<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

class Message
{
    private ?array $result = null;

    private static int $mid = 0;

    public readonly int $id;

    public function __construct(public readonly string $method, public readonly array $params = [])
    {
        $this->id = self::$mid++;
    }

    public function __toString(): string
    {
        return json_encode([
            'id' => $this->id,
            'method' => $this->method,
            'params' => [
                'awaitPromise' => true,
                'returnByValue' => true,
                'userGesture' => true,
                ...$this->params
            ],
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
