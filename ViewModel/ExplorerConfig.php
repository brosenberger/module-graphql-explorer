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

use Magento\Framework\Serialize\Serializer\Json;
use Magento\Framework\UrlInterface;
use Magento\Framework\View\Element\Block\ArgumentInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Supplies the Adminhtml template with everything GraphiQL needs, as one JSON blob.
 *
 * Kept out of the template so the phtml stays free of PHP logic and free of
 * inline JavaScript, which the CSP in a hardened Adminhtml would block anyway.
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
     * @var \Magento\Framework\UrlInterface
     */
    private $url;

    /**
     * @var \Magento\Framework\Serialize\Serializer\Json
     */
    private $json;

    public function __construct(
        StoreRepositoryInterface $storeRepository,
        StoreManagerInterface $storeManager,
        UrlInterface $url,
        Json $json
    ) {
        $this->storeRepository = $storeRepository;
        $this->storeManager = $storeManager;
        $this->url = $url;
        $this->json = $json;
    }

    /**
     * Absolute URL of the GraphQL endpoint.
     *
     * Built from the default store's base URL rather than the Adminhtml URL:
     * the admin may live on a separate domain, and /graphql does not exist there.
     *
     * @return string
     */
    public function getEndpointUrl(): string
    {
        $base = $this->storeManager->getStore($this->getDefaultStoreCode())
            ->getBaseUrl(UrlInterface::URL_TYPE_WEB);

        return rtrim($base, '/') . '/graphql';
    }

    /**
     * Every active store view, for the store switcher.
     *
     * The switcher sets the "Store" request header, which is how Magento's
     * GraphQL layer selects a store view. Without it every query answers for
     * the default view, which is the single most confusing thing about
     * exploring a multi-store catalog.
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
            $options[] = [
                'code' => (string)$store->getCode(),
                'label' => sprintf('%s (%s)', $store->getName(), $store->getCode()),
            ];
        }

        return $options;
    }

    /**
     * @return string
     */
    public function getDefaultStoreCode(): string
    {
        $options = $this->getStoreOptions();

        return $options[0]['code'] ?? 'default';
    }

    /**
     * The whole client-side configuration, JSON encoded for a data attribute.
     *
     * @return string
     */
    public function getJsonConfig(): string
    {
        return $this->json->serialize([
            'endpoint' => $this->getEndpointUrl(),
            'stores' => $this->getStoreOptions(),
            'defaultStore' => $this->getDefaultStoreCode(),
        ]);
    }
}
