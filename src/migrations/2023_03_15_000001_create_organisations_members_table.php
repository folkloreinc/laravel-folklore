<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('organisation_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users');
            $table->string('role', 100)
                ->nullable()
                ->default(null)
                ->index();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('organisation_members');
    }
};
