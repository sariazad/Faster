<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            ['name' => 'المشرف', 'password' => env('ADMIN_PASSWORD', 'password')],
        );

        $this->call(QuestionBankSeeder::class);
        $this->call(AlefYaSeeder::class);
        $this->call(AkherSeeder::class);
    }
}
