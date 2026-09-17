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

namespace mteu\Monitoring\Solr\Tests\Unit\Provider;

use mteu\Monitoring\Result\Result;
use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use mteu\Monitoring\Solr\Provider\SolrConfigurationIssue;
use mteu\Monitoring\Solr\Provider\SolrConfigurationProblem;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrConnectionSet;
use mteu\Monitoring\Solr\Provider\SolrProvider;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\IndexQueueRepositoryStub;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\Language\XliffLanguageServiceFactoryTrait;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrClientSpy;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrConnectionProviderStub;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;

/**
 * SolrProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SolrProvider::class)]
#[Framework\Attributes\UsesClass(SolrConnection::class)]
#[Framework\Attributes\UsesClass(SolrConnectionSet::class)]
#[Framework\Attributes\UsesClass(SolrConfigurationProblem::class)]
final class SolrProviderTest extends Framework\TestCase
{
    use XliffLanguageServiceFactoryTrait;

    private const string ROOT_URI = 'http://localhost:8983/solr';
    private const string HOST_PROBE = self::ROOT_URI . '/admin/info/system';
    private const string CORE_PROBE = self::ROOT_URI . '/core_en/admin/ping';

    #[Test]
    public function getName(): void
    {
        self::assertSame('Solr', $this->createProvider()->getName());
    }

    #[Test]
    public function getDescriptionMentionsSolr(): void
    {
        self::assertStringContainsString('Solr', $this->createProvider()->getDescription());
    }

    #[Test]
    public function isEnabledReturnsTrueByDefault(): void
    {
        self::assertTrue($this->createProvider()->isEnabled());
    }

    #[Test]
    public function isEnabledReflectsConfiguration(): void
    {
        self::assertFalse(
            $this->createProvider(configuration: new SolrProviderConfiguration(enabled: false))->isEnabled(),
        );
    }

    #[Test]
    public function reportsHealthyWhenHostAndCoreAreReachableAndNoIndexingErrors(): void
    {
        $result = $this->createProvider()->execute();

        self::assertSame(Status::Healthy, $result->getStatus());
        self::assertTrue($result->isHealthy());

        self::assertSame('Host', $result->getSubResults()[0]->getName());
        self::assertSame('Cores', $result->getSubResults()[1]->getName());
        self::assertSame('Indexing Errors', $result->getSubResults()[2]->getName());
    }

    #[Test]
    public function reportsUnhealthyWhenHostIsUnreachable(): void
    {
        $result = $this->createProvider(unreachableUrls: [self::HOST_PROBE])->execute();

        self::assertSame(Status::Unhealthy, $result->getStatus());

        $host = $result->getSubResults()[0];
        self::assertSame('Host', $host->getName());
        self::assertSame(Status::Unhealthy, $host->getStatus());
    }

    #[Test]
    public function eachDistinctNodeIsProbedOnlyOnce(): void
    {
        // Two connections (two cores) on the same node share one host probe.
        $client = new SolrClientSpy();

        $this->createProvider(
            connections: [
                new SolrConnection('main / en (core_en)', self::ROOT_URI, 'core_en'),
                new SolrConnection('main / de (core_de)', self::ROOT_URI, 'core_de'),
            ],
            client: $client,
        )->execute();

        self::assertSame(1, array_count_values($client->getProbedUrls())[self::HOST_PROBE]);
    }

    #[Test]
    public function skipsCoreProbeWhenHostIsUnreachable(): void
    {
        $client = new SolrClientSpy([self::HOST_PROBE]);

        $result = $this->createProvider(client: $client)->execute();

        // The core was never probed because its node was already down.
        self::assertNotContains(self::CORE_PROBE, $client->getProbedUrls());

        $cores = $result->getSubResults()[1];
        self::assertSame('Cores', $cores->getName());
        self::assertSame([], $cores->getSubResults());
        self::assertStringContainsString('No cores', (string)$cores->getReason());
    }

    #[Test]
    public function reportsUnhealthyWhenAConfiguredCoreIsUnreachable(): void
    {
        $result = $this->createProvider(unreachableUrls: [self::CORE_PROBE])->execute();

        self::assertSame(Status::Unhealthy, $result->getStatus());

        $cores = $result->getSubResults()[1];
        self::assertSame('Cores', $cores->getName());
        self::assertSame(Status::Unhealthy, $cores->getStatus());
    }

    #[Test]
    public function coresStayHealthyWhenConnectionHasNoCore(): void
    {
        $result = $this->createProvider(
            connections: [new SolrConnection('main / en', self::ROOT_URI, '')],
        )->execute();

        $cores = $result->getSubResults()[1];
        self::assertSame(Status::Healthy, $cores->getStatus());
        self::assertStringContainsString('No cores', (string)$cores->getReason());
    }

    #[Test]
    public function reportsDegradedOnIndexingErrorsByDefault(): void
    {
        $result = $this->createProvider(indexingErrorCount: 3)->execute();

        // Degraded is not an outage: the aggregate stays a non-failing 200.
        self::assertSame(Status::Degraded, $result->getStatus());
        self::assertTrue($result->isHealthy());

        $indexing = $result->getSubResults()[2];
        self::assertSame(Status::Degraded, $indexing->getStatus());
        self::assertStringContainsString('3 index queue items', (string)$indexing->getReason());
    }

    #[Test]
    public function indexingErrorSeverityCanBeRaisedToUnhealthy(): void
    {
        $result = $this->createProvider(
            configuration: new SolrProviderConfiguration(indexingErrorSeverity: Status::Unhealthy),
            indexingErrorCount: 1,
        )->execute();

        $indexing = $result->getSubResults()[2];
        self::assertSame(Status::Unhealthy, $indexing->getStatus());
        self::assertFalse($result->isHealthy());
        self::assertStringContainsString('1 index queue item ', (string)$indexing->getReason());
    }

    #[Test]
    public function indexingErrorReasonListsAffectedItems(): void
    {
        $result = $this->createProvider(
            indexingErrorCount: 1,
            indexingErrorSample: ['pages:42'],
        )->execute();

        self::assertStringContainsString('<li>pages:42</li>', (string)$result->getSubResults()[2]->getReason());
    }

    #[Test]
    public function indexQueueFailureIsReportedAsUnhealthy(): void
    {
        $result = $this->createProvider(
            indexQueueException: new \RuntimeException('table missing'),
        )->execute();

        $indexing = $result->getSubResults()[2];
        self::assertSame(Status::Unhealthy, $indexing->getStatus());
        self::assertStringContainsString('table missing', (string)$indexing->getReason());
        self::assertFalse($result->isHealthy());
    }

    #[Test]
    public function unhealthyResultCarriesTopLevelReason(): void
    {
        $result = $this->createProvider(unreachableUrls: [self::HOST_PROBE])->execute();

        self::assertStringContainsString('One or more Solr health checks failed', (string)$result->getReason());
    }

    #[Test]
    public function reportsUnusableConfigurationAsUnhealthy(): void
    {
        $result = $this->createProvider(
            problems: [
                new SolrConfigurationProblem(
                    SolrConfigurationIssue::InvalidWhitespace,
                    'main / German',
                    'solr_port_read',
                    "\u{a0}8983",
                ),
            ],
        )->execute();

        $configuration = $result->getSubResults()[0];

        self::assertSame('Configuration', $configuration->getName());
        self::assertSame(Status::Unhealthy, $configuration->getStatus());
        self::assertStringContainsString('solr_port_read', (string)$configuration->getReason());
        self::assertStringContainsString('main / German', (string)$configuration->getReason());
        self::assertFalse($result->isHealthy());
    }

    #[Test]
    public function reportsConfigurationProblemsEvenWhenNoConnectionCouldBeResolved(): void
    {
        // The toggle itself was unreadable, so there is nothing to probe. Reporting
        // only the probes would turn a broken Solr setup into silence.
        $result = $this->createProvider(
            connections: [],
            problems: [
                new SolrConfigurationProblem(
                    SolrConfigurationIssue::NotBoolean,
                    'main / English',
                    'solr_enabled_read',
                    'maybe',
                ),
            ],
        )->execute();

        $names = array_map(static fn(Result $sub): string => $sub->getName(), $result->getSubResults());

        self::assertSame(['Configuration', 'Indexing Errors'], $names);
        self::assertFalse($result->isHealthy());
    }

    #[Test]
    public function omitsTheConfigurationSubResultWhenEverythingReads(): void
    {
        $names = array_map(
            static fn(Result $sub): string => $sub->getName(),
            $this->createProvider()->execute()->getSubResults(),
        );

        self::assertSame(['Host', 'Cores', 'Indexing Errors'], $names);
    }

    #[Test]
    public function languagesSharingACoreCostOneProbeButKeepTheirOwnSubResult(): void
    {
        // A core declared once for the whole site resolves to the same probe URI
        // for every language.
        $client = new SolrClientSpy();

        $result = $this->createProvider(
            connections: [
                new SolrConnection('main / English (core_shared)', self::ROOT_URI, 'core_shared'),
                new SolrConnection('main / German (core_shared)', self::ROOT_URI, 'core_shared'),
            ],
            client: $client,
        )->execute();

        $probe = self::ROOT_URI . '/core_shared/admin/ping';

        self::assertSame(1, array_count_values($client->getProbedUrls())[$probe]);
        self::assertCount(2, $result->getSubResults()[1]->getSubResults());
    }

    /**
     * @param list<SolrConnection>|null $connections
     * @param list<string> $unreachableUrls
     * @param list<string> $indexingErrorSample
     * @param list<SolrConfigurationProblem> $problems
     */
    private function createProvider(
        ?SolrProviderConfiguration $configuration = null,
        ?array $connections = null,
        array $unreachableUrls = [],
        ?SolrClientSpy $client = null,
        int $indexingErrorCount = 0,
        array $indexingErrorSample = [],
        ?\Throwable $indexQueueException = null,
        array $problems = [],
    ): SolrProvider {
        return new SolrProvider(
            $configuration ?? new SolrProviderConfiguration(),
            new SolrConnectionProviderStub(
                $connections ?? [new SolrConnection('main / English (core_en)', self::ROOT_URI, 'core_en')],
                $problems,
            ),
            $client ?? new SolrClientSpy($unreachableUrls),
            new IndexQueueRepositoryStub($indexingErrorCount, $indexingErrorSample, $indexQueueException),
            new NullLogger(),
            $this->createEnglishLanguageServiceFactory(),
        );
    }
}
