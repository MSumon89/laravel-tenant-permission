<?php

namespace Smk\Permission\Tests;

/**
 * The UI routes are registered at boot, so they must be enabled in the
 * environment rather than inside a test.
 */
abstract class UiTestCase extends TestCase
{
    protected function defineEnvironment($app): void
    {
        parent::defineEnvironment($app);

        $app['config']->set('permission.ui.enabled', true);
    }
}
