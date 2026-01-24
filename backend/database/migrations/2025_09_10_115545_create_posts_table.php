<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('post', function (Blueprint $table) {
            $table->id();

            // Khóa ngoại tới topic
            $table->foreignId('topic_id')
                  ->nullable()
                  ->constrained('topic')
                  ->nullOnDelete();

            $table->string('title', 1000);
            $table->string('slug', 1000)->unique();
            $table->string('image', 255)->nullable();
            $table->longText('content');
            $table->text('description')->nullable();

            $table->enum('post_type', ['post', 'page'])->default('post');
            $table->unsignedBigInteger('created_by')->default(1);
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->unsignedTinyInteger('status')->default(1);

            $table->timestamps();

            $table->index(['status', 'post_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('post');
    }
};
