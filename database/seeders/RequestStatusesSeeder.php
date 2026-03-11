<?php

namespace Dcplibrary\Requests\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RequestStatusesSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            [
                'name' => 'Pending', 'slug' => 'pending', 'color' => '#f59e0b',
                'sort_order' => 1, 'active' => true, 'is_terminal' => false, 'notify_patron' => true,
                'description' => 'Pending means your request has been received.',
            ],
            [
                'name' => 'Under Review', 'slug' => 'under-review', 'color' => '#3b82f6',
                'sort_order' => 2, 'active' => true, 'is_terminal' => false, 'notify_patron' => true,
                'description' => "Under Review means a library staff member is reviewing this request to see if it fits the library's collection.",
            ],
            [
                'name' => 'On Order', 'slug' => 'on-order', 'color' => '#8b5cf6',
                'sort_order' => 3, 'active' => true, 'is_terminal' => false, 'notify_patron' => true,
                'description' => 'On Order means your request has been purchased for the library collection. The item will appear in the catalog soon and you will be able to put it on hold.',
            ],
            [
                'name' => 'Purchased', 'slug' => 'purchased', 'color' => '#10b981',
                'sort_order' => 4, 'active' => true, 'is_terminal' => true, 'notify_patron' => true,
                'description' => 'Your suggestion has been selected for the library collection. The item will be purchased soon. Occasionally check the catalog for the item so that you can place it on hold.',
            ],
            [
                'name' => 'Denied', 'slug' => 'denied', 'color' => '#ef4444',
                'sort_order' => 5, 'active' => true, 'is_terminal' => true, 'notify_patron' => true,
                'description' => "Denied means that alternative items in the library cover this topic, or the request does not fulfill the library's collection development policies. Please consider requesting an Interlibrary Loan.",
            ],
            [
                'name' => 'ILL Referred', 'slug' => 'ill-referred', 'color' => '#6b7280',
                'sort_order' => 6, 'active' => true, 'is_terminal' => true, 'notify_patron' => true,
                'description' => 'ILL Referred means that the library will attempt to borrow your request from another library. If the item can be borrowed, we will notify you when it is ready for pickup.',
            ],
        ];

        foreach ($statuses as $status) {
            DB::table('request_statuses')->updateOrInsert(
                ['slug' => $status['slug']],
                array_merge($status, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
