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
 * Why a value in the site configuration could not be read as intended.
 *
 * The backing value doubles as the suffix of the `provider.solr.configuration.*`
 * label used to render the problem.
 *
 * @author Martin Adler <mteu@mailbox.org>
 * @license GPL-2.0-or-later
 */
enum SolrConfigurationIssue: string
{
    /**
     * The value carries whitespace that PHP's `trim()` does not strip, most
     * notably a non-breaking space. EXT:solr casts such a value to int or bool
     * and silently ends up with 0 or false.
     */
    case InvalidWhitespace = 'invalidWhitespace';

    case NotNumeric = 'notNumeric';

    case NotBoolean = 'notBoolean';
}
