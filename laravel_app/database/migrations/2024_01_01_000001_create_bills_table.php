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
        Schema::create('bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('subject');
            $table->decimal('amount', 10, 2)->nullable();
            $table->date('due_date')->nullable();
            $table->string('status')->default('unprocessed');
            $table->text('raw_email')->nullable();
            $table->timestamp('received_at')->useCurrent();
            $table->boolean('is_lottery')->default(false);
            $table->string('lottery_numbers')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('bills');
    }
};
