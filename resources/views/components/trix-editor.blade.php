{{--
    SFP Trix Editor Component
    =========================
    Renders a Trix rich-text editor with an optional token insert bar below it.
    Token insertion is resolved at click time — no trix-initialize event needed.

    Props:
        $inputId    (string) – ID for the hidden <input> element          [required]
        $name       (string) – name attribute for form submission          [required]
        $value      (string) – current HTML content                        [default: '']
        $tokens     (array)  – token strings e.g. ['{title}', '{author}'] [default: []]
        $minHeight  (string) – CSS min-height for the editor area          [default: '8rem']

    Note: the page must include sfpTrixInsert() and sfpInsertToken() — both are
    defined in a <script> block at the bottom of each settings view that uses this
    component, keeping the JS co-located with the view and out of the component.
--}}
@props([
    'inputId',
    'name',
    'value'     => '',
    'tokens'    => [],
    'minHeight' => '8rem',
])

@php $tokens = array_values(array_filter((array) $tokens)); @endphp

<input type="hidden"
       id="{{ $inputId }}"
       name="{{ $name }}"
       value="{{ $value }}">

<trix-editor
    input="{{ $inputId }}"
    class="trix-content border border-gray-300 rounded bg-white text-sm"
    style="min-height: {{ $minHeight }}"></trix-editor>

@if(count($tokens))
<div class="flex flex-wrap items-center gap-1.5 mt-2">
    <span class="text-xs text-gray-400">Insert:</span>
    @foreach($tokens as $token)
    <button type="button"
            onclick="sfpTrixInsert('{{ $token }}', '{{ $inputId }}')"
            class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 hover:bg-indigo-50 hover:text-indigo-700 text-xs font-mono text-gray-600 border border-gray-200 hover:border-indigo-300 transition-colors">
        {{ $token }}
    </button>
    @endforeach
</div>
@endif
