<?php

namespace Dcplibrary\Requests\Http\Controllers\Concerns;

use Dcplibrary\Requests\Models\Field;

/**
 * Shared token-list builder for email template editors.
 *
 * Centralises the logic that produces the list of {token} insert buttons
 * shown on the staff email settings pages and patron status template forms.
 *
 * Field-based tokens are sourced directly from the fields table
 * (`include_as_token = true`). The `active` flag is intentionally ignored
 * so that fields like `isbn` — which are inactive on the patron form but
 * still meaningful in staff emails — still appear.
 */
trait ProvidesEmailTokens
{
    /**
     * Core request tokens that are not stored in the fields table.
     * Title, author, material_type, and audience come from dedicated
     * columns / EAV rows but have `include_as_token = false` in the DB.
     *
     * @return string[]
     */
    protected function coreTokens(): array
    {
        return ['{title}', '{author}', '{material_type}', '{audience}'];
    }

    /**
     * System tokens relating to the patron and request metadata.
     * These are always present regardless of which fields are active.
     *
     * @param  bool  $includeStatusDescription  Include {status_description} (patron emails only).
     * @return string[]
     */
    protected function systemTokens(bool $includeStatusDescription = false): array
    {
        $tokens = [
            '{patron_name}', '{patron_first_name}', '{patron_email}', '{patron_phone}',
            '{status}', '{status_name}', '{submitted_date}', '{request_url}',
        ];

        if ($includeStatusDescription) {
            $tokens[] = '{status_description}';
        }

        return $tokens;
    }

    /**
     * Tokens derived from the fields table where `include_as_token = true`.
     * Active state is ignored so fields like `isbn` (inactive on the patron
     * form but useful in staff emails) are included.
     *
     * @return string[]
     */
    protected function fieldTokens(): array
    {
        try {
            return Field::query()
                ->where('include_as_token', true)
                ->ordered()
                ->pluck('key')
                ->map(fn (string $k) => "{{$k}}")
                ->all();
        } catch (\Throwable $e) {
            // Fields table may not exist during initial install.
            return [];
        }
    }

    /**
     * Full list of available tokens for a body editor.
     *
     * @param  bool  $includeStatusDescription
     * @return string[]
     */
    protected function availableTokens(bool $includeStatusDescription = false): array
    {
        return array_values(array_unique(array_merge(
            $this->coreTokens(),
            $this->systemTokens($includeStatusDescription),
            $this->fieldTokens(),
        )));
    }

    /**
     * Tokens that should be excluded from subject-line editors.
     * These are body-only: long text, URLs, field-specific values.
     *
     * @return string[]
     */
    protected function subjectExcludedTokens(): array
    {
        return [
            '{will_pay_up_to}', '{ill_requested}', '{prefer_mail}', '{other_specify}',
            '{publisher}', '{periodical_title}', '{article_author}', '{article_title}', '{volume_number}',
            '{page_number}', '{director}', '{cast}', '{comments}', '{request_url}', '{genre}', '{isbn}',
            '{publish_date}', '{where_heard}', '{date_needed_by}', '{console}',
            '{patron_email}', '{patron_phone}', '{audience}', '{action_buttons}',
            '{convert_to_ill_url}', '{convert_to_ill_link}',
        ];
    }
}
