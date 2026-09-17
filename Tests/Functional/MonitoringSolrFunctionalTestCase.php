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

use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

/**
 * MonitoringSolrFunctionalTestCase.
 *
 * Loads this extension together with the host extension it plugs into, so the
 * tests exercise the same container wiring an installation gets.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
abstract class MonitoringSolrFunctionalTestCase extends FunctionalTestCase
{
    protected array $testExtensionsToLoad = [
        'monitoring',
        'monitoring_solr',
        'typed_extconf',
    ];
}
