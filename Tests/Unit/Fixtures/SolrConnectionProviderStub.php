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

namespace mteu\Monitoring\Solr\Tests\Unit\Fixtures;

use mteu\Monitoring\Solr\Provider\SolrConfigurationProblem;
use mteu\Monitoring\Solr\Provider\SolrConnection;
use mteu\Monitoring\Solr\Provider\SolrConnectionProvider;
use mteu\Monitoring\Solr\Provider\SolrConnectionSet;

/**
 * SolrConnectionProviderStub.
 *
 * Returns a preconfigured set of connections and configuration problems to drive
 * the provider under test.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrConnectionProviderStub implements SolrConnectionProvider
{
    /**
     * @param list<SolrConnection> $connections
     * @param list<SolrConfigurationProblem> $problems
     */
    public function __construct(
        private array $connections = [],
        private array $problems = [],
    ) {}

    public function resolveConnections(): SolrConnectionSet
    {
        return new SolrConnectionSet($this->connections, $this->problems);
    }
}
