<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chef_kitchen', function (Blueprint $table) {
            $table->foreignId('chef_id')->constrained()->cascadeOnDelete();
            $table->foreignId('kitchen_id')->constrained()->cascadeOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->foreignId('assigned_by')->nullable()->constrained('users')->nullOnDelete();

            $table->primary(['chef_id', 'kitchen_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chef_kitchen');
    }
};
