<?php

declare(strict_types=1);

namespace ProjectReviews\Tests;

use PHPUnit\Framework\TestCase;

final class PluginBootstrapTest extends TestCase
{
    public function test_plugin_constants_defined_when_stub_loaded(): void
    {
        require_once dirname(__DIR__) . '/project-reviews.php';
        $this->assertTrue(defined('PR_PLUGIN_VERSION'));
        $this->assertSame('project-reviews', PR_PLUGIN_SLUG);
        $this->assertTrue(defined('PR_PLUGIN_DIR'));
        $this->assertTrue(defined('PR_PLUGIN_FILE'));
    }
}
