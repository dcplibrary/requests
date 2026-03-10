<?php

namespace Dcplibrary\Sfp\Tests\Unit;

use Dcplibrary\Sfp\Models\CustomField;
use Dcplibrary\Sfp\Models\FormField;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for the conditional-logic engine shared by FormField and CustomField.
 *
 * Both models implement identical isVisibleFor() / isRequiredFor() logic.
 * Tests cover: match modes (all/any), operators (in/not_in), edge cases
 * (inactive, no condition, empty state, unknown operator).
 *
 * No database or Laravel boot required — fields are instantiated in memory.
 *
 * @see \Dcplibrary\Sfp\Models\FormField::isVisibleFor()
 * @see \Dcplibrary\Sfp\Models\CustomField::isVisibleFor()
 */
class FormFieldConditionTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Build a FormField with the given condition array.
     *
     * @param array|null $condition
     * @param bool       $active
     * @param bool       $required
     * @return FormField
     */
    private function formField(?array $condition, bool $active = true, bool $required = false): FormField
    {
        $f = new FormField();
        $f->key       = 'test_field';
        $f->active    = $active;
        $f->required  = $required;
        $f->condition = $condition;

        return $f;
    }

    /**
     * Build a CustomField with the given condition array.
     *
     * @param array|null $condition
     * @param bool       $active
     * @param bool       $required
     * @return CustomField
     */
    private function customField(?array $condition, bool $active = true, bool $required = false): CustomField
    {
        $f = new CustomField();
        $f->key       = 'test_custom';
        $f->active    = $active;
        $f->required  = $required;
        $f->condition = $condition;

        return $f;
    }

    /**
     * Convenience: a typical form state with material_type and audience slugs.
     *
     * @param string $materialType
     * @param string $audience
     * @return array{material_type: string, audience: string}
     */
    private function state(string $materialType = 'book', string $audience = 'adult'): array
    {
        return ['material_type' => $materialType, 'audience' => $audience];
    }

    // =========================================================================
    // 1. Inactive field — always hidden
    // =========================================================================

    #[Test]
    public function inactive_field_is_never_visible(): void
    {
        $field = $this->formField(condition: null, active: false);

        $this->assertFalse($field->isVisibleFor($this->state()));
    }

    #[Test]
    public function inactive_custom_field_is_never_visible(): void
    {
        $field = $this->customField(condition: null, active: false);

        $this->assertFalse($field->isVisibleFor($this->state()));
    }

    // =========================================================================
    // 2. No condition — always visible (when active)
    // =========================================================================

    #[Test]
    public function null_condition_means_always_visible(): void
    {
        $field = $this->formField(condition: null);

        $this->assertTrue($field->isVisibleFor($this->state()));
    }

    #[Test]
    public function empty_rules_array_means_always_visible(): void
    {
        $field = $this->formField(condition: ['match' => 'all', 'rules' => []]);

        $this->assertTrue($field->isVisibleFor($this->state()));
    }

    // =========================================================================
    // 3. "in" operator
    // =========================================================================

    #[Test]
    public function in_operator_matches_when_value_is_in_list(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book', 'ebook']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book')));
        $this->assertTrue($field->isVisibleFor($this->state('ebook')));
    }

    #[Test]
    public function in_operator_rejects_when_value_not_in_list(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor($this->state('dvd')));
    }

    // =========================================================================
    // 4. "not_in" operator
    // =========================================================================

    #[Test]
    public function not_in_operator_matches_when_value_is_absent(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'not_in', 'values' => ['dvd', 'blu-ray']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book')));
    }

    #[Test]
    public function not_in_operator_rejects_when_value_is_present(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'not_in', 'values' => ['dvd', 'blu-ray']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor($this->state('dvd')));
    }

    // =========================================================================
    // 5. match = "all" — every rule must pass
    // =========================================================================

    #[Test]
    public function match_all_requires_every_rule_to_pass(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['adult']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book', 'adult')));
        $this->assertFalse($field->isVisibleFor($this->state('book', 'kids')));
        $this->assertFalse($field->isVisibleFor($this->state('dvd', 'adult')));
    }

    // =========================================================================
    // 6. match = "any" — at least one rule must pass
    // =========================================================================

    #[Test]
    public function match_any_requires_at_least_one_rule_to_pass(): void
    {
        $field = $this->formField(condition: [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['kids']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book', 'adult')));  // first rule
        $this->assertTrue($field->isVisibleFor($this->state('dvd', 'kids')));    // second rule
        $this->assertFalse($field->isVisibleFor($this->state('dvd', 'adult')));  // neither
    }

    #[Test]
    public function match_any_with_all_rules_passing_is_visible(): void
    {
        $field = $this->formField(condition: [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['adult']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book', 'adult')));
    }

    // =========================================================================
    // 7. Edge cases — empty/null state values
    // =========================================================================

    #[Test]
    public function null_state_value_fails_rule(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor(['material_type' => null, 'audience' => 'adult']));
    }

    #[Test]
    public function empty_string_state_value_fails_rule(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor(['material_type' => '', 'audience' => 'adult']));
    }

    #[Test]
    public function missing_state_key_fails_rule(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor(['audience' => 'adult']));
    }

    // =========================================================================
    // 8. Unknown operator — fails closed
    // =========================================================================

    #[Test]
    public function unknown_operator_fails_rule(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'equals', 'values' => ['book']],
            ],
        ]);

        $this->assertFalse($field->isVisibleFor($this->state('book')));
    }

    // =========================================================================
    // 9. isRequiredFor — ties required flag to visibility
    // =========================================================================

    #[Test]
    public function required_and_visible_means_required(): void
    {
        $field = $this->formField(
            condition: ['match' => 'all', 'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ]],
            required: true,
        );

        $this->assertTrue($field->isRequiredFor($this->state('book')));
    }

    #[Test]
    public function required_but_hidden_means_not_required(): void
    {
        $field = $this->formField(
            condition: ['match' => 'all', 'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ]],
            required: true,
        );

        $this->assertFalse($field->isRequiredFor($this->state('dvd')));
    }

    #[Test]
    public function not_required_even_when_visible(): void
    {
        $field = $this->formField(condition: null, required: false);

        $this->assertTrue($field->isVisibleFor($this->state()));
        $this->assertFalse($field->isRequiredFor($this->state()));
    }

    // =========================================================================
    // 10. CustomField parity — same logic, separate model
    // =========================================================================

    #[Test]
    public function custom_field_in_operator_works(): void
    {
        $field = $this->customField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book')));
        $this->assertFalse($field->isVisibleFor($this->state('dvd')));
    }

    #[Test]
    public function custom_field_match_any_works(): void
    {
        $field = $this->customField(condition: [
            'match' => 'any',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['kids']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('dvd', 'kids')));
        $this->assertFalse($field->isVisibleFor($this->state('dvd', 'adult')));
    }

    #[Test]
    public function custom_field_required_tied_to_visibility(): void
    {
        $field = $this->customField(
            condition: ['match' => 'all', 'rules' => [
                ['field' => 'audience', 'operator' => 'in', 'values' => ['adult']],
            ]],
            required: true,
        );

        $this->assertTrue($field->isRequiredFor($this->state('book', 'adult')));
        $this->assertFalse($field->isRequiredFor($this->state('book', 'kids')));
    }

    // =========================================================================
    // 11. Mixed operators in one condition
    // =========================================================================

    #[Test]
    public function mixed_in_and_not_in_with_match_all(): void
    {
        $field = $this->formField(condition: [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book', 'ebook']],
                ['field' => 'audience', 'operator' => 'not_in', 'values' => ['kids']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book', 'adult')));
        $this->assertFalse($field->isVisibleFor($this->state('book', 'kids')));   // not_in fails
        $this->assertFalse($field->isVisibleFor($this->state('dvd', 'adult')));   // in fails
    }

    // =========================================================================
    // 12. Default match mode (omitted key defaults to "all")
    // =========================================================================

    #[Test]
    public function missing_match_key_defaults_to_all(): void
    {
        $field = $this->formField(condition: [
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
                ['field' => 'audience', 'operator' => 'in', 'values' => ['adult']],
            ],
        ]);

        $this->assertTrue($field->isVisibleFor($this->state('book', 'adult')));
        $this->assertFalse($field->isVisibleFor($this->state('book', 'kids')));
    }
}
