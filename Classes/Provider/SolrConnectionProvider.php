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
 * Supplies the Solr read connections that should be probed for health.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
interface SolrConnectionProvider
{
    /**
     * Resolves every Solr read connection that should be probed, together with
     * the configuration values that could not be read.
     */
    public function resolveConnections(): SolrConnectionSet;
}
