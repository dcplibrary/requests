<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Restrict subject-line settings to a reduced token list.
     * Removes body-only / material-type-specific tokens from the subject UI.
     */
    public function up(): void
    {
        $subjectTokens = json_encode([
            '{title}',
            '{author}',
            '{patron_name}',
            '{patron_first_name}',
            '{material_type}',
            '{status}',
            '{submitted_date}',
        ]);

        foreach (['staff_routing_subject', 'patron_status_subject'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->update(['tokens' => $subjectTokens]);
        }
    }

    /**
     * Revert to the previous subject token list (with audience, request_url).
     */
    public function down(): void
    {
        $previousTokens = json_encode([
            '{title}',
            '{author}',
            '{patron_name}',
            '{patron_first_name}',
            '{material_type}',
            '{audience}',
            '{status}',
            '{submitted_date}',
            '{request_url}',
        ]);

        foreach (['staff_routing_subject', 'patron_status_subject'] as $key) {
            DB::table('settings')
                ->where('key', $key)
                ->update(['tokens' => $previousTokens]);
        }
    }
};
