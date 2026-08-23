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

namespace BroCode\GraphQlExplorer\Controller\Index;

use BroCode\GraphQlExplorer\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\ForwardFactory;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Public GraphQL explorer, in the spirit of Magento's own /swagger route.
 *
 * Disabled by default. When disabled it forwards to noroute rather than
 * returning 403, so the route does not advertise that it exists.
 */
class Index implements HttpGetActionInterface
{
    /**
     * @var \BroCode\GraphQlExplorer\Model\Config
     */
    private $config;

    /**
     * @var \Magento\Framework\App\RequestInterface
     */
    private $request;

    /**
     * @var \Magento\Framework\View\Result\PageFactory
     */
    private $pageFactory;

    /**
     * @var \Magento\Framework\Controller\Result\ForwardFactory
     */
    private $forwardFactory;

    /**
     * @var \Magento\Framework\Controller\Result\RawFactory
     */
    private $rawFactory;

    public function __construct(
        Config $config,
        RequestInterface $request,
        PageFactory $pageFactory,
        ForwardFactory $forwardFactory,
        RawFactory $rawFactory
    ) {
        $this->config = $config;
        $this->request = $request;
        $this->pageFactory = $pageFactory;
        $this->forwardFactory = $forwardFactory;
        $this->rawFactory = $rawFactory;
    }

    /**
     * @return \Magento\Framework\Controller\ResultInterface
     */
    public function execute()
    {
        if (!$this->config->isFrontendEnabled()) {
            return $this->forwardFactory->create()->forward('noroute');
        }

        if ($this->config->isBasicAuthEnabled() && !$this->isAuthorised()) {
            return $this->challenge();
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('GraphQL Explorer'));
        $page->getConfig()->setRobots('NOINDEX,NOFOLLOW');

        // The layout marks the block cacheable="false", which keeps this out of
        // Magento's own full page cache. These headers cover everything else in
        // front of it — Varnish, a CDN, a corporate proxy. Without both, an
        // authorised response gets cached and then served to anonymous
        // visitors, which defeats Basic Auth entirely.
        $page->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0', true);
        $page->setHeader('Pragma', 'no-cache', true);

        return $page;
    }

    /**
     * @return bool
     */
    private function isAuthorised(): bool
    {
        $expectedUser = $this->config->getBasicAuthUser();
        $expectedPassword = $this->config->getBasicAuthPassword();

        // A blank credential pair would otherwise authorise everyone the moment
        // an admin enables Basic Auth without filling the fields in.
        if ($expectedUser === '' || $expectedPassword === '') {
            return false;
        }

        $header = (string)$this->request->getHeader('Authorization');
        if ($header === '' && !empty($_SERVER['REDIRECT_HTTP_AUTHORIZATION'])) {
            // Some PHP-FPM setups drop the header unless the web server
            // forwards it explicitly; this is the usual fallback.
            $header = (string)$_SERVER['REDIRECT_HTTP_AUTHORIZATION'];
        }

        if (stripos($header, 'Basic ') !== 0) {
            return false;
        }

        $decoded = base64_decode(substr($header, 6), true);
        if ($decoded === false || strpos($decoded, ':') === false) {
            return false;
        }

        [$user, $password] = explode(':', $decoded, 2);

        // hash_equals on both halves: constant time, so a wrong username cannot
        // be distinguished from a wrong password by timing.
        return hash_equals($expectedUser, $user) && hash_equals($expectedPassword, $password);
    }

    /**
     * @return \Magento\Framework\Controller\Result\Raw
     */
    private function challenge()
    {
        $result = $this->rawFactory->create();
        $result->setHttpResponseCode(HttpResponse::STATUS_CODE_401);
        $result->setHeader('WWW-Authenticate', 'Basic realm="GraphQL Explorer"', true);
        $result->setHeader('Cache-Control', 'no-store', true);
        $result->setContents('Authentication required.');

        return $result;
    }
}
