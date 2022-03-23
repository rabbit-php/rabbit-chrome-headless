<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Rabbit\Base\Exception\InvalidArgumentException;

class Dom
{
    protected bool $throwable = true;

    public function __construct(public readonly string $dom, public readonly  Page $page, public readonly  array $params = [])
    {
    }

    public function withThrow(bool $throwable = true): self
    {
        $this->throwable = $throwable;
        return $this;
    }

    public function __call($name, $arguments)
    {
        $args = empty($arguments) ? '()' : '(' . implode(',', $arguments) . ')';
        $cmd = $this->dom;
        if (!str_contains($this->dom, 'document.querySelector')) {
            $cmd = "document.querySelector('{$this->dom}')";
        }
        $cmd .= ".{$name}{$args}";
        return $this->run($cmd);
    }

    public function __get($name)
    {
        $cmd = $this->dom;
        if (!str_contains($this->dom, 'document.querySelector')) {
            $cmd = "document.querySelector('{$this->dom}')";
        }
        $cmd .= ".{$name}";
        return $this->run($cmd);
    }

    private function run(string $cmd): array
    {
        $res = $this->page->execute("Runtime.evaluate", [...$this->params, 'expression' => $cmd])->getResult();
        if ($res['exceptionDetails'] ?? false) {
            $msg = "$cmd error! msg={$res['result']['description']}";
            $this->throwable ? throw new InvalidArgumentException($msg) : App::error($msg);
        }
        return $res;
    }
}
