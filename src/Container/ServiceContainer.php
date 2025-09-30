<?php

/**
 * -------------------------------------------------------------------------
 * advancedldap plugin for GLPI
 * -------------------------------------------------------------------------
 *
 * MIT License
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
 * -------------------------------------------------------------------------
 * @copyright Copyright (C) 2018-2025 by Teclib'.
 * @license   MIT https://opensource.org/licenses/mit-license.php
 * @link      https://github.com/pluginsGLPI/advancedldap
 * -------------------------------------------------------------------------
 */

namespace GlpiPlugin\Advancedldap\Container;

use GlpiPlugin\Advancedldap\Contracts\AssetFieldProviderInterface;
use GlpiPlugin\Advancedldap\Contracts\ConfigurationInterface;
use GlpiPlugin\Advancedldap\Contracts\DatabaseInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapConnectionInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapFilterParserInterface;
use GlpiPlugin\Advancedldap\Contracts\LdapAttributeMapperInterface;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterFormHelperInterface;
use GlpiPlugin\Advancedldap\Contracts\SyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Contracts\AuthLdapSyncFilterRepositoryInterface;
use GlpiPlugin\Advancedldap\Factories\AssetFieldProviderFactory;
use GlpiPlugin\Advancedldap\Services\AssetFieldService;
use GlpiPlugin\Advancedldap\Services\AssetCreationService;
use GlpiPlugin\Advancedldap\Services\AssetTypeClassifier;
use GlpiPlugin\Advancedldap\Services\GlpiConfigurationService;
use GlpiPlugin\Advancedldap\Services\GlpiDatabaseService;
use GlpiPlugin\Advancedldap\Services\GlpiLdapConnectionService;
use GlpiPlugin\Advancedldap\Services\LdapFilterParser;
use GlpiPlugin\Advancedldap\Services\LdapAttributeMapper;
use GlpiPlugin\Advancedldap\Services\SyncFilterFormHelper;
use GlpiPlugin\Advancedldap\Services\LdapSyncService;
use GlpiPlugin\Advancedldap\Services\LdapTestService;
use GlpiPlugin\Advancedldap\Services\LdapToInventoryConverter;
use GlpiPlugin\Advancedldap\Services\LdapInventoryService;
use GlpiPlugin\Advancedldap\Services\SyncFilterService;
use GlpiPlugin\Advancedldap\Repositories\SyncFilterRepository;
use GlpiPlugin\Advancedldap\Repositories\AuthLdapSyncFilterRepository;

/**
 * Simple dependency injection container
 */
class ServiceContainer
{
    /** @var array<string, object> */
    private array $services = [];

    /** @var array<string, callable> */
    private array $factories = [];

    private static ?ServiceContainer $instance = null;

