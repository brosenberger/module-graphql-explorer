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

namespace BroCode\GraphQlExplorer\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Encryption\EncryptorInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Typed access to the module's store configuration.
 *
 * Everything here defaults to off. A GraphQL IDE on a public route is a
 * deliberate act, never something a module turns on for you.
 */
class Config
{
    private const XML_PATH_FRONTEND_ENABLED = 'brocode_graphqlexplorer/frontend/enabled';
    private const XML_PATH_BASIC_AUTH_ENABLED = 'brocode_graphqlexplorer/frontend/basic_auth_enabled';
    private const XML_PATH_BASIC_AUTH_USER = 'brocode_graphqlexplorer/frontend/basic_auth_user';
    private const XML_PATH_BASIC_AUTH_PASSWORD = 'brocode_graphqlexplorer/frontend/basic_auth_password';
    private const XML_PATH_ALLOW_STORE_SWITCH = 'brocode_graphqlexplorer/frontend/allow_store_switch';

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    private $scopeConfig;

    /**
     * @var \Magento\Framework\Encryption\EncryptorInterface
     */
    private $encryptor;

    public function __construct(ScopeConfigInterface $scopeConfig, EncryptorInterface $encryptor)
    {
        $this->scopeConfig = $scopeConfig;
        $this->encryptor = $encryptor;
    }

    /**
     * @param int|string|null $store
     * @return bool
     */
    public function isFrontendEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_FRONTEND_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param int|string|null $store
     * @return bool
     */
    public function isBasicAuthEnabled($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_BASIC_AUTH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * @param int|string|null $store
     * @return string
     */
    public function getBasicAuthUser($store = null): string
    {
        return (string)$this->scopeConfig->getValue(
            self::XML_PATH_BASIC_AUTH_USER,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }

    /**
     * Decrypted Basic Auth password.
     *
     * Stored with backend_model Encrypted and type="obscure" in system.xml, so
     * the value is neither readable in Adminhtml nor stored in plaintext.
     *
     * @param int|string|null $store
     * @return string
     */
    public function getBasicAuthPassword($store = null): string
    {
        $value = (string)$this->scopeConfig->getValue(
            self::XML_PATH_BASIC_AUTH_PASSWORD,
            ScopeInterface::SCOPE_STORE,
            $store
        );

        return $value === '' ? '' : (string)$this->encryptor->decrypt($value);
    }

    /**
     * Whether the public page may list and switch store views.
     *
     * Off by default: the switcher enumerates every active store code, which is
     * information a public page has no reason to hand out.
     *
     * @param int|string|null $store
     * @return bool
     */
    public function isStoreSwitchAllowed($store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ALLOW_STORE_SWITCH,
            ScopeInterface::SCOPE_STORE,
            $store
        );
    }
}
