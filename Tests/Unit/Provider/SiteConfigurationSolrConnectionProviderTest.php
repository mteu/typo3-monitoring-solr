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

use mteu\Monitoring\Solr\Provider\SiteConfigurationSolrConnectionProvider;
use mteu\Monitoring\Solr\Provider\SolrConfigurationIssue;
use mteu\Monitoring\Solr\Provider\SolrConfigurationProblem;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrConnectionSet;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;

/**
 * SiteConfigurationSolrConnectionProviderTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SiteConfigurationSolrConnectionProvider::class)]
#[Framework\Attributes\UsesClass(SolrConnection::class)]
#[Framework\Attributes\UsesClass(SolrConnectionSet::class)]
#[Framework\Attributes\UsesClass(SolrConfigurationProblem::class)]
final class SiteConfigurationSolrConnectionProviderTest extends Framework\TestCase
{
    #[Test]
    public function buildsConnectionFromAnEnabledSiteLanguage(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_scheme_read' => 'https',
                    'solr_host_read' => 'solr.example.com',
                    'solr_port_read' => 8984,
                    'solr_path_read' => '/solr/',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertCount(1, $connections);
        self::assertSame('https://solr.example.com:8984/solr', $connections[0]->rootUri);
        self::assertSame('core_en', $connections[0]->core);
        self::assertStringContainsString('core_en', $connections[0]->label);
        self::assertSame('https://solr.example.com:8984/solr/core_en/admin/ping', $connections[0]->coreProbeUri());
    }

    #[Test]
    public function skipsLanguagesWhereTheReadConnectionIsDisabled(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => false,
                    'solr_host_read' => 'solr.example.com',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertSame([], $connections);
    }

    #[Test]
    public function skipsLanguagesWithoutAHost(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => '',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertSame([], $connections);
    }

    #[Test]
    public function fallsBackToDefaultSchemePortAndPathWhenNotConfigured(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => '1',
                    'solr_host_read' => 'localhost',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertSame('http://localhost:8983/solr', $connections[0]->rootUri);
    }

    #[Test]
    public function collectsConnectionsAcrossSitesAndLanguages(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_core_read' => 'core_en',
                ]),
                $this->language(1, 'German', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_core_read' => 'core_de',
                ]),
            ]),
            $this->createSite('blog', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_core_read' => 'core_blog',
                ]),
            ]),
        ])->resolveConnections()->connections;

        $cores = array_map(static fn(SolrConnection $c): string => $c->core, $connections);
        self::assertSame(['core_en', 'core_de', 'core_blog'], $cores);
    }

    #[Test]
    public function readsConnectionSettingsDeclaredOnceForTheWholeSite(): void
    {
        // The layout EXT:solr documents: shared settings on the site, only the
        // parts that actually differ on the language.
        $connections = $this->createProvider([
            $this->createSite(
                'main',
                [
                    $this->language(0, 'English', ['solr_host_read' => 'solr-host-1', 'solr_core_read' => 'core_en']),
                    $this->language(1, 'German', ['solr_host_read' => 'solr-host-2', 'solr_core_read' => 'core_de']),
                ],
                [
                    'solr_enabled_read' => true,
                    'solr_scheme_read' => 'https',
                    'solr_port_read' => 8443,
                    'solr_path_read' => '/',
                ],
            ),
        ])->resolveConnections()->connections;

        self::assertCount(2, $connections);
        self::assertSame('https://solr-host-1:8443/solr', $connections[0]->rootUri);
        self::assertSame('https://solr-host-2:8443/solr', $connections[1]->rootUri);
        self::assertSame('core_de', $connections[1]->core);
    }

    #[Test]
    public function languageSettingsOverrideTheSiteLevelOnes(): void
    {
        $connections = $this->createProvider([
            $this->createSite(
                'main',
                [$this->language(0, 'English', ['solr_port_read' => 8984, 'solr_core_read' => 'core_en'])],
                [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_port_read' => 8983,
                ],
            ),
        ])->resolveConnections()->connections;

        self::assertSame('http://solr:8984/solr', $connections[0]->rootUri);
    }

    #[Test]
    public function siteLevelReadConnectionCanBeDisabled(): void
    {
        $set = $this->createProvider([
            $this->createSite(
                'main',
                [$this->language(0, 'English', ['solr_core_read' => 'core_en'])],
                ['solr_enabled_read' => false, 'solr_host_read' => 'solr'],
            ),
        ])->resolveConnections();

        self::assertSame([], $set->connections);
        self::assertSame([], $set->problems);
        self::assertTrue($set->isEmpty());
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function pathConventionProvider(): \Generator
    {
        // Solr serves below /solr and Solarium appends the segment itself, so the
        // pre-EXT:solr-12 spelling and the current one must resolve identically.
        yield 'EXT:solr 12+ root path' => ['/', 'http://solr:8983/solr'];
        yield 'empty path' => ['', 'http://solr:8983/solr'];
        yield 'legacy path carrying the solr segment' => ['/solr/', 'http://solr:8983/solr'];
        yield 'legacy path without slashes' => ['solr', 'http://solr:8983/solr'];
        yield 'path behind a prefix' => ['/search/', 'http://solr:8983/search/solr'];
        yield 'path behind a prefix carrying the solr segment' => ['/search/solr/', 'http://solr:8983/search/solr'];
    }

    #[Test]
    #[Framework\Attributes\DataProvider('pathConventionProvider')]
    public function normalisesTheSolrPathSegmentAcrossConventions(string $configuredPath, string $expectedRootUri): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_path_read' => $configuredPath,
                    'solr_core_read' => 'core_de',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertSame($expectedRootUri, $connections[0]->rootUri);
        self::assertSame($expectedRootUri . '/core_de/admin/ping', $connections[0]->coreProbeUri());
    }

    #[Test]
    public function reportsAPortCarryingANonBreakingSpaceInsteadOfDefaultingSilently(): void
    {
        // EXT:solr casts this to int, lands on 0 and rejects the endpoint. Falling
        // back to the default port would probe a healthy Solr and report green
        // while the site cannot reach it at all.
        $set = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'German', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_port_read' => "\u{a0}8983",
                    'solr_core_read' => 'core_de',
                ]),
            ]),
        ])->resolveConnections();

        self::assertCount(1, $set->problems);
        self::assertSame(SolrConfigurationIssue::InvalidWhitespace, $set->problems[0]->issue);
        self::assertSame('solr_port_read', $set->problems[0]->key);
        self::assertSame('main / German', $set->problems[0]->origin);
        self::assertFalse($set->isEmpty());
    }

    #[Test]
    public function stillProbesTheCleanedValueSoTheReportCarriesMoreThanTheProblem(): void
    {
        $connections = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'German', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_port_read' => "\u{a0}8983",
                    'solr_core_read' => 'core_de',
                ]),
            ]),
        ])->resolveConnections()->connections;

        self::assertSame('http://solr:8983/solr', $connections[0]->rootUri);
    }

    #[Test]
    public function reportsAnEnabledFlagCarryingANonBreakingSpace(): void
    {
        // The same contamination on the toggle drops the connection entirely.
        // Without the problem the provider would simply go inactive.
        $set = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'German', [
                    'solr_enabled_read' => "\u{a0}1",
                    'solr_host_read' => 'solr',
                    'solr_core_read' => 'core_de',
                ]),
            ]),
        ])->resolveConnections();

        self::assertCount(1, $set->problems);
        self::assertSame(SolrConfigurationIssue::InvalidWhitespace, $set->problems[0]->issue);
        self::assertSame('solr_enabled_read', $set->problems[0]->key);
        self::assertFalse($set->isEmpty());
    }

    #[Test]
    public function reportsAPortThatIsNotANumberAtAll(): void
    {
        $set = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => true,
                    'solr_host_read' => 'solr',
                    'solr_port_read' => 'eight-nine-eight-three',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections();

        self::assertCount(1, $set->problems);
        self::assertSame(SolrConfigurationIssue::NotNumeric, $set->problems[0]->issue);
    }

    #[Test]
    public function reportsAnEnabledFlagThatIsNotABoolean(): void
    {
        $set = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => 'maybe',
                    'solr_host_read' => 'solr',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections();

        self::assertCount(1, $set->problems);
        self::assertSame(SolrConfigurationIssue::NotBoolean, $set->problems[0]->issue);
        self::assertSame([], $set->connections);
    }

    #[Test]
    public function acceptsCleanValuesWithoutReportingProblems(): void
    {
        $set = $this->createProvider([
            $this->createSite('main', [
                $this->language(0, 'English', [
                    'solr_enabled_read' => '1',
                    'solr_host_read' => 'solr',
                    'solr_port_read' => '8983',
                    'solr_core_read' => 'core_en',
                ]),
            ]),
        ])->resolveConnections();

        self::assertSame([], $set->problems);
        self::assertCount(1, $set->connections);
    }

    /**
     * @param list<Site> $sites
     */
    private function createProvider(array $sites): SiteConfigurationSolrConnectionProvider
    {
        $siteFinder = self::createStub(SiteFinder::class);
        $siteFinder->method('getAllSites')->willReturn($sites);

        return new SiteConfigurationSolrConnectionProvider($siteFinder, new NullLogger());
    }

    /**
     * @param list<array<string, mixed>> $languages
     * @param array<string, mixed> $siteConfiguration Site-level keys, as EXT:solr allows them to be declared once for every language.
     */
    private function createSite(string $identifier, array $languages, array $siteConfiguration = []): Site
    {
        return new Site($identifier, 1, array_merge(
            [
                'base' => 'https://example.com/',
                'languages' => $languages,
            ],
            $siteConfiguration,
        ));
    }

    /**
     * @param array<string, mixed> $solrConfiguration
     * @return array<string, mixed>
     */
    private function language(int $languageId, string $title, array $solrConfiguration): array
    {
        return array_merge(
            [
                'languageId' => $languageId,
                'title' => $title,
                'locale' => 'en_US.UTF-8',
                'base' => $languageId === 0 ? '/' : '/' . $languageId . '/',
            ],
            $solrConfiguration,
        );
    }
}
