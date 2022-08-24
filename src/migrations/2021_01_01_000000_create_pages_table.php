<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('pages', function (Blueprint $table) {
            $locales = config('locale.locales');
            $table->id();
            $table
                ->string('handle')
                ->default(null)
                ->nullable()
                ->index();
            $table
                ->string('type')
                ->default('page')
                ->nullable();
            $table
                ->foreignId('parent_id')
                ->nullable()
                ->index();
            foreach ($locales as $locale) {
                $table
                    ->string('slug_' . $locale)
                    ->nullable()
                    ->index();
            }
            $table->longText('data')->nullable();
            $table
                ->boolean('published')
                ->default(true)
                ->index();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('pages');
    }
};
