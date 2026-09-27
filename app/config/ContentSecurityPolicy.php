<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Minimal ContentSecurityPolicy config stub for API responses.
 * CSP header generation is disabled by default via App::$CSPEnabled = false.
 */
class ContentSecurityPolicy extends BaseConfig
{
    public bool $reportOnly = false;
}
