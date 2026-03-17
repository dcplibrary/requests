<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->string('label');
            $table->string('type')->default('string'); // string|integer|boolean|text|html
            $table->string('group')->default('general');
            $table->text('description')->nullable();
            $table->json('tokens')->nullable();        // optional insert-token list for admin UI
            $table->timestamps();
        });

        $now = now();
        $bodyTokens = json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{audience}', '{status}', '{status_name}', '{action_buttons}', '{submitted_date}', '{request_url}']);

        foreach ([
            [
                'key' => 'staff_routing_ill_subject',
                'value' => 'New ILL request: {title}',
                'label' => 'ILL Staff Routing — Subject',
                'type' => 'string',
                'group' => 'notifications',
                'description' => 'Subject line for new interlibrary loan staff notifications.',
                'tokens' => json_encode(['{title}', '{author}', '{isbn}', '{patron_name}', '{patron_first_name}', '{material_type}', '{status}', '{submitted_date}']),
            ],
            [
                'key' => 'staff_routing_ill_template',
                'value' => '',
                'label' => 'ILL Staff Routing — Email Body',
                'type' => 'html',
                'group' => 'notifications',
                'description' => 'HTML body for ILL staff routing. Leave blank for built-in default. Use {action_buttons} to place quick-action links.',
                'tokens' => $bodyTokens,
            ],
            [
                'key' => 'staff_routing_ill_title',
                'value' => 'ILL staff routing',
                'label' => 'ILL Staff Routing — Title',
                'type' => 'string',
                'group' => 'notifications',
                'description' => 'Label for this template in the Emails list.',
            ],
        ] as $row) {
            DB::table('settings')->insert(array_merge($row, ['created_at' => $now, 'updated_at' => $now]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
