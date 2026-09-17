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

namespace mteu\Monitoring\Solr\Tests\Unit\Configuration;

use mteu\Monitoring\Result\Status;
use mteu\Monitoring\Solr\Configuration\SolrProviderConfiguration;
use PHPUnit\Framework;
use PHPUnit\Framework\Attributes\Test;

/**
 * SolrProviderConfigurationTest.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
#[Framework\Attributes\CoversClass(SolrProviderConfiguration::class)]
final class SolrProviderConfigurationTest extends Framework\TestCase
{
    #[Test]
    public function providerIsEnabledByDefault(): void
    {
        // Installing this extension is the opt-in — unlike a provider bundled
        // with EXT:monitoring, which every installation would carry.
        self::assertTrue((new SolrProviderConfiguration())->isEnabled());
    }

    #[Test]
    public function canBeDisabled(): void
    {
        self::assertFalse((new SolrProviderConfiguration(enabled: false))->isEnabled());
    }

    #[Test]
    public function timeoutDefaultsToFiveSeconds(): void
    {
        self::assertSame(5, (new SolrProviderConfiguration())->timeout);
    }

    #[Test]
    public function indexingErrorSeverityDefaultsToDegraded(): void
    {
        self::assertSame(
            Status::Degraded,
            (new SolrProviderConfiguration())->indexingErrorSeverity,
        );
    }
}
