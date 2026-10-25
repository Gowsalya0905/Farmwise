<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->string('type', 100);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->string('category', 100);
            $table->decimal('amount', 14, 2);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('harvests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('season_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('harvest_id')->constrained()->restrictOnDelete();
            $table->date('date')->index();
            $table->decimal('quantity', 12, 4);
            $table->string('unit', 20);
            $table->decimal('unit_price', 12, 2);
            $table->decimal('total', 14, 2);
            $table->string('currency', 3);
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales');
        Schema::dropIfExists('harvests');
        Schema::dropIfExists('expenses');
        Schema::dropIfExists('activities');
    }
};
