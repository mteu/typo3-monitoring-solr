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

use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrProvider;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\IndexQueueRepositoryStub;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrClientSpy;
use mteu\Monitoring\Solr\Tests\Unit\Fixtures\SolrConnectionProviderStub;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\NullLogger;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;

/**
 * SolrProviderInactiveTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[CoversClass(SolrProvider::class)]
final class SolrProviderInactiveTest extends MonitoringSolrFunctionalTestCase
{
    #[Test]
    public function isInactiveWhenNoSolrConnectionIsConfigured(): void
    {
        // No site configuration / root page → no connections to probe. The
        // provider must report inactive instead of a misleading "healthy".
        self::assertFalse($this->createSolrProvider([])->isActive());
    }

    #[Test]
    public function isInactiveWhenSolrExtensionIsNotLoaded(): void
    {
        // Even with a connection present, the unavailable solr extension keeps
        // the provider inactive in this environment.
        self::assertFalse(
            $this->createSolrProvider([
                new SolrConnection('main / English (core_en)', 'http://localhost:8983/solr', 'core_en'),
            ])->isActive(),
        );
    }

    /**
     * @param list<SolrConnection> $connections
     */
    private function createSolrProvider(array $connections): SolrProvider
    {
        return new SolrProvider(
            new SolrProviderConfiguration(enabled: true),
            new SolrConnectionProviderStub($connections),
            new SolrClientSpy(),
            new IndexQueueRepositoryStub(),
            new NullLogger(),
            $this->get(LanguageServiceFactory::class),
        );
    }
}
