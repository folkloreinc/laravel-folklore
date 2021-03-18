<?php

use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Orchestra\Testbench\TestCase;

class FolkloreTestCase extends TestCase {

    public function setUp()
    {
        parent::setUp();

        $this->app->instance('path.public', __DIR__.'/fixture');
    }

    protected function getPackageProviders($app)
    {
        return array('Folklore\ServiceProvider');
    }

    protected function getPackageAliases($app)
    {
        return array(
            'Folklore' => 'Folklore\Facade'
        );
    }

}
