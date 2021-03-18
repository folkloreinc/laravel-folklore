<?php

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Orchestra\Testbench\TestCase;

class FolkloreTestCase extends TestCase
{
    public function setUp()
    {
        parent::setUp();

        $this->app->instance('path.public', __DIR__ . '/fixture');
    }

    protected function getPackageProviders($app)
    {
        return ['Folklore\ServiceProvider'];
    }
}
