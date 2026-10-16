<?php

namespace Config;

use CodeIgniter\Config\View as BaseView;

class View extends BaseView
{
    /**
     * When false, the view method will clear the data between requests so you
     * won't have to manually do it each time.
     *
     * @var bool
     */
    public bool $saveData = true;

    /**
     * Parser Filters map a filter name with any sort of callable.
     *
     * @var array<string, string>
     */
    public array $filters = [];

    /**
     * Parser Plugins provide a way to extend the functionality provided
     * by the core parser by adding functions that can be called in the
     * template.
     *
     * @var array<string, string>
     */
    public array $plugins = [];
}
