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
        Schema::create('organisations_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organisation_id')->constrained('organisations');
            $table->string('email')->nullable();
            $table->foreignId('role_id', 100)->nullable();
            $table->string('invitation_token')->nullable();
            $table->timestamp('invited_at')->nullable();
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
        Schema::dropIfExists('organisations_invitations');
    }
};
