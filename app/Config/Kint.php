<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Streamlined Kint configuration without external dev dependencies.
 */
class Kint extends BaseConfig
{
    public $plugins;
    public int $maxDepth           = 6;
    public bool $displayCalledFrom = false;
    public bool $expanded          = false;
    public string $richTheme       = 'aante-light.css';
    public bool $richFolder        = false;
    public $richObjectPlugins;
    public $richTabPlugins;
    public bool $cliColors         = true;
    public bool $cliForceUTF8      = false;
    public bool $cliDetectWidth    = true;
    public int $cliMinWidth        = 40;
}
