<?php

declare(strict_types=1);

/*
 * This file is part of the TYPO3 CMS extension "monitoring_solr".
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

namespace mteu\Monitoring\Solr\Tests\Functional;

use mteu\Monitoring\Provider\MonitoringProvider;
use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use mteu\Monitoring\Solr\Provider\DoctrineIndexQueueRepository;
use mteu\Monitoring\Solr\Provider\HttpSolrClient;
use mteu\Monitoring\Solr\Provider\IndexQueueRepository;
use mteu\Monitoring\Solr\Provider\SiteConfigurationSolrConnectionProvider;
use mteu\Monitoring\Solr\Provider\SolrClient;
use mteu\Monitoring\Solr\Provider\SolrConnectionProvider;
use mteu\Monitoring\Solr\Provider\SolrProvider;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\Test;

/**
 * ContainerIntegrationTest.
 *
 * The point of shipping this provider as its own extension is that EXT:monitoring
 * discovers it without either side knowing about the other. Everything that makes
 * that work lives in configuration rather than in code — this extension's
 * Services.yaml, the `#[AsAlias]` attributes on the implementations, and the typed
 * extension configuration bound to this extension's own key — so none of it is
 * covered by a unit test. This is where it gets exercised.
 *
 * What it cannot assert directly is the `monitoring.provider` tag itself: the
 * compiled container does not expose tags at runtime, and reaching for the host's
 * collecting services would bind these tests to classes EXT:monitoring marks
 * `@internal`. Resolving the provider through the container covers the part that
 * actually breaks in practice.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversNothing]
final class ContainerIntegrationTest extends MonitoringSolrFunctionalTestCase
{
    #[Test]
    public function providerIsResolvableWithAllDependenciesAutowiredAcrossExtensions(): void
    {
        $provider = $this->get(SolrProvider::class);

        self::assertInstanceOf(SolrProvider::class, $provider);
        self::assertInstanceOf(MonitoringProvider::class, $provider);
    }

    #[Test]
    public function interfacesResolveToThisExtensionsImplementations(): void
    {
        // Each of these is bound by an #[AsAlias] attribute rather than by a
        // Services.yaml entry, so a lost attribute fails here and nowhere else.
        self::assertInstanceOf(
            SiteConfigurationSolrConnectionProvider::class,
            $this->get(SolrConnectionProvider::class),
        );
        self::assertInstanceOf(HttpSolrClient::class, $this->get(SolrClient::class));
        self::assertInstanceOf(DoctrineIndexQueueRepository::class, $this->get(IndexQueueRepository::class));
    }

    #[Test]
    public function typedExtensionConfigurationBindsToThisExtensionsKey(): void
    {
        // Reads `monitoring_solr`, not the host extension's `monitoring`. A wrong
        // key silently yields the declared defaults, which is indistinguishable
        // from working until an operator changes a setting and nothing happens.
        $configuration = $this->get(SolrProviderConfiguration::class);

        self::assertInstanceOf(SolrProviderConfiguration::class, $configuration);
        self::assertTrue($configuration->isEnabled());
        self::assertSame(5, $configuration->timeout);
    }
}
