<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('spgs', function (Blueprint $table) {
            $table->id();
            $table->string('spg_name')->index();
            $table->foreignId('assigned_outlet_id')->nullable()->constrained('outlets')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->text('aliases')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spgs');
    }
};
