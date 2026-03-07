<?php

namespace Dcplibrary\Sfp\Tests\Unit;

use Dcplibrary\Sfp\Models\FormField;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class SfpFormStepTwoRulesTest extends TestCase
{
    #[Test]
    public function hidden_required_field_is_not_required_for_state(): void
    {
        $field = new FormField();
        $field->key      = 'title';
        $field->active   = true;
        $field->required = true;
        $field->condition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ];

        $state = ['material_type' => 'dvd', 'audience' => 'adult'];

        $this->assertFalse($field->isVisibleFor($state));
        $this->assertFalse(
            $field->isRequiredFor($state),
            'Required fields must become non-required when hidden by conditional logic'
        );
    }

    #[Test]
    public function visible_required_field_is_required_for_state(): void
    {
        $field = new FormField();
        $field->key      = 'title';
        $field->active   = true;
        $field->required = true;
        $field->condition = [
            'match' => 'all',
            'rules' => [
                ['field' => 'material_type', 'operator' => 'in', 'values' => ['book']],
            ],
        ];

        $state = ['material_type' => 'book', 'audience' => 'adult'];

        $this->assertTrue($field->isVisibleFor($state));
        $this->assertTrue($field->isRequiredFor($state));
    }
}

