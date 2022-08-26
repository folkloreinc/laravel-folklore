<?php

namespace Folklore\Console;

use Illuminate\Console\Command;
use Folklore\Contracts\Repositories\Users;
use Illuminate\Support\Facades\Hash;

class UsersCreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'users:create {email?} {--name=} {--password=} {--role=admin}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a user';

    protected $users;

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct(Users $users)
    {
        parent::__construct();

        $this->users = $users;
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');
        $name = $this->option('name');
        $role = $this->option('role');
        $password = $this->option('password');

        if (empty($email)) {
            $email = $this->ask('Enter the email address');
        }

        if (empty($name)) {
            $name = $this->ask('Enter the name');
        }

        if (empty($password)) {
            $password = $this->ask('Enter the password');
        }

        if (empty($email) || empty($name) || empty($password)) {
            exit('Please fill all the required fields.');
        }

        $user = $this->users->create([
            'name' => $name,
            'email' => $email,
            'role' => $role,
            'password' => Hash::make($password),
        ]);

        $user->markEmailAsVerified();

        $this->info('User #' . $user->id() . ' created.');
        $this->line('<info>Email:</info> ' . $user->email());
        $this->line('<info>Name:</info> ' . $user->name());
        $this->line('<info>Password:</info> ' . $password);
    }
}
