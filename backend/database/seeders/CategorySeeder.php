<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Category::create([
            'name' => 'VTuber',
        ]);

        Category::create([
            'name' => 'スポーツ',
        ]);

        Category::create([
            'name' => 'ゲーム',
        ]);

        Category::create([
            'name' => 'アニメ',
        ]);

        Category::create([
            'name' => '音楽',
        ]);

        Category::create([
            'name' => 'その他',
        ]);
    }
}