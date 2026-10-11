<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('economics_events')) {
            return;
        }

        Schema::create('economics_events', function (Blueprint $table) {
            $table->increments('id');
            $table->string('country_code', 10);
            $table->double('point')->nullable();
            $table->string('description', 255)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('economics_events');
    }
};