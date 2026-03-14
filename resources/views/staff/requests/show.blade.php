@extends('requests::staff._layout')

@section('title', 'Request #' . $patronRequest->id)

@section('content')
@if($justClaimed ?? false)
<div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-3 py-2">
    This request was automatically assigned to you.
</div>
@endif

@php
    $material  = $patronRequest->material;
    $patron    = $patronRequest->patron;
    $title     = $material?->title ?? $patronRequest->submitted_title ?? '—';
    $author    = $material?->author ?? $patronRequest->submitted_author ?? '—';
    $synopsis  = $material?->synopsis ?? $material?->overview ?? null;
    $isbn      = $material?->isbn13 ?? $material?->isbn ?? null;
    $coreKeys  = ['material_type', 'audience', 'title', 'author', 'publish_date', 'isbn'];
    $customVals = $patronRequest->fieldValues->filter(fn ($v) =>
        $v->field && ! in_array($v->field->key, $coreKeys)
    )->sortBy(fn ($v) => $v->field?->sort_order ?? 9999)->values();
@endphp

{{-- Back link (design: ghost button style) --}}
<a href="{{ route('request.staff.requests.index') }}"
   class="inline-flex items-center gap-1 mb-2 -ml-2 px-2 py-1 text-sm text-blue-600 hover:text-blue-700 rounded hover:bg-gray-100 transition-colors">
    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
    Back to requests
</a>

{{-- Info bar --}}
<x-requests::info-bar>
    <x-requests::info-bar-item icon="clock" label="Submitted" :value="$patronRequest->created_at->format('M j, Y g:ia')" />
    <x-requests::info-bar-item
        :dot-color="$patronRequest->status?->color ?? '#9ca3af'"
        label="Status"
        :value="$patronRequest->status?->name ?? '—'" />
    <x-requests::info-bar-item icon="user" label="Assigned to"
        :value="$patronRequest->assignedTo?->name ?: ($patronRequest->assignedTo?->email ?? 'Unassigned')" />
