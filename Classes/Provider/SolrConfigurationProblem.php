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
 * A single unusable value found in the Solr site configuration.
 *
 * Reported instead of quietly falling back to a default: a default would make
 * the probe test something the production setup does not use, which is exactly
 * the drift this provider exists to catch.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class SolrConfigurationProblem
{
    /**
     * @param non-empty-string $origin Human-readable location, e.g. "main / German".
     * @param non-empty-string $key    Site configuration key, e.g. "solr_port_read".
     * @param string           $value  The offending value as configured.
     */
    public function __construct(
        public SolrConfigurationIssue $issue,
        public string $origin,
        public string $key,
        public string $value,
    ) {}
}
