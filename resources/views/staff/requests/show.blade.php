@extends('requests::staff._layout')

@section('title', 'Request #' . $patronRequest->id)

@section('content')
@if($justClaimed ?? false)
<div class="mb-4 text-sm text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-3 py-2">
    This request was automatically assigned to you.
</div>
@endif

<div class="mb-6 flex items-center gap-3">
    <a href="{{ route('request.staff.requests.index') }}" class="text-sm text-blue-600 hover:underline">&larr; Back to requests</a>
    <span class="text-gray-300">/</span>
    <h1 class="text-xl font-bold text-gray-900">Request #{{ $patronRequest->id }}</h1>
    <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium {{ $patronRequest->request_kind === 'ill' ? 'bg-purple-50 text-purple-700' : 'bg-blue-50 text-blue-700' }}">
        {{ strtoupper($patronRequest->request_kind ?? 'sfp') }}
    </span>
    @if($patronRequest->status)
        <span class="inline-block px-2 py-0.5 rounded text-xs font-medium"
              style="background-color: {{ $patronRequest->status->color }}22; color: {{ $patronRequest->status->color }};">
            {{ $patronRequest->status->name }}
        </span>
    @endif
    <span class="text-xs text-gray-400">{{ $patronRequest->created_at->format('M j, Y g:ia') }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Main info --}}
    <div class="lg:col-span-2 space-y-6">

        {{-- Material --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">
                {{ $patronRequest->request_kind === 'ill' ? 'ILL Summary' : 'Material' }}
            </h2>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Type</dt>
                    <dd class="font-medium">{{ $patronRequest->fieldValueLabel('material_type') ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Audience</dt>
                    <dd class="font-medium">{{ $patronRequest->fieldValueLabel('audience') ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Title</dt>
                    <dd class="font-medium">{{ $patronRequest->material?->title ?? $patronRequest->submitted_title ?? '—' }}</dd>
                </div>
                <div class="col-span-2">
                    <dt class="text-gray-500">Author</dt>
                    <dd class="font-medium">{{ $patronRequest->material?->author ?? $patronRequest->submitted_author ?? '—' }}</dd>
                </div>
                @if($patronRequest->submitted_publish_date)
                <div>
                    <dt class="text-gray-500">Pub. Date (submitted)</dt>
                    <dd class="font-medium">{{ $patronRequest->submitted_publish_date }}</dd>
                </div>
                @endif
                @if($patronRequest->material?->publish_date)
                <div>
                    <dt class="text-gray-500">Pub. Date (ISBNdb)</dt>
                    <dd class="font-medium">{{ $patronRequest->material->publish_date }}</dd>
                </div>
                @endif
                @if($patronRequest->material?->isbn)
                <div>
                    <dt class="text-gray-500">ISBN</dt>
                    <dd class="font-mono font-medium">{{ $patronRequest->material->isbn13 ?? $patronRequest->material->isbn }}</dd>
                </div>
                @endif
                @if($patronRequest->material?->publisher)
                <div>
                    <dt class="text-gray-500">Publisher</dt>
                    <dd class="font-medium">{{ $patronRequest->material->publisher }}</dd>
                </div>
                @endif
                @if($patronRequest->other_material_text)
                <div class="col-span-2">
                    <dt class="text-gray-500">Other / Notes</dt>
                    <dd class="font-medium">{{ $patronRequest->other_material_text }}</dd>
                </div>
                @endif
            </dl>
            @if($patronRequest->material)
                <div class="mt-3 pt-3 border-t border-gray-100 flex items-center gap-3">
                    <a href="{{ route('request.staff.titles.show', $patronRequest->material) }}"
                       class="text-xs text-blue-600 hover:underline">View full title details →</a>
                    @if($patronRequest->material->isbn)
                        @if($patronRequest->request_kind === 'ill' && ($illIsbnLookupUrl ?? null))
                            @php $isbnUrl = str_replace('{isbn}', $patronRequest->material->isbn13 ?? $patronRequest->material->isbn, $illIsbnLookupUrl); @endphp
                            <x-requests::external-link-btn :href="$isbnUrl" icon="globe" label="View on WorldCat" />
                        @elseif(($sfpIsbnLookupUrl ?? null))
                            @php $isbnUrl = str_replace('{isbn}', $patronRequest->material->isbn13 ?? $patronRequest->material->isbn, $sfpIsbnLookupUrl); @endphp
                            <x-requests::external-link-btn :href="$isbnUrl" icon="cart" label="View on Amazon" />
                        @endif
                    @endif
                </div>
            @endif
        </div>

        @if($patronRequest->request_kind === 'sfp')
            @php
                $coreKeys = ['material_type', 'audience', 'title', 'author', 'publish_date', 'isbn'];
                $sfpCustomVals = $patronRequest->fieldValues->filter(fn ($v) =>
                    $v->field && ! in_array($v->field->key, $coreKeys)
                    && in_array($v->field->scope, ['sfp', 'both'])
                )->sortBy(fn ($v) => $v->field?->sort_order ?? 9999)->values();
            @endphp
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">SFP Details</h2>
            @if($sfpCustomVals->isNotEmpty())
            <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                @foreach($sfpCustomVals as $val)
                    <div class="md:col-span-1">
                        <dt class="text-gray-500">{{ $val->field?->label ?? $val->field?->key ?? 'Field' }}</dt>
                        <dd class="font-medium">
                            {{ $fieldValueLabelByFieldId[$val->field_id] ?? ($val->value ?? '—') }}
                        </dd>
                    </div>
                @endforeach
            </dl>
            @else
                <p class="text-sm text-gray-400">No custom field values recorded.</p>
            @endif
            <x-requests::patron-info :patron="$patronRequest->patron" :leap-url="$polarisLeapUrl ?? null" />
        </div>
        @endif

        @if($patronRequest->request_kind === 'ill')
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">ILL Details</h2>
            @php
                $coreKeys = $coreKeys ?? ['material_type', 'audience', 'title', 'author', 'publish_date', 'isbn'];
                $vals = $patronRequest->fieldValues->filter(fn ($v) =>
                    $v->field && ! in_array($v->field->key, $coreKeys)
                )->sortBy(fn($v) => $v->field?->sort_order ?? 9999)->values();
            @endphp
            @if($vals->isEmpty())
                <p class="text-sm text-gray-400">No custom field values recorded.</p>
            @else
                <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-4 gap-y-3 text-sm">
                    @foreach($vals as $val)
                        <div class="md:col-span-1">
                            <dt class="text-gray-500">{{ $val->field?->label ?? $val->field?->key ?? 'Field' }}</dt>
                            <dd class="font-medium">
                                {{ $fieldValueLabelByFieldId[$val->field_id] ?? ($val->value ?? '—') }}
                            </dd>
                        </div>
                    @endforeach
                </dl>
            @endif
            <x-requests::patron-info :patron="$patronRequest->patron" :leap-url="$polarisLeapUrl ?? null" />
        </div>
        @endif

        {{-- Catalog / ILL info --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <div class="flex items-center justify-between mb-3">
                <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Catalog &amp; ILL</h2>
                <form method="POST" action="{{ route('request.staff.requests.catalog-recheck', $patronRequest) }}">
                    @csrf
                    <button type="submit"
                            class="text-xs px-2 py-1 rounded border border-gray-300 text-gray-600 hover:bg-gray-50"
                            onclick="return confirm('Re-run catalog search for this request?')">
                        ↺ Re-check catalog
                    </button>
                </form>
            </div>
            <dl class="grid grid-cols-2 gap-x-4 gap-y-3 text-sm">
                <div>
                    <dt class="text-gray-500">Catalog searched</dt>
                    <dd>{{ $patronRequest->catalog_searched ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Catalog results</dt>
                    <dd>{{ $patronRequest->catalog_result_count ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">Patron accepted catalog match</dt>
                    <dd>{{ $patronRequest->catalog_match_accepted ? 'Yes' : 'No' }}</dd>
                </div>
                @if($patronRequest->catalog_match_bib_id)
                <div>
                    <dt class="text-gray-500">Bib ID</dt>
                    <dd>
                        <a href="https://dcpl.bibliocommons.com/v2/record/{{ $patronRequest->catalog_match_bib_id }}"
                           target="_blank"
                           class="font-mono text-blue-600 hover:underline">
                            {{ $patronRequest->catalog_match_bib_id }}
                        </a>
                    </dd>
                </div>
                @endif
                <div>
                    <dt class="text-gray-500">ISBNdb searched</dt>
                    <dd>{{ $patronRequest->isbndb_searched ? 'Yes' : 'No' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">ISBNdb results</dt>
                    <dd>{{ $patronRequest->isbndb_result_count ?? '—' }}</dd>
                </div>
                <div>
                    <dt class="text-gray-500">ILL requested</dt>
                    <dd>{{ $patronRequest->ill_requested ? 'Yes' : 'No' }}</dd>
                </div>
                @if($patronRequest->is_duplicate)
                <div>
                    <dt class="text-gray-500">Duplicate of</dt>
                    <dd>
                        @if($patronRequest->duplicateOf)
                            <a href="{{ route('request.staff.requests.show', $patronRequest->duplicateOf) }}" class="text-blue-600 hover:underline">
                                #{{ $patronRequest->duplicate_of_request_id }}
                            </a>
                        @else
                            #{{ $patronRequest->duplicate_of_request_id }}
                        @endif
                    </dd>
                </div>
                @endif
            </dl>
        </div>

        {{-- Status history --}}
        <div class="bg-white rounded-lg border border-gray-200 p-5">
            <h2 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Status History</h2>
            @if($patronRequest->statusHistory->isEmpty())
                <p class="text-sm text-gray-400">No status history.</p>
            @else
                <ol class="space-y-3">
                    @foreach($patronRequest->statusHistory as $entry)
                    <li class="flex gap-3 text-sm">
                        <div class="mt-1 w-2 h-2 rounded-full bg-gray-300 flex-shrink-0"></div>
                        <div>
                            <span class="font-medium">{{ $entry->status?->name ?? 'Unknown' }}</span>
                            @if($entry->user)
                                <span class="text-gray-500"> by {{ $entry->user->name ?? $entry->user->email }}</span>
                            @endif
                            <span class="text-gray-400 text-xs ml-2">{{ $entry->created_at->format('M j, Y g:ia') }}</span>
                            @if($entry->note)
                                <div class="text-gray-600 mt-0.5">{{ $entry->note }}</div>
                            @endif
                        </div>
                    </li>
                    @endforeach
                </ol>
            @endif
        </div>
    </div>

    {{-- Actions panel --}}
    <div class="bg-white rounded-lg border border-gray-200 overflow-hidden" x-data>
        <div class="px-5 py-3 bg-gray-50 border-b border-gray-200">
            <h2 class="text-sm font-semibold text-gray-700">Actions</h2>
        </div>

        {{-- ── Update Status ── --}}
        <div class="px-5 py-4" x-data="statusUpdateForm('{{ route('request.staff.requests.preview-email', $patronRequest) }}')">
            <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Update Status</h3>
            <form method="POST"
                  action="{{ route('request.staff.requests.status', $patronRequest) }}"
                  @submit.prevent="handleSubmit($event)"
                  x-ref="statusForm">
                @csrf
                @method('PATCH')
                <div class="mb-2">
                    <select name="status_id" required x-model="selectedStatusId"
                            class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        <option value="">Select…</option>
                        @foreach($statuses as $s)
                            <option value="{{ $s->id }}" {{ $patronRequest->request_status_id == $s->id ? 'selected' : '' }}>
                                {{ $s->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="mb-2">
                    <textarea name="note" rows="2" placeholder="Note (optional)" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
                </div>

                <input type="hidden" name="email_confirmed"    x-model="emailPayload.confirmed">
                <input type="hidden" name="email_skip"         :value="emailPayload.skip ? '1' : ''">
                <input type="hidden" name="email_subject"      :value="emailPayload.subject">
                <input type="hidden" name="email_body"         :value="emailPayload.body">
                <input type="hidden" name="email_to"           :value="emailPayload.to">
                <input type="hidden" name="email_cc"           :value="emailPayload.cc">
                <input type="hidden" name="email_bcc"          :value="emailPayload.bcc">
                <input type="hidden" name="email_copy_to_self" :value="emailPayload.copyToSelf ? '1' : ''">

                <button type="submit"
                        :disabled="loading"
                        class="w-full px-4 py-2 bg-blue-600 text-white text-sm rounded hover:bg-blue-700 disabled:opacity-60">
                    <span x-show="!loading">Update Status</span>
                    <span x-show="loading" x-cloak>Checking…</span>
                </button>
            </form>
            @include('requests::staff.requests._email-preview-modal')
        </div>

        {{-- ── Assignment ── --}}
        @if($assignmentEnabled ?? false)
        <div class="px-5 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Assignment</h3>
                    @if($patronRequest->assignedTo)
                        <p class="text-sm text-gray-900 mt-1">{{ $patronRequest->assignedTo->name ?: $patronRequest->assignedTo->email }}</p>
                    @else
                        <p class="text-sm text-gray-400 mt-1">Unassigned</p>
                    @endif
                </div>
                <div class="flex items-center gap-2">
                    @if(! $patronRequest->assigned_to_user_id)
                        <form method="POST" action="{{ route('request.staff.requests.claim', $patronRequest) }}">
                            @csrf
                            <button type="submit"
                                    class="px-3 py-1.5 bg-emerald-600 text-white text-xs rounded hover:bg-emerald-700">
                                Claim
                            </button>
                        </form>
                    @endif
                    <button type="button"
                            @click="$dispatch('open-modal', 'reassign')"
                            class="px-3 py-1.5 bg-blue-600 text-white text-xs rounded hover:bg-blue-700">
                        Reassign
                    </button>
                </div>
            </div>
        </div>
        @endif

        {{-- ── Reroute ── --}}
        @if(($assignmentEnabled ?? false) && ($rerouteFields ?? collect())->isNotEmpty())
        <div class="px-5 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Reroute</h3>
                    <div class="flex flex-wrap gap-1 mt-1">
                        @foreach($rerouteFields as $rf)
                            @php $currentLabel = $rf->options->firstWhere('slug', $patronRequest->fieldValue($rf->key))?->name; @endphp
                            @if($currentLabel)
                                <span class="inline-block px-1.5 py-0.5 rounded text-xs bg-gray-100 text-gray-600">{{ $currentLabel }}</span>
                            @endif
                        @endforeach
                    </div>
                </div>
                <button type="button"
                        @click="$dispatch('open-modal', 'reroute')"
                        class="px-3 py-1.5 bg-amber-600 text-white text-xs rounded hover:bg-amber-700">
                    Reroute
                </button>
            </div>
        </div>
        @endif

        {{-- ── Convert to ILL ── --}}
        @if($showConvertToIll)
        <div class="px-5 py-4 border-t border-gray-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Workflow</h3>
                    <span class="inline-block mt-1 px-1.5 py-0.5 rounded text-xs bg-purple-50 text-purple-700">ILL eligible</span>
                </div>
                <button type="button"
                        @click="$dispatch('open-modal', 'convert-ill')"
                        class="px-3 py-1.5 bg-purple-600 text-white text-xs rounded hover:bg-purple-700">
                    Convert to ILL
                </button>
            </div>
        </div>
        @endif

        {{-- ── Danger zone ── --}}
        <div class="px-5 py-4 border-t border-red-200">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xs font-semibold text-red-600 uppercase tracking-wide">Danger Zone</h3>
                </div>
                <form method="POST" action="{{ route('request.staff.requests.destroy', $patronRequest) }}">
                    @csrf
                    @method('DELETE')
                    <button type="submit"
                            class="px-3 py-1.5 bg-red-600 text-white text-xs rounded hover:bg-red-700"
                            onclick="return confirm('Delete Request #{{ $patronRequest->id }}? This cannot be undone.')">
                        Delete
                    </button>
                </form>
            </div>
            <p class="mt-1 text-xs text-gray-400">Removes the request and its status history.</p>
        </div>
    </div>
</div>

{{-- ════════════════════════════════════════════════════════════════════════ --}}
{{-- Modals (rendered outside the grid so they overlay correctly)            --}}
{{-- ════════════════════════════════════════════════════════════════════════ --}}

{{-- Reassign modal --}}
@if($assignmentEnabled ?? false)
<x-requests::action-modal name="reassign" title="Reassign Request">
    <form method="POST" action="{{ route('request.staff.requests.assign', $patronRequest) }}" id="reassign-form" class="space-y-3">
        @csrf
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Assign to</label>
            <select name="assigned_to_user_id" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                <option value="">Unassigned</option>
                @foreach($staffUsers as $u)
                    <option value="{{ $u->id }}" @selected($patronRequest->assigned_to_user_id == $u->id)>
                        {{ $u->name ?: $u->email }} ({{ $u->email }})
                    </option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
            <textarea name="note" rows="2" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
        </div>
    </form>
    <x-slot:footer>
        <button type="button" @click="$dispatch('close-modal', 'reassign')" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">Cancel</button>
        <button type="submit" form="reassign-form" class="px-4 py-2 text-sm text-white bg-blue-600 rounded hover:bg-blue-700">Save Assignment</button>
    </x-slot:footer>
</x-requests::action-modal>
@endif

{{-- Reroute modal --}}
@if(($assignmentEnabled ?? false) && ($rerouteFields ?? collect())->isNotEmpty())
<x-requests::action-modal name="reroute" title="Reroute Request" max-width="lg">
    <div x-data="rerouteForm('{{ route('request.staff.requests.reroute-preview', $patronRequest) }}')">
        <p class="text-xs text-gray-400 mb-3">Change fields to send this request to a different group. It will be unassigned and auto-claimed by the next person who opens it.</p>
        <form method="POST" action="{{ route('request.staff.requests.reroute', $patronRequest) }}" id="reroute-form" class="space-y-3">
            @csrf
            @foreach($rerouteFields as $rf)
                <div>
                    <label class="block text-xs font-medium text-gray-600 mb-1">{{ $rf->label }}</label>
                    <select name="fields[{{ $rf->key }}]" x-model="fields.{{ $rf->key }}" @change="fetchPreview()"
                            class="w-full text-sm border border-gray-300 rounded px-2 py-1.5">
                        @foreach($rf->options as $opt)
                            <option value="{{ $opt->slug }}" @selected($patronRequest->fieldValue($rf->key) === $opt->slug)>
                                {{ $opt->name }}
                            </option>
                        @endforeach
                    </select>
                </div>
            @endforeach

            <div class="text-xs rounded px-3 py-2" :class="previewGroups.length ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-amber-50 text-amber-700 border border-amber-200'">
                <template x-if="previewLoading"><span class="text-gray-400">Checking…</span></template>
                <template x-if="!previewLoading && previewGroups.length">
                    <span>
                        <svg class="inline w-3.5 h-3.5 mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M18 18.72a9.094 9.094 0 0 0 3.741-.479 3 3 0 0 0-4.682-2.72m.94 3.198.001.031c0 .225-.012.447-.037.666A11.944 11.944 0 0 1 12 21c-2.17 0-4.207-.576-5.963-1.584A6.062 6.062 0 0 1 6 18.719m12 0a5.971 5.971 0 0 0-.941-3.197m0 0A5.995 5.995 0 0 0 12 12.75a5.995 5.995 0 0 0-5.058 2.772m0 0a3 3 0 0 0-4.681 2.72 8.986 8.986 0 0 0 3.74.477m.94-3.197a5.971 5.971 0 0 0-.94 3.197M15 6.75a3 3 0 1 1-6 0 3 3 0 0 1 6 0Zm6 3a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Zm-13.5 0a2.25 2.25 0 1 1-4.5 0 2.25 2.25 0 0 1 4.5 0Z"/></svg>
                        Will be visible to: <span class="font-medium" x-text="previewGroups.map(g => g.name).join(', ')"></span>
                    </span>
                </template>
                <template x-if="!previewLoading && !previewGroups.length">
                    <span>
                        <svg class="inline w-3.5 h-3.5 mr-0.5 -mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        No groups match this combination
                    </span>
                </template>
            </div>

            <div>
                <label class="block text-xs font-medium text-gray-600 mb-1">Note (optional)</label>
                <textarea name="note" rows="2" class="w-full text-sm border border-gray-300 rounded px-2 py-1.5 resize-none"></textarea>
            </div>
        </form>
    </div>
    <x-slot:footer>
        <button type="button" @click="$dispatch('close-modal', 'reroute')" class="px-4 py-2 text-sm text-gray-700 bg-white border border-gray-300 rounded hover:bg-gray-100">Cancel</button>
        <button type="submit" form="reroute-form" class="px-4 py-2 text-sm text-white bg-amber-600 rounded hover:bg-amber-700"
                onclick="return confirm('Reroute this request? It will be unassigned and sent to the matching group.')">
            Reroute &amp; Unassign
        </button>
    </x-slot:footer>
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
        <button type="submit" form="convert-ill-form" class="px-4 py-2 text-sm text-white bg-purple-600 rounded hover:bg-purple-700">Convert to ILL</button>
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

@if(($assignmentEnabled ?? false) && ($rerouteFields ?? collect())->isNotEmpty())
<script>
function rerouteForm(previewUrl) {
    return {
        fields: {
            @foreach($rerouteFields as $rf)
                {{ $rf->key }}: '{{ $patronRequest->fieldValue($rf->key) ?? '' }}',
            @endforeach
        },
        previewGroups: [],
        previewLoading: false,
        _debounce: null,

        init() { this.fetchPreview(); },

        fetchPreview() {
            clearTimeout(this._debounce);
            this._debounce = setTimeout(() => this._doFetch(), 200);
        },

        async _doFetch() {
            this.previewLoading = true;
            try {
                const params = new URLSearchParams();
                for (const [k, v] of Object.entries(this.fields)) { if (v) params.set(k, v); }
                const res = await fetch(previewUrl + '?' + params.toString(), {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                if (res.ok) { const data = await res.json(); this.previewGroups = data.groups || []; }
            } catch (e) {
                console.error('Reroute preview failed:', e);
            } finally {
                this.previewLoading = false;
            }
        }
    };
}
</script>
@endif
@endsection
