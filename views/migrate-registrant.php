<?php
$migrate_config = [
    'apiUrl'      => $migrate_registrant_api_url ?? add_query_arg(['action' => 'migrate-registrant-data'], $page_url),
    'executeUrl'  => $migrate_registrant_execute_url ?? add_query_arg(['action' => 'migrate-registrant-execute'], $page_url),
    'nonce'       => $migrate_registrant_nonce ?? wp_create_nonce('rm_migrate_registrant'),
    'eventId'     => (int) ($migrate_registrant_event_id ?? 0),
    'events'      => $migrate_registrant_events ?? [],
    'pageUrl'     => $page_url ?? rm_page_url(),
];
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rmMigrateRegistrant', () => ({
        loading: false,
        migratingId: 0,
        error: '',
        successMessage: '',
        eventId: <?php echo (int) $migrate_config['eventId']; ?>,
        eventSearch: '',
        showEventResults: false,
        showUnmigratedOnly: false,
        event: null,
        legacyRows: [],
        v2Rows: [],
        summary: {
            paid_total: 0,
            migrated: 0,
            ready: 0,
            conflicts: 0,
            v2_total: 0,
        },
        events: <?php echo wp_json_encode($migrate_config['events']); ?>,
        apiUrl: <?php echo wp_json_encode($migrate_config['apiUrl']); ?>,
        executeUrl: <?php echo wp_json_encode($migrate_config['executeUrl']); ?>,
        nonce: <?php echo wp_json_encode($migrate_config['nonce']); ?>,
        pageUrl: <?php echo wp_json_encode($migrate_config['pageUrl']); ?>,
        escapeHtmlTags(value) {
            return String(value ?? '')
                .replace(/<[^>]*>/g, '')
                .trim();
        },
        get filteredEvents() {
            const query = this.eventSearch.trim().toLowerCase();
            if (query === '') {
                return this.events;
            }

            return this.events.filter((event) => {
                const title = (event.title || '').toLowerCase();
                const code = (event.program_code || '').toLowerCase();
                return title.includes(query) || code.includes(query);
            });
        },
        get visibleLegacyRows() {
            if (!this.showUnmigratedOnly) {
                return this.legacyRows;
            }

            return this.legacyRows.filter((row) => row.migration_status === 'ready');
        },
        eventProfileHref(tab) {
            if (this.eventId < 1 || !this.event?.program_code) {
                return '#';
            }

            const url = new URL(this.pageUrl, window.location.origin);
            url.searchParams.set('action', 'get-event-profile');
            url.searchParams.set('event_code', this.event.program_code);
            url.searchParams.set('tab', tab);
            url.searchParams.set('event_id', String(this.eventId));

            return url.toString();
        },
        get eventRegistrantsHref() {
            return this.eventProfileHref('registrants');
        },
        get eventSettingsHref() {
            return this.eventProfileHref('settings');
        },
        async init() {
            if (this.eventId > 0) {
                this.syncEventSearchFromSelection();
                await this.load();
            }
        },
        syncEventSearchFromSelection() {
            const match = this.events.find((event) => event.id === this.eventId);
            if (!match) {
                return;
            }

            const title = this.escapeHtmlTags(match.title);
            this.eventSearch = match.program_code
                ? `${title} (${match.program_code})`
                : title;
        },
        searchEvents() {
            const query = this.eventSearch.trim();
            if (query === '') {
                this.showEventResults = false;
                return;
            }

            this.showEventResults = true;
        },
        selectEventFromSearch(event) {
            if (!event?.id) {
                return;
            }

            this.showEventResults = false;
            this.selectEvent(event.id);
        },
        selectEvent(id) {
            if (id < 1) {
                return;
            }

            const pageUrl = new URL(this.pageUrl, window.location.origin);
            pageUrl.searchParams.set('action', 'migrate-registrant');
            pageUrl.searchParams.set('event_id', String(id));
            window.location.assign(pageUrl.toString());
        },
        async load() {
            if (this.eventId < 1) {
                this.legacyRows = [];
                this.v2Rows = [];
                this.event = null;
                return;
            }

            this.loading = true;
            this.error = '';
            this.successMessage = '';

            try {
                const url = new URL(this.apiUrl, window.location.origin);
                url.searchParams.set('event_id', String(this.eventId));

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.error = data.error || 'Failed to load migration data.';
                    this.legacyRows = [];
                    this.v2Rows = [];
                    this.event = null;
                    return;
                }

                this.event = data.event || null;
                this.legacyRows = data.legacy_rows || [];
                this.v2Rows = data.v2_rows || [];
                this.summary = data.summary || this.summary;
            } catch (e) {
                this.error = 'Failed to load migration data.';
                this.legacyRows = [];
                this.v2Rows = [];
            } finally {
                this.loading = false;
            }
        },
        statusLabel(status) {
            if (status === 'migrated') {
                return 'Migrated';
            }
            if (status === 'conflict') {
                return 'Conflict';
            }
            return 'Ready';
        },
        statusClasses(status) {
            if (status === 'migrated') {
                return 'bg-emerald-100 text-emerald-800';
            }
            if (status === 'conflict') {
                return 'bg-amber-100 text-amber-800';
            }
            return 'bg-blue-100 text-blue-800';
        },
        isHighlightedV2Row(row) {
            if (!row?.legacy_registrant_id) {
                return false;
            }

            return this.legacyRows.some(
                (legacy) => legacy.legacy_id === row.legacy_registrant_id
                    && legacy.migration_status === 'migrated'
            );
        },
        confirmMigrate(row) {
            const label = row.is_group
                ? `Migrate group (${row.member_count} members) for ${row.full_name}?`
                : `Migrate ${row.full_name} (${row.order_number}) to v2?`;

            return window.confirm(label);
        },
        async migrate(row) {
            if (!row?.can_migrate || this.migratingId > 0) {
                return;
            }

            if (!this.confirmMigrate(row)) {
                return;
            }

            this.migratingId = row.legacy_id;
            this.error = '';
            this.successMessage = '';

            try {
                const body = new FormData();
                body.append('rm_migrate_registrant_nonce', this.nonce);
                body.append('event_id', String(this.eventId));
                body.append('legacy_registrant_id', String(row.legacy_id));

                const response = await fetch(this.executeUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body,
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.error = data.error || 'Migration failed.';
                    return;
                }

                this.successMessage = data.message || 'Registrant migrated successfully.';
                this.event = data.event || this.event;
                this.legacyRows = data.legacy_rows || [];
                this.v2Rows = data.v2_rows || [];
                this.summary = data.summary || this.summary;
            } catch (e) {
                this.error = 'Migration failed. Please try again.';
            } finally {
                this.migratingId = 0;
            }
        },
    }));
});
</script>

