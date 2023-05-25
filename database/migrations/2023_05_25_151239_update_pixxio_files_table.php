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
        Schema::table('pixxio_files', function (Blueprint $table) {
            $table->integer('height')->nullable();
            $table->integer('width')->nullable();
            $table->text('mimetype')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pixxio_files', function (Blueprint $table) {
            $table->dropColumn('height');
        });

        Schema::table('pixxio_files', function (Blueprint $table) {
            $table->dropColumn('width');
        });

        Schema::table('pixxio_files', function (Blueprint $table) {
            $table->dropColumn('mimetype');
        });
    }
};
