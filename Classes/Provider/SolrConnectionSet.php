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

namespace mteu\Monitoring\Solr\Provider;

/**
 * The outcome of reading Solr read connections from the site configuration:
 * the connections that could be resolved, plus the values that could not.
 *
 * Both halves matter. A site whose configuration is unreadable yields no
 * connection, and reporting only the connections would turn a broken setup
 * into silence.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrConnectionSet
{
    /**
     * @param list<SolrConnection>          $connections
     * @param list<SolrConfigurationProblem> $problems
     */
    public function __construct(
        public array $connections = [],
        public array $problems = [],
    ) {}

    /**
     * True when the site configuration yielded neither a connection to probe
     * nor a problem worth reporting — there is genuinely no Solr to monitor.
     */
    public function isEmpty(): bool
    {
        return $this->connections === [] && $this->problems === [];
    }
}
