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

use mteu\Monitoring\Solr\Provider\SolrConfigurationIssue;
use mteu\Monitoring\Solr\Provider\SolrConfigurationProblem;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrConnectionSet;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * SolrConnectionSetTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SolrConnectionSet::class)]
#[Framework\Attributes\UsesClass(SolrConnection::class)]
#[Framework\Attributes\UsesClass(SolrConfigurationProblem::class)]
final class SolrConnectionSetTest extends Framework\TestCase
{
    #[Test]
    public function isEmptyWithNeitherConnectionNorProblem(): void
    {
        self::assertTrue((new SolrConnectionSet())->isEmpty());
    }

    #[Test]
    public function isNotEmptyWhenOnlyAProblemWasFound(): void
    {
        // A site whose configuration cannot be read has nothing to probe but
        // plenty to report, so it must not count as "no Solr here".
        self::assertFalse((new SolrConnectionSet([], [self::problem()]))->isEmpty());
    }

    #[Test]
    public function fingerprintIsAValidCacheIdentifier(): void
    {
        self::assertMatchesRegularExpression(
            '/^[a-zA-Z0-9_%\-&]+$/',
            self::set()->fingerprint(),
        );
    }

    #[Test]
    public function fingerprintIsStableAcrossEqualSets(): void
    {
        self::assertSame(self::set()->fingerprint(), self::set()->fingerprint());
    }

    #[Test]
    public function emptySetHasAFingerprint(): void
    {
        self::assertNotSame('', (new SolrConnectionSet())->fingerprint());
    }

    #[Test]
    #[Framework\Attributes\DataProvider('setsDifferingFromTheReferenceSetProvider')]
    public function fingerprintChangesWithTheSiteConfiguration(SolrConnectionSet $changed): void
    {
        self::assertNotSame(self::set()->fingerprint(), $changed->fingerprint());
    }

    /**
     * @return \Generator<string, array{SolrConnectionSet}>
     */
    public static function setsDifferingFromTheReferenceSetProvider(): \Generator
    {
        yield 'changed host' => [
            new SolrConnectionSet(
                [new SolrConnection('main / English (core_en)', 'http://solr:8983/solr', 'core_en')],
                [self::problem()],
            ),
        ];

        yield 'changed core' => [
            new SolrConnectionSet(
                [new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_de')],
                [self::problem()],
            ),
        ];

        yield 'changed label' => [
            new SolrConnectionSet(
                [new SolrConnection('main / German (core_en)', 'http://localhost:8983/solr', 'core_en')],
                [self::problem()],
            ),
        ];

        yield 'added connection' => [
            new SolrConnectionSet(
                [
                    new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_en'),
                    new SolrConnection('main / German (core_de)', 'http://localhost:8983/solr', 'core_de'),
                ],
                [self::problem()],
            ),
        ];

        yield 'resolved problem' => [
            new SolrConnectionSet(
                [new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_en')],
            ),
        ];

        yield 'different problem issue' => [
            new SolrConnectionSet(
                [new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_en')],
                [new SolrConfigurationProblem(
                    SolrConfigurationIssue::NotBoolean,
                    'main / German',
                    'solr_port_read',
                    '8983 ',
                )],
            ),
        ];
    }

    #[Test]
    public function fingerprintDistinguishesValuesThatOnlyDifferInFieldBoundaries(): void
    {
        // Naive concatenation would collapse these two into one fingerprint and
        // serve a cached verdict about a node that is not being probed.
        $first = new SolrConnectionSet([new SolrConnection('a', 'http://solr/solr', 'bc')]);
        $second = new SolrConnectionSet([new SolrConnection('a', 'http://solr/solrb', 'c')]);

        self::assertNotSame($first->fingerprint(), $second->fingerprint());
    }

    private static function set(): SolrConnectionSet
    {
        return new SolrConnectionSet(
            [new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_en')],
            [self::problem()],
        );
    }

    private static function problem(): SolrConfigurationProblem
    {
        return new SolrConfigurationProblem(
            SolrConfigurationIssue::InvalidWhitespace,
            'main / German',
            'solr_port_read',
            '8983 ',
        );
    }
}
