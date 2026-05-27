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
        Schema::create('notifications_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->timestamp('period_start');
            $table->timestamp('period_end');
            $table->string('status')->default('pending');
            $table->string('file_path')->nullable();
            $table->text('error_message')->nullable();
            $table->foreignId('created_by')->constrained('users', 'id')->cascadeOnDelete();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index('uuid');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('notifications_reports');
    }
};
