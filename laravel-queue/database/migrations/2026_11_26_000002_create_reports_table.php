<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Sakala runs this through the release command `php artisan migrate --force`,
     * once per deployment, before the new version takes traffic.
     */
    public function up(): void
    {
        Schema::create('reports', function (Blueprint $table) {
            $table->id();
            $table->string('title', 80);
            $table->string('status', 16)->index();
            // Queue connection that carried the job: `redis` or `database`.
            $table->string('queue_connection', 32)->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });

        Schema::create('cleanup_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('deleted_reports');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cleanup_runs');
        Schema::dropIfExists('reports');
    }
};
