<?php

declare(strict_types=1);

namespace Rabbit\Chrome\Headless;

use Rabbit\Base\App;
use Swlib\Saber;

/**
 * Class Chrome
 * @package addons\headless
 */
class Chrome
{
    private array $pages = [];
    private Saber $httpClient;
    private int $timeout = 30;

    public function __construct(string $dsn, Saber $httpClient = null)
    {
        $this->dsn = $dsn;
        $this->httpClient = $httpClient ?? Saber::create([
            'base_uri' => $dsn,
            'use_pool' => true,
            'timeout' => $this->timeout
        ]);
    }

    public function getPages(): array
    {
        if (empty($this->pages)) {
            $response = $this->httpClient->post('/json/list');
            $pages = $response->getParsedJsonArray();
            foreach ($pages as $page) {
                if (!array_key_exists($page['id'], $this->pages)) {
                    $page['timeout'] = $this->timeout;
                    $this->pages[$page['id']] = new Page($page);
                }
            }
        }
        return $this->pages;
    }

    public function checkUrl(string $url, bool $autoOpen = true): ?page
    {
        $emptypage = [];
        $urlArr = explode(',', $url);
        foreach ($this->getPages() as $page) {
            if (in_array(parse_url($page->url, PHP_URL_HOST), $urlArr)) {
                return $page;
            } elseif ($page->url === 'about:blank') {
                $emptypage[] = $page;
            }
        }
        if (!empty($emptypage)) {
            return array_shift($emptypage);
        }
        return $autoOpen ? $this->open() : null;
    }

    private function open(): ?Page
    {
        App::debug("Opening new page");
        $response = $this->httpClient->post('/json/new');
        if ($response->getStatusCode() === 200) {
            $page = $response->getParsedJsonArray();
            $page['timeout'] = $this->timeout;
            $this->pages[$page['id']] = new Page($page);
            App::debug("Opened new page");
            return $this->pages[$page['id']];
        }
        App::error("Open new page" . " failed error=" . (string)$response->getBody());
        return null;
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
            unset($this->pages[$id]);
            $this->pages = array_slice($this->pages, 0, null, true);
            return true;
        }
        return false;
    }
}