</x-requests::info-bar>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- ══ Left column (2/3) ══ --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- ── Material card (design layout) ── --}}
        <x-requests::card>
            <div class="space-y-6">
                {{-- Title / Author / Synopsis row --}}
                <div class="flex gap-6">
                    {{-- Book cover --}}
                    <div class="flex-shrink-0">
                        <div class="w-40 h-60 rounded-lg overflow-hidden bg-gray-100 border border-gray-200 flex items-center justify-center">
                            @if($coverUrl)
                                <img src="{{ $coverUrl }}"
                                     alt="Cover of {{ $title }}"
                                     class="w-full h-full object-cover"
                                     onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                <div class="w-full h-full items-center justify-center" style="display:none">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                                </div>
                            @else
                                <svg class="w-12 h-12 text-gray-300" fill="none" viewBox="0 0 24 24" stroke-width="1" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                            @endif
                        </div>
                    </div>

                    <div class="flex-1 flex flex-col">
                        <h3 class="text-2xl font-semibold text-gray-900 leading-tight">{{ $title }}</h3>
                        <p class="text-base text-gray-600 mt-1 mb-3">by {{ $author }}</p>

                        @if($synopsis)
                        <div class="text-sm text-gray-600 leading-relaxed mb-3">
                            <p class="line-clamp-4">{{ Str::limit($synopsis, 280) }}</p>
                            @if(strlen($synopsis) > 280)
                                <button type="button" x-data @click="$dispatch('open-modal', 'synopsis')" class="text-blue-600 hover:text-blue-700 hover:underline text-sm font-normal mt-1">more</button>
                            @endif
                        </div>
                        @endif

                        <div class="flex items-center gap-2 mt-auto">
                            @if($patronRequest->fieldValueLabel('material_type'))
                                <x-requests::badge variant="outline">{{ $patronRequest->fieldValueLabel('material_type') }}</x-requests::badge>
                            @endif
                            @if($patronRequest->fieldValueLabel('audience'))
                                <x-requests::badge variant="purple">{{ $patronRequest->fieldValueLabel('audience') }}</x-requests::badge>
                            @endif
                            @if($patronRequest->fieldValueLabel('genre'))
                                <x-requests::badge variant="blue">{{ $patronRequest->fieldValueLabel('genre') }}</x-requests::badge>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Metadata row --}}
                <div class="flex items-center gap-6 text-[0.8125rem] text-gray-700">
                    @if($isbn)
                    <div class="flex items-center gap-1.5">
                        <x-requests::status-icon name="barcode" class="h-4 w-4 text-gray-500" />
                        <span>{{ $isbn }}</span>
                    </div>
                    @endif
                    @php $publishDate = $material?->publish_date ?? $material?->exact_publish_date?->format('Y') ?? $patronRequest->submitted_publish_date; @endphp
                    @if($publishDate)
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                        <span>{{ $publishDate }}</span>
                    </div>
                    @endif
                    @if($material?->publisher)
                    <div class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25"/></svg>
                        <span>{{ $material->publisher }}</span>
                    </div>
                    @endif
                </div>

                {{-- Catalog & Editions stat cards --}}
                <div class="grid grid-cols-2 gap-4 pt-4 border-t border-gray-200">
                    <x-requests::stat-card
                        icon="search"
                        label="Catalog"
                        :value="($patronRequest->catalog_result_count ?? 0) . ' found'"
                        :subtitle="$patronRequest->catalog_match_accepted ? 'Patron accepted' : ($patronRequest->catalog_searched ? 'Patron rejected' : 'Not searched')"
                        color="blue" />
                    <x-requests::stat-card
                        icon="book"
                        label="Editions"
                        :value="($patronRequest->isbndb_result_count ?? 0) . ' found'"
                        :subtitle="$patronRequest->isbndb_searched ? 'ISBNdb searched' : 'Not searched'"
                        color="purple"
                        :href="$material ? route('request.staff.titles.show', $material) : null" />
                </div>
            </div>
        </x-requests::card>

        {{-- ── Requested By card ── --}}
        @if($patron)
        <x-requests::card>
            <div x-data="{ showDetails: false }">
                <div class="flex items-start gap-2 mb-4">
                    <svg class="h-5 w-5 text-gray-600 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z"/></svg>
                    <h2 class="font-semibold text-gray-900 flex-1">Requested By</h2>
                    <button type="button" @click="showDetails = !showDetails"
                            class="px-2 py-1 text-sm text-gray-500 hover:text-gray-700 rounded hover:bg-gray-100">
                        <svg x-show="!showDetails" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        <svg x-show="showDetails" x-cloak class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 15.75 7.5-7.5 7.5 7.5"/></svg>
                    </button>
                </div>

                <div class="flex items-start gap-4">
                    <x-requests::avatar :name="$patron->name_first . ' ' . $patron->name_last" size="lg" />
                    <div class="flex-1">
                        <p class="font-medium text-gray-900">
                            <a href="{{ route('request.staff.patrons.show', $patron) }}" class="hover:text-blue-600 hover:underline">
                                {{ $patron->name_last }}, {{ $patron->name_first }}
                            </a>
                        </p>
                        <div class="flex items-center gap-4 mt-2 text-sm text-gray-600">
                            @if($patron->effective_email)
                            <div class="flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75"/></svg>
                                <span>{{ $patron->effective_email }}</span>
                            </div>
                            @endif
                            @if($patron->effective_phone)
                            <div class="flex items-center gap-1">
                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 6.75c0 8.284 6.716 15 15 15h2.25a2.25 2.25 0 0 0 2.25-2.25v-1.372c0-.516-.351-.966-.852-1.091l-4.423-1.106c-.44-.11-.902.055-1.173.417l-.97 1.293c-.282.376-.769.542-1.21.38a12.035 12.035 0 0 1-7.143-7.143c-.162-.441.004-.928.38-1.21l1.293-.97c.363-.271.527-.734.417-1.173L6.963 3.102a1.125 1.125 0 0 0-1.091-.852H4.5A2.25 2.25 0 0 0 2.25 4.5v2.25Z"/></svg>
                                <span>{{ $patron->effective_phone }}</span>
                            </div>
                            @endif
                        </div>

                        {{-- Collapsible details --}}
                        <div x-show="showDetails" x-transition x-cloak class="mt-4 pt-4 border-t border-gray-200 grid grid-cols-2 gap-4">
                            <div>
                                <p class="text-xs text-gray-500">Barcode</p>
                                <p class="text-sm font-medium text-gray-900 font-mono">{{ $patron->barcode }}</p>
                            </div>
                            @if($currentGroupName ?? null)
                            <div>
                                <p class="text-xs text-gray-500">Selection Type</p>
                                <p class="text-sm font-medium text-gray-900">{{ $currentGroupName }}</p>
                            </div>
                            @endif
                            @foreach($customVals as $val)
                                @if(in_array($val->field?->key, ['recommended_by', 'genre']))
                                <div>
                                    <p class="text-xs text-gray-500">{{ $val->field?->label ?? $val->field?->key }}</p>
                                    <p class="text-sm font-medium text-gray-900">{{ $fieldValueLabelByFieldId[$val->field_id] ?? ($val->value ?? '—') }}</p>
                                </div>
                                @endif
                            @endforeach
                            @if(($polarisLeapUrl ?? null) && $patron->polaris_patron_id)
                            <div class="col-span-2">
                                @php $resolvedLeapUrl = str_replace('{PatronID}', $patron->polaris_patron_id, $polarisLeapUrl); @endphp
                                <x-requests::external-link-btn :href="$resolvedLeapUrl" icon="user" label="View in Polaris Leap" />
                            </div>
                            @endif
                        </div>
                    </div>
                </div>
            </div>
        </x-requests::card>
        @endif

        {{-- ── Activity History card ── --}}
        <x-requests::card>
            <x-requests::collapsible title="Activity History" icon="clock" show-label="Show history" hide-label="Hide">
                @if($patronRequest->statusHistory->isEmpty())
                    <p class="text-sm text-gray-400">No status history.</p>
                @else
                    <div class="space-y-3 pt-2">
                        @foreach($patronRequest->statusHistory as $entry)
                        <div class="flex gap-3 text-sm">
                            <div class="flex-shrink-0 w-2 h-2 rounded-full mt-1.5"
                                 style="background-color: {{ $entry->status?->color ?? '#d1d5db' }};"></div>
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="font-medium text-gray-900">{{ $entry->status?->name ?? 'Unknown' }}</span>
                                    @if($entry->user)
                                        <span class="text-gray-500">by {{ $entry->user->name ?? $entry->user->email }}</span>
                                    @endif
                                    <span class="text-gray-400 text-xs">{{ $entry->created_at->format('M j, Y g:ia') }}</span>
                                </div>
                                @if($entry->note)
                                    <p class="text-gray-600">{{ $entry->note }}</p>
                                @endif
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </x-requests::collapsible>
        </x-requests::card>
    </div>

    {{-- ══ Right column (1/3) ══ --}}
    <div class="space-y-4">

        {{-- ── Update Status ── --}}
        <x-requests::card padding="p-5" x-data="statusUpdateForm('{{ route('request.staff.requests.preview-email', $patronRequest) }}')">
            <h3 class="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Update Status</h3>

            <form method="POST"
                  action="{{ route('request.staff.requests.status', $patronRequest) }}"
                  @submit.prevent="handleSubmit($event)"
                  x-ref="statusForm"
                  style="display:none">
                @csrf
                @method('PATCH')
                <input type="hidden" name="status_id" x-model="selectedStatusId">
                <input type="hidden" name="note" x-model="noteText">
                <input type="hidden" name="email_confirmed"    x-model="emailPayload.confirmed">
                <input type="hidden" name="email_skip"         :value="emailPayload.skip ? '1' : ''">
                <input type="hidden" name="email_subject"      :value="emailPayload.subject">
                <input type="hidden" name="email_body"         :value="emailPayload.body">
                <input type="hidden" name="email_to"           :value="emailPayload.to">
                <input type="hidden" name="email_cc"           :value="emailPayload.cc">
                <input type="hidden" name="email_bcc"          :value="emailPayload.bcc">
                <input type="hidden" name="email_copy_to_self" :value="emailPayload.copyToSelf ? '1' : ''">
            </form>

            @php
                /**
                 * Return '#fff' or '#1f2937' (gray-800) depending on background luminance.
                 * Uses WCAG relative luminance formula for AA contrast compliance.
                 */
                $contrastText = function (string $hex): string {
                    $hex = ltrim($hex, '#');
                    if (strlen($hex) === 3) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
                    [$r, $g, $b] = [hexdec(substr($hex,0,2))/255, hexdec(substr($hex,2,2))/255, hexdec(substr($hex,4,2))/255];
                    $lin = fn($c) => $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
                    $L = 0.2126 * $lin($r) + 0.7152 * $lin($g) + 0.0722 * $lin($b);
                    return $L > 0.35 ? '#1f2937' : '#ffffff';
                };
            @endphp
            <div class="space-y-2">
                @foreach($statuses as $s)
                @if($s->id === $patronRequest->request_status_id)
                <button type="button"
                        @click="selectStatus('{{ $s->id }}')"
                        :disabled="loading"
                        class="w-full flex items-center gap-2 h-11 px-4 rounded font-medium text-sm transition-colors disabled:opacity-60 ring-2 ring-offset-2"
                        style="background-color: {{ $s->color }}; color: {{ $contrastText($s->color) }}; --tw-ring-color: {{ $s->color }};">
                    @if($s->icon)
                        <x-requests::status-icon :name="$s->icon" class="w-4 h-4" />
                    @endif
                    {{ $s->name }}
                </button>
                @else
                <button type="button"
                        @click="selectStatus('{{ $s->id }}')"
                        :disabled="loading"
                        class="w-full flex items-center gap-2 h-11 px-4 rounded font-medium text-sm border-2 transition-colors disabled:opacity-60 hover:opacity-80"
                        style="color: {{ $s->color }}; border-color: {{ $s->color }};">
                    @if($s->icon)
                        <x-requests::status-icon :name="$s->icon" class="w-4 h-4" />
                    @endif
                    {{ $s->name }}
                </button>
                @endif
                @endforeach
            </div>

            <div class="mt-4 pt-4 border-t border-gray-200">
                <button type="button"
                        @click="showNote = !showNote"
                        class="w-full flex items-center gap-2 px-2 py-1.5 text-sm text-gray-600 hover:text-gray-900 rounded hover:bg-gray-50 transition-colors">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 8.25h9m-9 3H12m-9.75 1.51c0 1.6 1.123 2.994 2.707 3.227 1.129.166 2.27.293 3.423.379.35.026.67.21.865.501L12 21l2.755-4.133a1.14 1.14 0 0 1 .865-.501 48.172 48.172 0 0 0 3.423-.379c1.584-.233 2.707-1.626 2.707-3.228V6.741c0-1.602-1.123-2.995-2.707-3.228A48.394 48.394 0 0 0 12 3c-2.392 0-4.744.175-7.043.513C3.373 3.746 2.25 5.14 2.25 6.741v6.018Z"/></svg>
                    <span x-text="showNote ? 'Hide note' : 'Add internal note'"></span>
                </button>
                <textarea x-show="showNote" x-cloak x-model="noteText"
                          placeholder="Add an internal note about this decision…"
                          class="w-full mt-2 text-sm border border-gray-300 rounded px-2 py-1.5 resize-none" rows="3"></textarea>
            </div>

            @include('requests::staff.requests._email-preview-modal')
        </x-requests::card>

        {{-- ── Quick Actions ── --}}
        <x-requests::card padding="p-5">
            <h3 class="text-sm font-medium text-gray-700 uppercase tracking-wide mb-3">Quick Actions</h3>
            <div class="space-y-2">
                @if($assignmentEnabled ?? false)
                    @if(! $patronRequest->assigned_to_user_id)
                        <form method="POST" action="{{ route('request.staff.requests.claim', $patronRequest) }}">
                            @csrf
                            <x-requests::sidebar-btn icon="user" label="Claim" onclick="this.closest('form').submit()" />
                        </form>
                    @endif

                    {{-- Reassign dropdown (Groups + Users) --}}
                    <div class="relative" x-data="{ open: false }" @click.away="open = false">
                        <button type="button" @click="open = !open"
                                class="w-full flex items-center gap-2 px-3 py-2 text-sm text-gray-700 rounded hover:bg-gray-50 transition-colors">
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M7.5 21 3 16.5m0 0L7.5 12M3 16.5h13.5m0-13.5L21 7.5m0 0L16.5 12M21 7.5H7.5"/></svg>
                            <span class="flex-1 text-left">Reassign</span>
                            <svg class="h-4 w-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5"/></svg>
                        </button>

                        <div x-show="open" x-cloak x-transition
                             class="absolute left-0 right-0 top-full mt-1 bg-white border border-gray-200 rounded-lg shadow-lg z-50 max-h-64 overflow-y-auto">
                            @if($selectorGroups->isNotEmpty())
                            <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100">Groups</div>
                            @foreach($selectorGroups as $sg)
                                <form method="POST" action="{{ route('request.staff.requests.reroute', $patronRequest) }}">
                                    @csrf
                                    <input type="hidden" name="group_id" value="{{ $sg->id }}">
                                    <button type="submit" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                            onclick="if(!confirm('Reroute to {{ $sg->name }}?')) { event.preventDefault(); }">
                                        {{ $sg->name }}
                                    </button>
                                </form>
                            @endforeach
                            @endif

                            @if($staffUsers->isNotEmpty())
                            <div class="px-3 py-2 text-xs font-semibold text-gray-400 uppercase tracking-wider border-b border-gray-100 {{ $selectorGroups->isNotEmpty() ? 'border-t' : '' }}">Users</div>
                            @foreach($staffUsers as $u)
                                <form method="POST" action="{{ route('request.staff.requests.assign', $patronRequest) }}">
                                    @csrf
                                    <input type="hidden" name="assigned_to_user_id" value="{{ $u->id }}">
                                    <button type="submit" class="w-full text-left px-3 py-2 text-sm text-gray-700 hover:bg-gray-50"
                                            onclick="if(!confirm('Assign to {{ $u->name ?: $u->email }}?')) { event.preventDefault(); }">
                                        {{ $u->name ?: $u->email }}
                                    </button>
                                </form>
                            @endforeach
                            @endif
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('request.staff.requests.catalog-recheck', $patronRequest) }}">
                    @csrf
                    <x-requests::sidebar-btn icon="search" label="Re-check Catalog"
                        onclick="if(!confirm('Re-run catalog search for this request?')) return; this.closest('form').submit()" />
                </form>

                @if($material?->isbn)
                    @if($patronRequest->request_kind === 'ill' && ($illIsbnLookupUrl ?? null))
                        @php $isbnUrl = str_replace('{isbn}', $isbn, $illIsbnLookupUrl); @endphp
                        <x-requests::sidebar-btn icon="external-link" label="View on WorldCat" :href="$isbnUrl" />
                    @elseif($sfpIsbnLookupUrl ?? null)
                        @php $isbnUrl = str_replace('{isbn}', $isbn, $sfpIsbnLookupUrl); @endphp
                        <x-requests::sidebar-btn icon="external-link" label="View on Amazon" :href="$isbnUrl" />
                    @endif
                @endif


                @if($showConvertToIll)
                    <x-requests::sidebar-btn icon="arrow-right-left" label="Convert to ILL" @click="$dispatch('open-modal', 'convert-ill')" />
                @endif

                <form method="POST" action="{{ route('request.staff.requests.destroy', $patronRequest) }}">
                    @csrf
                    @method('DELETE')
                    <x-requests::sidebar-btn icon="trash" label="Delete" variant="danger"
                        onclick="if(!confirm('Delete Request #{{ $patronRequest->id }}? This cannot be undone.')) return; this.closest('form').submit()" />
                </form>
            </div>
        </x-requests::card>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- Modals (rendered outside the grid so they overlay correctly)            --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}

