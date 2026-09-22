<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editorial_explanations', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->string('locale', 10)->default('fr');
            $table->string('title')->nullable();
            $table->text('body');
            $table->jsonb('metadata')->nullable();
            $table->timestamps();

            $table->index(['locale', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editorial_explanations');
    }
};
