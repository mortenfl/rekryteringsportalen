<?php
/**
 * Unit test bootstrap. The tested classes are pure PHP and need no WordPress.
 *
 * @package RekryteringsportalenCore
 */

require_once dirname( __DIR__, 3 ) . '/vendor/autoload.php';
require_once dirname( __DIR__ ) . '/includes/class-rp-rating-math.php';
require_once dirname( __DIR__ ) . '/includes/class-rp-normalizer.php';
require_once dirname( __DIR__ ) . '/includes/class-rp-lead-matcher.php';

// The XLS import add-on's pure classes are tested in the same run.
require_once dirname( __DIR__, 2 ) . '/rekryteringsportalen-xls-import/tests/bootstrap.php';