<section
    class="grid grid-cols-1 gap-6"
    x-data="rmMigrateRegistrant"
    x-init="init()"
>
    <div class="bg-white border border-slate-200 rounded-xl shadow-sm">
        <div class="p-5 border-b border-slate-200">
            <h2 class="text-lg font-semibold">Migrate Registrant</h2>
            <p class="text-sm text-slate-500 mt-1">
                Move paid legacy registrants into v2 registration records for the same event.
            </p>
        </div>

        <div class="p-5 space-y-4">
            <div class="relative flex gap-3 items-center" @click.away="showEventResults = false">
                <div class="w-full">
                    <label class="block text-sm font-medium text-slate-700 mb-2" for="migrate_event_search">
                        Search legacy event
                    </label>
                    <input
                        id="migrate_event_search"
                        type="search"
                        x-model="eventSearch"
                        @keydown.enter.prevent="searchEvents()"
                        placeholder="Search by title or program code, then press Enter..."
                        class="w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none"
                    >
                    <div
                        x-show="showEventResults"
                        x-cloak
                        class="absolute z-10 mt-1 w-full overflow-hidden rounded-lg border border-slate-200 bg-white shadow-lg"
                    >
                        <template x-if="filteredEvents.length === 0">
                            <p class="px-3 py-2 text-sm text-slate-500">No matching legacy events found.</p>
                        </template>
                        <ul x-show="filteredEvents.length > 0" class="max-h-60 overflow-y-auto py-1">
                            <template x-for="event in filteredEvents" :key="event.id">
                                <li>
                                    <button
                                        type="button"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-slate-50"
                                        :class="eventId === event.id ? 'bg-indigo-50 text-indigo-900' : 'text-slate-700'"
                                        @click="selectEventFromSearch(event)"
                                    >
                                        <span class="min-w-0 truncate" x-text="`${escapeHtmlTags(event.title)} (${event.program_code})`"></span>
                                        <span
                                            class="shrink-0 text-xs"
                                            :class="event.v2_enabled ? 'text-emerald-700' : 'text-amber-700'"
                                            x-text="event.v2_enabled ? 'V2 enabled' : 'Legacy only'"
                                        ></span>
                                    </button>
                                </li>
                            </template>
                        </ul>
                    </div>
                </div>
                <template x-if="eventId > 0 && event">
                    <div class="w-full flex justify-end">
                        <a
                            :href="eventRegistrantsHref"
                            class="inline-flex items-center gap-2 rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4 shrink-0" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                            </svg>
                            Go to Registrants
                        </a>
                    </div>
                </template>
            </div>


            <template x-if="event">
                <div class="rounded-lg border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-700 flex flex-wrap items-center gap-3">
                    <span class="font-medium" x-text="escapeHtmlTags(event.title)"></span>
                    <span class="text-slate-500" x-text="`(${event.program_code})`"></span>
                    <span
                        class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                        :class="event.v2_enabled ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                        x-text="event.v2_enabled ? 'V2 enabled' : 'V2 not enabled yet'"
                    ></span>
                    <span class="text-slate-500" x-show="summary.paid_total > 0">
                        <span x-text="summary.paid_total"></span> paid ·
                        <span x-text="summary.migrated"></span> migrated ·
                        <span x-text="summary.ready"></span> pending
                    </span>
                </div>
            </template>

            <template x-if="event && !event.v2_enabled">
                <div class="rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900">
                    V2 registration is not enabled for this event yet. You can still migrate records now,
                    then enable v2 in
                    <a :href="eventSettingsHref" class="font-medium text-indigo-600 hover:text-indigo-700">
                        Event Settings
                    </a>
                    when you are ready to switch live registration traffic.
                </div>
            </template>

            <template x-if="error">
                <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800" x-text="error"></div>
            </template>

            <template x-if="successMessage">
                <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800" x-text="successMessage"></div>
            </template>
        </div>
    </div>

    <template x-if="eventId < 1">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-8 text-center text-slate-500">
            Select a legacy event to compare paid registrants and v2 records.
        </div>
    </template>

    <template x-if="eventId > 0">
        <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">
            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col max-h-[70vh]">
                <div class="p-5 border-b border-slate-200 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 shrink-0">
                    <div>
                        <h3 class="text-base font-semibold">Legacy (paid)</h3>
                        <p class="text-sm text-slate-500 mt-1">Paid registrants from bss_registrant</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm text-slate-600">
                        <input type="checkbox" x-model="showUnmigratedOnly" class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Show unmigrated only
                    </label>
                </div>

                <div class="p-5 overflow-y-auto flex-1 min-h-0">
                    <template x-if="loading">
                        <div class="space-y-3">
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                        </div>
                    </template>

                    <template x-if="!loading && visibleLegacyRows.length === 0">
                        <p class="text-sm text-slate-500 text-center py-8">No legacy paid registrants found for this event.</p>
                    </template>

                    <div class="space-y-3" x-show="!loading && visibleLegacyRows.length > 0">
                        <template x-for="row in visibleLegacyRows" :key="row.legacy_id">
                            <div class="rounded-lg border border-slate-200 p-4">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 truncate" x-text="row.full_name"></p>
                                        <p class="text-sm text-slate-500 truncate" x-text="row.email"></p>
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                                            <span x-text="`Order: ${row.order_number || 'N/A'}`"></span>
                                            <span x-text="row.amount_display"></span>
                                            <span x-text="row.registered_at"></span>
                                            <template x-if="row.is_group">
                                                <span x-text="`${row.member_count} members`"></span>
                                            </template>
                                        </div>
                                    </div>
                                    <span
                                        class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                        :class="statusClasses(row.migration_status)"
                                        x-text="statusLabel(row.migration_status)"
                                    ></span>
                                </div>
                                <div class="mt-4 flex justify-end">
                                    <button
                                        type="button"
                                        class="inline-flex items-center rounded-lg bg-indigo-600 px-3 py-2 text-sm font-medium text-white hover:bg-indigo-700 disabled:opacity-50 disabled:cursor-not-allowed"
                                        :disabled="!row.can_migrate || migratingId > 0"
                                        @click="migrate(row)"
                                    >
                                        <span x-show="migratingId !== row.legacy_id">Migrate to v2</span>
                                        <span x-show="migratingId === row.legacy_id">Migrating...</span>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>

            <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden flex flex-col max-h-[70vh]">
                <div class="p-5 border-b border-slate-200 shrink-0">
                    <h3 class="text-base font-semibold">V2 registrants</h3>
                    <p class="text-sm text-slate-500 mt-1">Records in event_registration / event_registrant</p>
                </div>

                <div class="p-5 overflow-y-auto flex-1 min-h-0">
                    <template x-if="loading">
                        <div class="space-y-3">
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                            <div class="h-16 rounded-lg bg-slate-100 animate-pulse"></div>
                        </div>
                    </template>

                    <template x-if="!loading && v2Rows.length === 0">
                        <p class="text-sm text-slate-500 text-center py-8">No v2 registrants yet for this event.</p>
                    </template>

                    <div class="space-y-3" x-show="!loading && v2Rows.length > 0">
                        <template x-for="row in v2Rows" :key="row.registrant_id">
                            <div
                                class="rounded-lg border p-4"
                                :class="isHighlightedV2Row(row) ? 'border-emerald-300 bg-emerald-50/40' : 'border-slate-200'"
                            >
                                <div class="flex items-start justify-between gap-3">
                                    <div class="min-w-0">
                                        <p class="font-medium text-slate-900 truncate" x-text="row.full_name"></p>
                                        <p class="text-sm text-slate-500 truncate" x-text="row.email"></p>
                                        <div class="mt-2 flex flex-wrap gap-2 text-xs text-slate-600">
                                            <span x-text="`Order: ${row.order_number || 'N/A'}`"></span>
                                            <span x-text="row.amount_display"></span>
                                            <span x-text="row.date_display || '—'"></span>
                                        </div>
                                    </div>
                                    <div class="flex flex-col items-end gap-1">
                                        <span
                                            class="inline-flex shrink-0 items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
                                            :class="row.is_paid ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-700'"
                                            x-text="row.payment_status"
                                        ></span>
                                        <template x-if="row.is_migrated">
                                            <span class="text-xs text-emerald-700">From legacy</span>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </div>
    </template>
</section>
