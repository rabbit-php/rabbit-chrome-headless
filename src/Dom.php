<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Rabbit\Base\Exception\InvalidArgumentException;

class Dom
{
    protected string $dom;
    protected Page $page;

    protected bool $throwable = true;

    public function __construct(string $dom, Page $page)
    {
        $this->dom = $dom;
        $this->page = $page;
    }

    public function withThrow(bool $throwable = true): self
    {
        $this->throwable = $throwable;
        return $this;
    }

    public function __call($name, $arguments)
    {
        $args = empty($arguments) ? '()' : '(' . implode(',', $arguments) . ')';
        $cmd = "document.querySelector('{$this->dom}').{$name}{$args}";
        $res = $this->page->execute("Runtime.evaluate", ['expression' => $cmd])->getResult();
        if ($res['exceptionDetails'] ?? false) {
            $msg = "$cmd error! msg={$res['result']['description']}";
            $this->throwable ? throw new InvalidArgumentException($msg) : App::error($msg);
        }
        return $this->page;
    }
}
