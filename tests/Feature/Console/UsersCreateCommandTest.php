<?php

namespace Folklore\Tests\Feature\Console;

use ArrayObject;
use Folklore\Models\User as UserModel;
use Folklore\Tests\TestCase;
use Illuminate\Console\OutputStyle;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Question\Question;

class UsersCreateCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->loadLaravelMigrations(['--database' => 'testbench']);
        $this->artisan('migrate', [
            '--database' => 'testbench',
            '--path' => __DIR__.'/../../../src/migrations/2020_01_01_000000_add_role_to_users.php',
            '--realpath' => true,
        ]);
    }

    public function test_it_creates_a_user_without_printing_the_password()
    {
        $this->artisan('users:create', [
            'email' => 'jane@example.com',
            '--name' => 'Jane',
            '--password' => 'secret-password',
        ])
            ->expectsOutputToContain('created')
            ->doesntExpectOutputToContain('secret-password')
            ->assertSuccessful();

        $user = UserModel::where('email', 'jane@example.com')->firstOrFail();
        $this->assertTrue(Hash::check('secret-password', $user->password));
        $this->assertSame('admin', $user->role);
        $this->assertNotNull($user->email_verified_at);
    }

    public function test_it_asks_for_the_password_without_echoing_it()
    {
        $questions = new ArrayObject;
        $answers = [
            'Enter the email address' => 'jane@example.com',
            'Enter the name' => 'Jane',
            'Enter the password' => 'secret-password',
        ];
        $this->app->bind(OutputStyle::class, function ($app, $parameters) use ($questions, $answers) {
            return new class($parameters['input'], $parameters['output'], $questions, $answers) extends OutputStyle
            {
                public function __construct($input, $output, protected ArrayObject $questions, protected array $answers)
                {
                    parent::__construct($input, $output);
                }

                public function askQuestion(Question $question): mixed
                {
                    $this->questions[$question->getQuestion()] = $question;

                    return $this->answers[$question->getQuestion()];
                }
            };
        });

        $output = new BufferedOutput;
        $exitCode = Artisan::call('users:create', [], $output);

        $this->assertSame(0, $exitCode);
        $this->assertTrue($questions['Enter the password']->isHidden());
        $this->assertFalse($questions['Enter the email address']->isHidden());
        $this->assertStringNotContainsString('secret-password', $output->fetch());
        $this->assertTrue(Hash::check(
            'secret-password',
            UserModel::where('email', 'jane@example.com')->firstOrFail()->password,
        ));
    }

    public function test_it_fails_when_a_field_is_missing()
    {
        $this->artisan('users:create', ['email' => 'jane@example.com', '--name' => 'Jane'])
            ->expectsQuestion('Enter the password', '')
            ->expectsOutputToContain('Please fill all the required fields.')
            ->assertFailed();

        $this->assertSame(0, UserModel::count());
    }
}
