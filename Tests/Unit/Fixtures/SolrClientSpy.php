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

use mteu\Monitoring\Solr\Provider\SolrClient;

/**
 * SolrClientSpy.
 *
 * Records every probed URL (exposed via {@see getProbedUrls()} for assertions)
 * and treats any URL listed as unreachable as down; every other URL is
 * reachable.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final class SolrClientSpy implements SolrClient
{
    /**
     * @var list<string>
     */
    private array $probedUrls = [];

    /**
     * @param list<string> $unreachableUrls
     */
    public function __construct(
        private readonly array $unreachableUrls = [],
    ) {}

    public function isReachable(string $url): bool
    {
        $this->probedUrls[] = $url;

        return !in_array($url, $this->unreachableUrls, true);
    }

    /**
     * @return list<string>
     */
    public function getProbedUrls(): array
    {
        return $this->probedUrls;
    }
}
