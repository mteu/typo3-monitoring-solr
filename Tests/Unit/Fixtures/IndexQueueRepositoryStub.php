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

use mteu\Monitoring\Solr\Provider\IndexQueueRepository;

/**
 * IndexQueueRepositoryStub.
 *
 * Returns preconfigured counts and samples, or throws a preconfigured
 * exception, to drive the indexing-errors branch under test.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
final readonly class IndexQueueRepositoryStub implements IndexQueueRepository
{
    /**
     * @param list<string> $errorSample
     */
    public function __construct(
        private int $errorCount = 0,
        private array $errorSample = [],
        private ?\Throwable $throwOnCount = null,
    ) {}

    public function countIndexingErrors(): int
    {
        if ($this->throwOnCount !== null) {
            throw $this->throwOnCount;
        }

        return $this->errorCount;
    }

    public function getIndexingErrorSample(int $limit): array
    {
        return array_slice($this->errorSample, 0, $limit);
    }
}
