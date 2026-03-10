<?php

namespace Dcplibrary\Sfp\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PatronStatusTemplatesSeeder extends Seeder
{
    public function run(): void
    {
        $templates = [
            [
                'slug'       => 'on-order',
                'name'       => 'On Order',
                'enabled'    => true,
                'is_default' => false,
                'sort_order' => 1,
                'subject'    => 'Your suggestion is on order: {title}',
                'body'       => <<<'HTML'
<p>Hi {patron_first_name},</p>
<p>Great news! Your purchase suggestion has been accepted and the item is now on order.</p>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;margin:16px 0;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
</table>
<p>Thank you for your suggestion!</p>
HTML,
            ],
            [
                'slug'       => 'purchased',
                'name'       => 'Purchased',
                'enabled'    => true,
                'is_default' => false,
                'sort_order' => 2,
                'subject'    => 'Your suggestion has been purchased: {title}',
                'body'       => <<<'HTML'
<p>Hi {patron_first_name},</p>
<p>We are pleased to let you know that your purchase suggestion has been acquired and will be available soon.</p>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;margin:16px 0;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
</table>
<p>Thank you for your suggestion!</p>
HTML,
            ],
            [
                'slug'       => 'denied',
                'name'       => 'Denied',
                'enabled'    => true,
                'is_default' => false,
                'sort_order' => 3,
                'subject'    => 'Update on your suggestion: {title}',
                'body'       => <<<'HTML'
<p>Hi {patron_first_name},</p>
<p>Thank you for your purchase suggestion. After review, we are unable to add this item to our collection at this time.</p>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;margin:16px 0;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
</table>
<p>We encourage you to continue submitting suggestions. Thank you for helping us build our collection!</p>
HTML,
            ],
            [
                'slug'       => 'ill-referred',
                'name'       => 'ILL Referred',
                'enabled'    => true,
                'is_default' => false,
                'sort_order' => 4,
                'subject'    => 'Interlibrary loan referral: {title}',
                'body'       => <<<'HTML'
<p>Hi {patron_first_name},</p>
<p>Your purchase suggestion has been reviewed. While we are not adding this item to our collection, we have referred your request to our Interlibrary Loan service so you may be able to borrow it from another library.</p>
<table role="presentation" style="font-size:14px;border-collapse:collapse;width:100%;margin:16px 0;">
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Title</td>
    <td style="padding:5px 0;font-weight:bold;">{title}</td>
  </tr>
  <tr>
    <td style="padding:5px 14px 5px 0;color:#6b7280;white-space:nowrap;vertical-align:top;">Author</td>
    <td style="padding:5px 0;">{author}</td>
  </tr>
</table>
<p>Thank you for your suggestion!</p>
HTML,
            ],
        ];

        foreach ($templates as $template) {
            $slug = $template['slug'];
            unset($template['slug']);

            // Match on name as a stable natural key (no slug column on templates table).
            $existing = DB::table('patron_status_templates')->where('name', $template['name'])->first();

            if ($existing) {
                // Never overwrite subject/body on an existing template — staff may have customised it.
                continue;
            }

            $now = now();
            $id  = DB::table('patron_status_templates')->insertGetId(array_merge($template, [
                'created_at' => $now,
                'updated_at' => $now,
            ]));

            // Link to the matching request status by slug.
            $status = DB::table('request_statuses')->where('slug', $slug)->first();
            if ($status) {
                DB::table('patron_status_template_request_status')->insertOrIgnore([
                    'patron_status_template_id' => $id,
                    'request_status_id'         => $status->id,
                ]);
            }
        }
    }
}
