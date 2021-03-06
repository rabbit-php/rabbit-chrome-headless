<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Swlib\Saber;

class Chrome
{
    private Saber $httpClient;
    private int $timeout = 30;

    public function __construct(string $dsn, Saber $httpClient = null)
    {
        $this->dsn = $dsn;
        $this->httpClient = $httpClient ?? Saber::create([
            'base_uri' => $dsn,
            'timeout' => $this->timeout
        ]);
    }

    public function getPages(): array
    {
        $pages = [];
        foreach ($this->httpClient->post('/json/list')->getParsedJsonArray() ?? [] as $page) {
            if (in_array($page['type'], ['page', 'iframe'])) {
                $pages[$page['id']] = $page;
            }
        }
        return $pages;
    }

    public function createPage(): ?page
    {
        foreach ($this->getPages() as $page) {
            if (!$page instanceof Page) {
                $this->closePageById($page['id']);
            }
        }
        return $this->open();
    }

    public function open(): ?Page
    {
        $response = $this->httpClient->post('/json/new');
        if ($response->getStatusCode() === 200) {
            $page = $response->getParsedJsonArray();
            $page['timeout'] = $this->timeout;
            return create(Page::class, ['attributes' => $page], false);
        }
        App::error("Open new page" . " failed error=" . (string)$response->getBody());
        return null;
    }

    public function buildPage(array $page): Page
    {
        $page['timeout'] = $this->timeout;
        return create(Page::class, ['attributes' => $page], false);
    }

    public function version(): array
    {
        $response = $this->httpClient->post('/json/version');
        return $response->getParsedJsonArray();
    }

    public function activatePageById(string $id): bool
    {
        $response = $this->httpClient->post("/json/activate/{$id}");
        return $response->getStatusCode() === 200;
    }

    public function closePageById(string $id): bool
    {
        $response = $this->httpClient->post("/json/close/{$id}");
        if ($response->getStatusCode() === 200) {
            return true;
        }
        return false;
    }
}
