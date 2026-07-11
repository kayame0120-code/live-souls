<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     *
     * オーナー（片倉）アカウントを冪等に再作成する。
     * 招待制のため画面から作れないので、開発・復旧用にここで用意する。
     * password は User の 'hashed' キャストで保存時に自動ハッシュ化される。
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'k.ayame0120@gmail.com'],
            [
                'name' => '片倉',
                'password' => 'kjna0809',
            ],
        );
    }
}
