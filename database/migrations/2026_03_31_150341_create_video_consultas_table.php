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
        Schema::create('video_consultas', function (Blueprint $table) {
            $table->id();

            // identificación
            $table->string('room_name')->unique();

            // relaciones (IMPORTANTE mantener)
            $table->unsignedBigInteger('paciente_id')->nullable();
            $table->unsignedBigInteger('medico_id')->nullable();

            // tokens de acceso
            $table->string('token_medico', 40)->unique();
            $table->string('token_paciente', 40)->unique();

            // estado
            $table->string('estado')->default('inactiva');

            // tiempos
            $table->timestamp('inicio_programado')->nullable();
            $table->timestamp('inicio_real')->nullable();
            $table->timestamp('fin_real')->nullable();

            // flags
            $table->boolean('paciente_conectado')->default(false);
            $table->boolean('medico_conectado')->default(false);

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('video_consultas');
    }
};
