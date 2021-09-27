<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {
         if( !\App\Models\User::where("username","tridevsharma")->count() )
         {
            \App\Models\User::create([
                "first"     => "Tridev",
                "last"      => "Sharma",
                "email"     => "sendmailtotridev@gmail.com",
                "username"  => "tridevsharma",
                "role"      => "admin",
                "password"  => bcrypt("password")
            ]);
         }

         \App\Models\User::create([
            "first"     => "Keshav",
            "last"      => "Sharma",
            "email"     => "keshav@gmail.com",
            "username"  => "keshav",
            "role"      => "tenant",
            "password"  => bcrypt("password")
        ]);

        \App\Models\User::create([
            "first"     => "Mohan",
            "last"      => "Sharma",
            "email"     => "mohan@gmail.com",
            "username"  => "mohan",
            "role"      => "ibo",
            "password"  => bcrypt("password")
        ]);
    }
}
