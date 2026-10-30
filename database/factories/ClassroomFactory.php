<?php

namespace Database\Factories;

use App\Models\Classroom;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClassroomFactory extends Factory
{
    protected $model = Classroom::class;

    public function definition(): array
    {
        return [
            'name' => 'Kelas '.fake()->unique()->randomNumber(2),
            'description' => null,
            'code' => Classroom::generateCode(),
        ];
    }
}
