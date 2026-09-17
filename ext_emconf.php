<?php

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

/** @noinspection PhpUndefinedVariableInspection */
$EM_CONF[$_EXTKEY] = [
    'title' => 'Monitoring: Apache Solr',
    'description' => 'Reports the health of the Apache Solr connections configured for EXT:solr as a provider for EXT:monitoring',
    'category' => 'be',
    'version' => '0.1.0',
    'state' => 'beta',
    'author' => 'Martin Adler',
    'author_email' => 'mteu@mailbox.org',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.23-14.3.99',
            'php' => '8.3.0-8.5.99',
            'monitoring' => '1.0.0-1.99.99',
            'typed_extconf' => '1.3.0-1.99.99',
        ],
        'suggests' => [
            'solr' => '',
        ],
    ],
];
