<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Dcplibrary\Requests\Models\Form;
use Illuminate\Database\Seeder;

class FormsSeeder extends Seeder
{
    public function run(): void
    {
        $forms = [
            ['name' => 'Suggest for Purchase', 'slug' => 'sfp'],
            ['name' => 'Interlibrary Loan', 'slug' => 'ill'],
        ];

        foreach ($forms as $form) {
            Form::firstOrCreate(
                ['slug' => $form['slug']],
                ['name' => $form['name']]
            );
        }
    }
}
