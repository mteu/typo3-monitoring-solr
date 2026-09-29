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

use mteu\Monitoring\Handler\MonitoringExecutionHandler;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrConnectionSet;
use mteu\Monitoring\Solr\Provider\SolrProvider;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\IndexQueueRepositoryStub;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrClientSpy;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrConnectionProviderStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * SolrProviderCachingTest.
 *
 * The point of making this provider cacheable is that a polled health endpoint
 * stops paying a network probe per node and per core on every single request.
 * That only holds if the host extension's execution handler actually finds the
 * entry the provider's key points at, which no unit test of `getCacheKey()` can
 * show — so it is exercised here, against the real cache.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(SolrProvider::class)]
#[UsesClass(SolrConnection::class)]
#[UsesClass(SolrConnectionSet::class)]
final class SolrProviderCachingTest extends MonitoringSolrFunctionalTestCase
{
    private const string ROOT_URI = 'http://localhost:8983/solr';

    #[Test]
    public function secondExecutionIsServedFromCacheWithoutProbingSolrAgain(): void
    {
        $client = new SolrClientSpy();
        $handler = $this->get(MonitoringExecutionHandler::class);
        self::assertInstanceOf(MonitoringExecutionHandler::class, $handler);

        $provider = $this->createSolrProvider($client);

        $handler->executeProvider($provider);
        $probesAfterFirstRun = $client->getProbedUrls();

        $handler->executeProvider($provider);

        self::assertNotSame([], $probesAfterFirstRun);
        self::assertSame($probesAfterFirstRun, $client->getProbedUrls());
    }

    #[Test]
    public function cachedResultKeepsItsVerdict(): void
    {
        $handler = $this->get(MonitoringExecutionHandler::class);
        self::assertInstanceOf(MonitoringExecutionHandler::class, $handler);

        $provider = $this->createSolrProvider(
            new SolrClientSpy([self::ROOT_URI . '/admin/info/system']),
        );

        $handler->executeProvider($provider);

        self::assertSame(Status::Unhealthy, $handler->executeProvider($provider)->getStatus());
    }

    #[Test]
    public function aChangedSiteConfigurationIsProbedInsteadOfReadFromCache(): void
    {
        // An operator who fixes a broken connection must not keep reading the
        // verdict about the connection they just replaced.
        $handler = $this->get(MonitoringExecutionHandler::class);
        self::assertInstanceOf(MonitoringExecutionHandler::class, $handler);

        $handler->executeProvider($this->createSolrProvider(new SolrClientSpy()));

        $clientAfterConfigurationChange = new SolrClientSpy();
        $handler->executeProvider(
            $this->createSolrProvider(
                $clientAfterConfigurationChange,
                [new SolrConnection('main / English (core_en)', 'http://solr:8983/solr', 'core_en')],
            ),
        );

        self::assertNotSame([], $clientAfterConfigurationChange->getProbedUrls());
    }

    /**
     * @param list<SolrConnection>|null $connections
     */
    private function createSolrProvider(SolrClientSpy $client, ?array $connections = null): SolrProvider
    {
        return new SolrProvider(
            new SolrProviderConfiguration(),
            new SolrConnectionProviderStub(
                $connections ?? [new SolrConnection('main / English (core_en)', self::ROOT_URI, 'core_en')],
            ),
            $client,
            new IndexQueueRepositoryStub(),
            new NullLogger(),
            $this->get(LanguageServiceFactory::class),
        );
    }
}
