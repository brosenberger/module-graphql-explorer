<?php
/**
 * Copyright (C) 2026 Benjamin Rosenberger <bensch.rosenberger@gmail.com>
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 *
 * @copyright 2026 Benjamin Rosenberger
 * @author bensch.rosenberger@gmail.com
 * @license MIT
 * @link https://brocode.at
 */
declare(strict_types=1);

namespace BroCode\GraphQlExplorer\ViewModel;

use BroCode\GraphQlExplorer\Model\Config;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies both the Adminhtml and the public template with one JSON blob.
 *
 * Kept out of the templates so they carry no PHP logic and no inline
 * JavaScript, which a hardened CSP would block anyway.
 */
class ExplorerConfig implements ArgumentInterface
{
    /**
     * @var \Magento\Store\Api\StoreRepositoryInterface
     */
    private $storeRepository;

    /**
     * @var \Magento\Store\Model\StoreManagerInterface
     */
    private $storeManager;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $json;

    /**
     * @var \BroCode\GraphQlExplorer\Model\Config
     */
    private $config;

    public function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        RequestInterface $request,
        Json $json,
        Config $config
    ) {
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
        $this->request = $request;
        $this->json = $json;
        $this->config = $config;
    }

    /**
     * Every active store view, each with its own GraphQL endpoint.
     *
     * The endpoint is per store rather than global on purpose: store views can
     * sit on different base URLs, and querying view B through view A's host
     * gives you view A's data no matter what the Store header says.
     *
     * @return array<int, array<string, string>>
     */
    public function getStoreOptions(): array
    {
        $options = [];
        foreach ($this->storeRepository->getList() as $store) {
            if (!$store->getId() || !$store->getIsActive()) {
                continue;
            }
            $base = $this->storeManager->getStore($store->getId())
                ->getBaseUrl(UrlInterface::URL_TYPE_WEB);

            $options[] = [
                'id' => (string)$store->getId(),
                'code' => (string)$store->getCode(),
                'label' => sprintf('%s (%s)', $store->getName(), $store->getCode()),
                'endpoint' => rtrim($base, '/') . '/graphql',
            ];
        }

        return $options;
    }

    /**
     * The store the page is currently showing.
     *
     * Follows the native Adminhtml store switcher, which reloads with a "store"
     * request parameter, and falls back to the current store otherwise.
     *
     * @return string
     */
    public function getCurrentStoreCode(): string
    {
        $requested = (int)$this->request->getParam('store');
        $options = $this->getStoreOptions();

        if ($requested) {
            foreach ($options as $option) {
                if ((int)$option['id'] === $requested) {
                    return $option['code'];
                }
            }
        }

        $current = (string)$this->storeManager->getStore()->getCode();
        foreach ($options as $option) {
            if ($option['code'] === $current) {
                return $current;
            }
        }

        return $options[0]['code'] ?? 'default';
    }

    /**
     * Endpoint for the currently selected store view.
     *
     * @return string
     */
    public function getCurrentEndpoint(): string
    {
        $code = $this->getCurrentStoreCode();
        foreach ($this->getStoreOptions() as $option) {
            if ($option['code'] === $code) {
                return $option['endpoint'];
            }
        }

        return '';
    }

    /**
     * Whether the template may render a store switcher of its own.
     *
     * Always true in Adminhtml; on the public route it follows configuration,
     * because a switcher enumerates every store code to whoever can reach it.
     *
     * @param bool $isAdmin
     * @return bool
     */
    public function isStoreSwitchAllowed(bool $isAdmin = false): bool
    {
        return $isAdmin || $this->config->isStoreSwitchAllowed();
    }

    /**
     * @param bool $isAdmin
     * @return string
     */
    public function getJsonConfig(bool $isAdmin = false): string
    {
        $current = $this->getCurrentStoreCode();
        $stores = $this->isStoreSwitchAllowed($isAdmin)
            ? $this->getStoreOptions()
            : array_values(array_filter(
                $this->getStoreOptions(),
                static function (array $option) use ($current): bool {
                    return $option['code'] === $current;
                }
            ));

        return $this->json->serialize([
            'stores' => $stores,
            'currentStore' => $current,
            'endpoint' => $this->getCurrentEndpoint(),
            'isAdmin' => $isAdmin,
        ]);
    }
}
