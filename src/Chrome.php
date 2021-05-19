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
    private array $tabs = [];
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

    /**
     * @param bool $refresh
     * @return array
     */
    public function getTabs(bool $refresh = false): array
    {
        if (empty($this->tabs) || $refresh) {
            $response = $this->httpClient->post('/json/list');
            $tabs = $response->getParsedJsonArray();
            foreach ($tabs as $tab) {
                if (!array_key_exists($tab['id'], $this->tabs)) {
                    $tab['timeout'] = $this->timeout;
                    $this->tabs[$tab['id']] = new Tab($tab);
                }
            }
        }
        return $this->tabs;
    }

    /**
     * @param string $url
     * @param bool $autoOpen
     * @return Tab|null
     * @throws \Exception
     */
    public function checkUrl(string $url, bool $autoOpen = true): ?Tab
    {
        $emptyTab = [];
        foreach ($this->getTabs() as $tab) {
            if (parse_url($tab->url, PHP_URL_HOST) === $url) {
                return $tab;
            } elseif ($tab->url === 'about:blank') {
                $emptyTab[] = $tab;
            }
        }
        if (!empty($emptyTab)) {
            return array_shift($emptyTab);
        }
        return $autoOpen ? $this->open() : null;
    }

    /**
     * @param string|null $url
     * @return Tab|null
     * @throws \Exception
     */
    public function open(?string $url = null): ?Tab
    {
        App::debug("Opening new tab" . ($url !== null ? " url=$url" : ''));
        $response = $this->httpClient->post('/json/new' . ($url !== null ? "?" . urlencode($url) : ""));
        if ($response->getStatusCode() === 200) {
            $tab = $response->getParsedJsonArray();
            $tab['timeout'] = $this->timeout;
            $this->tabs[$tab['id']] = new Tab($tab);
            App::debug("Opened new tab" . ($url !== null ? " url=$url" : ''));
            return $this->tabs[$tab['id']];
        }
        App::error("Open new tab" . ($url !== null ? " url=$url" : '') . " failed error=" . (string)$response->getBody());
        return null;
    }

    /**
     * @return array
     */
    public function version(): array
    {
        $response = $this->httpClient->post('/json/version');
        return $response->getParsedJsonArray();
    }

    /**
     * @param string $id
     * @return bool
     */
    public function activateTabById(string $id): bool
    {
        $response = $this->httpClient->post("/json/activate/{$id}");
        return $response->getStatusCode() === 200;
    }

    /**
     * @param string $id
     * @return bool
     */
    public function closeTabById(string $id): bool
    {
        $response = $this->httpClient->post("/json/close/{$id}");
        if ($response->getStatusCode() === 200) {
            unset($this->tabs[$id]);
            return true;
        }
        return false;
    }
}