{{-- Synopsis modal --}}
@if($synopsis && strlen($synopsis) > 280)
<x-requests::action-modal name="synopsis" title="Synopsis" max-width="lg">
    <p class="text-sm text-gray-700 leading-relaxed whitespace-pre-line">{{ $synopsis }}</p>
</x-requests::action-modal>
@endif

{{-- Convert to ILL modal --}}
@if($showConvertToIll)
<x-requests::action-modal name="convert-ill" title="Convert to ILL">
    <form method="POST" action="{{ route('request.staff.requests.convert-kind', $patronRequest) }}" id="convert-ill-form" class="space-y-3">
        @csrf
        <input type="hidden" name="to" value="ill">
        <p class="text-sm text-gray-600">This will convert the request from SFP to Interlibrary Loan.</p>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
            <textarea name="note" rows="2" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
        </div>
    </form>
    <x-slot:footer>
        <button type="button" @click="$dispatch('close-modal', 'convert-ill')" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">Cancel</button>
        <button type="submit" form="convert-ill-form" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Convert to ILL</button>
    </x-slot:footer>
</x-requests::action-modal>
@endif

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- Scripts                                                                 --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}

<script>
function statusUpdateForm(previewUrl) {
    return {
        selectedStatusId: '',
        loading: false,
        showNote: false,
        noteText: '',

        selectStatus(statusId) {
            this.selectedStatusId = statusId;
            this.$nextTick(() => this.handleSubmit());
        },

        emailPreview: {
            show:           false,
            subject:        '',
            body:           '',
            to:             '',
            cc:             '',
            bcc:            '',
            staffEmail:     '',
            copyToSelf:     false,
            editingEnabled: false,
        },

        emailPayload: {
            confirmed:  '',
            skip:       false,
            subject:    '',
            body:       '',
            to:         '',
            cc:         '',
            bcc:        '',
            copyToSelf: false,
        },

        async handleSubmit(event) {
            const form = this.$refs.statusForm;
            if (!this.selectedStatusId) { form.submit(); return; }
            this.loading = true;
            try {
                const url = previewUrl + '?status_id=' + encodeURIComponent(this.selectedStatusId);
                const res = await fetch(url, {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (!res.ok) throw new Error('Preview request failed');
                const data = await res.json();
                if (!data.preview_enabled || !data.would_send) {
                    this.emailPayload.confirmed = '';
                    form.submit();
                    return;
                }
                this.emailPreview.subject        = data.subject        || '';
                this.emailPreview.body           = data.body           || '';
                this.emailPreview.to             = data.to             || '';
                this.emailPreview.cc             = '';
                this.emailPreview.bcc            = '';
                this.emailPreview.staffEmail     = data.staff_email    || '';
                this.emailPreview.copyToSelf     = false;
                this.emailPreview.editingEnabled = data.editing_enabled || false;
                this.emailPreview.show           = true;
            } catch (e) {
                console.error('Email preview fetch failed:', e);
                this.emailPayload.confirmed = '';
                form.submit();
            } finally {
                this.loading = false;
            }
        },

        sendWithEmail() {
            this.emailPayload.confirmed  = '1';
            this.emailPayload.skip       = false;
            this.emailPayload.subject    = this.emailPreview.subject;
            this.emailPayload.body       = this.emailPreview.body;
            this.emailPayload.to         = this.emailPreview.to;
            this.emailPayload.cc         = this.emailPreview.cc;
            this.emailPayload.bcc        = this.emailPreview.bcc;
            this.emailPayload.copyToSelf = this.emailPreview.copyToSelf;
            this.emailPreview.show       = false;
            this.$nextTick(() => this.$refs.statusForm.submit());
        },

        cancelEmail() {
            this.emailPayload.confirmed = '';
            this.emailPayload.skip      = true;
            this.emailPreview.show      = false;
            this.$nextTick(() => this.$refs.statusForm.submit());
        },

        init() {
            this.$watch('emailPreview.show', (val) => {
                if (val) this.$nextTick(() => this.loadEmailBodyIntoTrix());
            });
            document.addEventListener('trix-change', (e) => {
                if (e.target.getAttribute('input') === 'requests-email-preview-body') {
                    const input = document.getElementById('requests-email-preview-body');
                    if (input) this.emailPreview.body = input.value;
                }
            });
        },

        loadEmailBodyIntoTrix() {
            const trix = document.querySelector('trix-editor[input="requests-email-preview-body"]');
            if (trix && trix.editor) trix.editor.loadHTML(this.emailPreview.body || '');
        },
    };
}
</script>

@if(($rerouteFields ?? collect())->isNotEmpty())
<script>
function rerouteForm(previewUrl) {
    return {
        selectedGroupId: '',
        changes: [],
        noChanges: false,
        loading: false,
        _debounce: null,

        fetchPreview() {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => this._doFetch(), 150);
        },

        async _doFetch() {
            if (!this.selectedGroupId) {
                this.changes = [];
                this.noChanges = false;
                return;
            }
            this.loading = true;
            try {
                const res = await fetch(previewUrl + '?group_id=' + encodeURIComponent(this.selectedGroupId), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) {
                    const data = await res.json();
                    this.changes   = data.changes   || [];
                    this.noChanges = data.no_changes || false;
                }
            } catch (e) {
                console.error('Reroute preview failed:', e);
            } finally {
                this.loading = false;
            }
        }
    };
}
</script>
@endif
@endsection
