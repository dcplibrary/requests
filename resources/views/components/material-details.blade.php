{{--
    Material detail grid — staff-facing display of all Material attributes.

    Usage:
        <x-requests::material-details :material="$material" />

    Props:
        material — Material model instance
--}}
@props([
    'material',
])

<dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm">
    <div class="col-span-2">
        <dt class="text-xs text-gray-500 mb-0.5">Title</dt>
        <dd class="font-medium text-gray-900">{{ $material->title }}</dd>
        @if($material->title_long && $material->title_long !== $material->title)
            <dd class="text-gray-500 text-xs mt-0.5">{{ $material->title_long }}</dd>
        @endif
    </div>

    <div class="col-span-2">
        <dt class="text-xs text-gray-500 mb-0.5">Author</dt>
        <dd class="text-gray-700">{{ $material->author ?: '—' }}</dd>
    </div>

    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Publish Date</dt>
        <dd class="text-gray-700">{{ $material->publish_date ?: '—' }}</dd>
    </div>

    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Type</dt>
        <dd class="text-gray-700">{{ $material->materialTypeOption?->name ?? '—' }}</dd>
    </div>

    @if($material->isbn || $material->isbn13)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">ISBN</dt>
        <dd class="font-mono text-gray-700 text-xs">{{ $material->isbn ?: '—' }}</dd>
    </div>
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">ISBN-13</dt>
        <dd class="font-mono text-gray-700 text-xs">{{ $material->isbn13 ?: '—' }}</dd>
    </div>
    @endif

    @if($material->publisher)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Publisher</dt>
        <dd class="text-gray-700">{{ $material->publisher }}</dd>
    </div>
    @endif

    @if($material->edition)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Edition</dt>
        <dd class="text-gray-700">{{ $material->edition }}</dd>
    </div>
    @endif

    @if($material->binding)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Binding</dt>
        <dd class="text-gray-700">{{ $material->binding }}</dd>
    </div>
    @endif

    @if($material->pages)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Pages</dt>
        <dd class="text-gray-700">{{ number_format($material->pages) }}</dd>
    </div>
    @endif

    @if($material->language)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Language</dt>
        <dd class="text-gray-700">{{ strtoupper($material->language) }}</dd>
    </div>
    @endif

    @if($material->msrp)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">MSRP</dt>
        <dd class="text-gray-700">${{ number_format((float) $material->msrp, 2) }}</dd>
    </div>
    @endif

    @if($material->dewey_decimal)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Dewey Decimal</dt>
        <dd class="font-mono text-gray-700 text-xs">{{ $material->dewey_decimal }}</dd>
    </div>
    @endif

    @if($material->dimensions)
    <div>
        <dt class="text-xs text-gray-500 mb-0.5">Dimensions</dt>
        <dd class="text-gray-700 text-xs">{{ $material->dimensions }}</dd>
    </div>
    @endif

    @if(!empty($material->subjects))
    <div class="col-span-2">
        <dt class="text-xs text-gray-500 mb-1">Subjects</dt>
        <dd class="flex flex-wrap gap-1">
            @foreach($material->subjects as $subject)
                <span class="inline-block px-2 py-0.5 rounded-full text-xs bg-gray-100 text-gray-600">{{ $subject }}</span>
            @endforeach
        </dd>
    </div>
    @endif

    @if($material->overview)
    <div class="col-span-2">
        <dt class="text-xs text-gray-500 mb-0.5">Overview</dt>
        <dd class="text-gray-600 text-xs leading-relaxed [&_p]:mb-1 [&_b]:font-semibold [&_strong]:font-semibold [&_i]:italic [&_em]:italic">{!! $material->overview !!}</dd>
    </div>
    @endif

    @if($material->synopsis)
    <div class="col-span-2">
        <dt class="text-xs text-gray-500 mb-0.5">Synopsis</dt>
        <dd class="text-gray-600 text-xs leading-relaxed [&_p]:mb-1 [&_b]:font-semibold [&_strong]:font-semibold [&_i]:italic [&_em]:italic">{!! $material->synopsis !!}</dd>
    </div>
    @endif
</dl>
