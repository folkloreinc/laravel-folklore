<?php

namespace Folklore\Tests\Feature\Repositories;

use Folklore\Repositories\Users;
use Folklore\Tests\TestCase;
use Illuminate\Contracts\Database\Query\Expression;
use Illuminate\Support\Facades\DB;

class EntitiesOrderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
    }

    public function test_it_orders_by_a_column()
    {
        $users = $this->makeRepository();
        foreach (['Bob', 'Alice', 'Carol'] as $name) {
            $users->create(['name' => $name, 'email' => strtolower($name).'@example.com', 'password' => 'secret']);
        }

        $this->assertSame(['Alice', 'Bob', 'Carol'], $users->get(['order' => 'name'])->map->name()->all());
        $this->assertSame(
            ['Carol', 'Bob', 'Alice'],
            $users->get(['order' => 'name', 'order_direction' => 'desc'])->map->name()->all(),
        );
    }

    public function test_it_accepts_the_order_formats()
    {
        $users = $this->makeRepository();

        $this->assertSame([['name', 'asc']], $users->orders(['order' => 'name']));
        $this->assertSame([['name', 'desc']], $users->orders(['order' => 'name', 'order_direction' => 'DESC']));
        $this->assertSame([['name', 'desc']], $users->orders(['order' => ['name', 'desc']]));
        $this->assertSame([['name', 'asc']], $users->orders(['order' => ['name']]));
        $this->assertSame(
            [['name', 'desc'], ['email', 'asc']],
            $users->orders(['order' => [['name', 'desc'], ['email']]]),
        );
    }

    public function test_an_invalid_direction_falls_back_to_ascending()
    {
        $users = $this->makeRepository();

        $this->assertSame([['name', 'asc']], $users->orders(['order' => 'name', 'order_direction' => 'sideways']));
        $this->assertSame([['name', 'asc']], $users->orders(['order' => ['name', 'up']]));
    }

    public function test_it_refuses_the_hidden_columns_of_the_model()
    {
        $users = $this->makeRepository();

        foreach (['password', 'PASSWORD', ' password ', 'users.password', '`password`', 'remember_token'] as $column) {
            $this->assertSame([], $users->orders(['order' => $column]), $column);
        }
        $this->assertSame([], $users->orders(['order' => ['password', 'desc']]));
        $this->assertSame(
            [['name', 'asc']],
            $users->orders(['order' => [['password', 'desc'], ['name', 'asc']]]),
        );
    }

    public function test_it_accepts_the_hidden_timestamps()
    {
        $users = $this->makeRepository();

        $this->assertSame([['created_at', 'desc']], $users->orders(['order' => 'created_at', 'order_direction' => 'desc']));
        $this->assertSame([['updated_at', 'asc']], $users->orders(['order' => 'updated_at']));
    }

    public function test_it_refuses_json_paths_into_an_unorderable_column()
    {
        $users = $this->makeRepository(unorderable: ['settings']);

        $this->assertSame([], $users->orders(['order' => 'settings->token']));
        $this->assertSame([['data->title', 'asc']], $users->orders(['order' => 'data->title']));
    }

    public function test_orderable_columns_restrict_the_order_param()
    {
        $users = $this->makeRepository(orderable: ['name', 'data->*']);

        $this->assertSame([['name', 'asc']], $users->orders(['order' => 'name']));
        $this->assertSame([['data->title', 'asc']], $users->orders(['order' => 'data->title']));
        $this->assertSame([], $users->orders(['order' => 'email']));
        $this->assertSame([], $users->orders(['order' => 'password']));
    }

    public function test_expressions_are_accepted()
    {
        $users = $this->makeRepository(orderable: ['name']);

        $orders = $users->query(['order' => DB::raw('RANDOM()')])->getQuery()->orders;

        $this->assertCount(1, $orders);
        $this->assertInstanceOf(Expression::class, $orders[0]['column']);
    }

    public function test_an_empty_order_is_ignored()
    {
        $this->assertSame([], $this->makeRepository()->orders(['order' => '']));
    }

    protected function makeRepository(?array $orderable = null, ?array $unorderable = null): Users
    {
        $users = new class($this->app['hash']->driver()) extends Users
        {
            public ?array $testUnorderable = null;

            public function query(array $params)
            {
                return $this->newQueryWithParams($params);
            }

            public function orders(array $params): array
            {
                return collect($this->query($params)->getQuery()->orders)
                    ->map(fn ($order) => [$order['column'], $order['direction']])
                    ->all();
            }

            public function setOrderableColumns(?array $columns): void
            {
                $this->orderableColumns = $columns;
            }

            protected function getUnorderableColumns(): array
            {
                return $this->testUnorderable ?? parent::getUnorderableColumns();
            }
        };
        $users->setOrderableColumns($orderable);
        $users->testUnorderable = $unorderable;

        return $users;
    }
}