    /**
     * Get singleton instance
     *
     * @return ServiceContainer
     */
    public static function getInstance(): ServiceContainer
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->registerDefaultServices();
        }

        return self::$instance;
    }

    /**
     * Register a service factory
     *
     * @param string $id Service identifier
     * @param callable $factory Service factory function
     * @return void
     */
    public function register(string $id, callable $factory): void
    {
        $this->factories[$id] = $factory;
    }

    /**
     * Get a service instance
     *
     * @param string $id Service identifier
     * @return object Service instance
     * @throws \InvalidArgumentException If service not found
     */
    public function get(string $id): object
    {
        // Return existing instance if already created
        if (isset($this->services[$id])) {
            return $this->services[$id];
        }

        // Create service using factory if available
        if (isset($this->factories[$id])) {
            $this->services[$id] = $this->factories[$id]($this);
            return $this->services[$id];
        }

        throw new \InvalidArgumentException("Service '$id' not found");
    }

    /**
     * Check if a service is registered
     *
     * @param string $id Service identifier
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->services[$id]) || isset($this->factories[$id]);
    }

    /**
     * Register default services
     *
     * @return void
     */
    private function registerDefaultServices(): void
    {
        // Core services
        $this->register(DatabaseInterface::class, function () {
            return new GlpiDatabaseService();
        });

        $this->register(ConfigurationInterface::class, function () {
            return new GlpiConfigurationService();
        });

        // Register the service also by its class name for direct access
        $this->register(GlpiConfigurationService::class, function () {
            return new GlpiConfigurationService();
        });

        $this->register(LdapConnectionInterface::class, function () {
            return new GlpiLdapConnectionService();
        });

        // LDAP utilities
        $this->register(LdapFilterParserInterface::class, function () {
            return new LdapFilterParser();
        });

        $this->register(LdapAttributeMapperInterface::class, function () {
            return new LdapAttributeMapper();
        });

        // Factory
        $this->register(AssetFieldProviderFactory::class, function () {
            return new AssetFieldProviderFactory();
        });

        // Asset field service
        $this->register(AssetFieldProviderInterface::class, function (ServiceContainer $container) {
            return new AssetFieldService(
                $container->get(ConfigurationInterface::class),
                $container->get(DatabaseInterface::class),
                $container->get(AssetFieldProviderFactory::class),
            );
        });

        // LDAP test service
        $this->register(LdapTestService::class, function (ServiceContainer $container) {
            return new LdapTestService(
                $container->get(LdapConnectionInterface::class),
                $container->get(DatabaseInterface::class),
                $container->get(AssetFieldProviderInterface::class),
            );
        });

        // Repositories
        $this->register(AuthLdapSyncFilterRepositoryInterface::class, function (ServiceContainer $container) {
            return new AuthLdapSyncFilterRepository(
                $container->get(DatabaseInterface::class),
            );
        });

        $this->register(SyncFilterRepositoryInterface::class, function (ServiceContainer $container) {
            return new SyncFilterRepository(
                $container->get(DatabaseInterface::class),
                $container->get(AuthLdapSyncFilterRepositoryInterface::class),
            );
        });

        // SyncFilter service
        $this->register(SyncFilterService::class, function (ServiceContainer $container) {
            return new SyncFilterService(
                $container->get(SyncFilterRepositoryInterface::class),
                $container->get(AuthLdapSyncFilterRepositoryInterface::class),
            );
        });

        // SyncFilter form helper service
        $this->register(SyncFilterFormHelperInterface::class, function (ServiceContainer $container) {
            return new SyncFilterFormHelper(
                $container->get(SyncFilterRepositoryInterface::class),
                $container->get(LdapConnectionInterface::class),
            );
        });

        // Asset type classifier service
        $this->register(AssetTypeClassifier::class, function (ServiceContainer $container) {
            return new AssetTypeClassifier(
                $container->get(ConfigurationInterface::class)
            );
        });

        // Asset creation service
        $this->register(AssetCreationService::class, function (ServiceContainer $container) {
            return new AssetCreationService(
                $container->get(DatabaseInterface::class),
            );
        });

        // LDAP to inventory converter service
        $this->register(LdapToInventoryConverter::class, function () {
            return new LdapToInventoryConverter();
        });

        // LDAP inventory service
        $this->register(LdapInventoryService::class, function (ServiceContainer $container) {
            return new LdapInventoryService(
                $container->get(LdapToInventoryConverter::class),
            );
        });

        // LDAP synchronization service
        $this->register(LdapSyncService::class, function (ServiceContainer $container) {
            $service = new LdapSyncService(
                $container->get(LdapConnectionInterface::class),
                $container->get(AssetCreationService::class),
                $container->get(AssetTypeClassifier::class),
            );

            // Only inject the inventory service if GLPI inventory is enabled
            $configService = $container->get(ConfigurationInterface::class);
            if ($configService->isInventoryEnabled()) {
                $service->setLdapInventoryService($container->get(LdapInventoryService::class));
                \Toolbox::logDebug("ServiceContainer: LdapInventoryService INJECTED - inventory workflow is available");
            } else {
                \Toolbox::logDebug("ServiceContainer: LdapInventoryService NOT injected - inventory disabled, fallback to traditional workflow");
            }

            return $service;
        });

    }

    /**
     * Clear all services (useful for testing)
     *
     * @return void
     */
    public function clear(): void
    {
        $this->services = [];
        $this->factories = [];
    }

    /**
     * Reset singleton instance (useful for testing)
     *
     * @return void
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
