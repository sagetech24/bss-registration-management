<?php
$payment_environment = $payment_environment ?? 'test';
$payment_transactions_api_url = $payment_transactions_api_url ?? add_query_arg(['action' => 'payment-transactions-data'], $page_url);
$payment_transactions_initial_page = (int) ($payment_transactions_initial_page ?? 1);
$payment_transactions_event_id = (int) ($payment_transactions_event_id ?? 0);
$environment_label = $payment_environment === 'live' ? 'Live' : 'Sandbox';
$payment_transactions_config = [
    'environmentLabel' => $environment_label,
    'apiUrl'           => esc_url_raw($payment_transactions_api_url),
    'initialPage'      => $payment_transactions_initial_page,
    'eventId'          => $payment_transactions_event_id,
];
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rmPaymentTransactions', () => ({
        modalOpen: false,
        eventModalOpen: false,
        activeTx: null,
        activeEvent: null,
        loading: true,
        error: '',
        rows: [],
        summary: { total: 0, succeeded_count: 0, total_amount: 0 },
        pagination: {
            current_page: 1,
            total_pages: 1,
            per_page: 10,
            total: 0,
            has_prev: false,
            has_next: false,
            from: 0,
            to: 0,
        },
        environmentLabel: <?php echo wp_json_encode($payment_transactions_config['environmentLabel']); ?>,
        apiUrl: <?php echo wp_json_encode($payment_transactions_config['apiUrl']); ?>,
        initialPage: <?php echo (int) $payment_transactions_config['initialPage']; ?>,
        eventId: <?php echo (int) $payment_transactions_config['eventId']; ?>,
        async init() {
            const params = new URLSearchParams(window.location.search);
            const page = Math.max(
                1,
                parseInt(params.get('tx_page') || String(this.initialPage), 10) || 1
            );
            await this.load(page);
        },
        async load(page) {
            this.loading = true;
            this.error = '';

            try {
                const url = new URL(this.apiUrl, window.location.origin);
                url.searchParams.set('tx_page', String(page));

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.error = data.error || 'Failed to load payment transactions from HitPay.';
                    this.rows = [];
                    return;
                }

                this.rows = data.rows || [];
                this.summary = data.summary || { total: 0, succeeded_count: 0, total_amount: 0 };
                this.pagination = data.pagination || this.pagination;
                this.environmentLabel = data.environment === 'live' ? 'Live' : 'Sandbox';

                const pageUrl = new URL(window.location.href);
                pageUrl.searchParams.set('action', 'payment-transactions');
                pageUrl.searchParams.set('tx_page', String(this.pagination.current_page || page));
                if (this.eventId > 0) {
                    pageUrl.searchParams.set('event_id', String(this.eventId));
                } else {
                    pageUrl.searchParams.delete('event_id');
                }
                window.history.replaceState({}, '', pageUrl.toString());
            } catch (e) {
                this.error = 'Failed to load payment transactions from HitPay.';
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },
        goToPage(page) {
            if (this.loading || page < 1 || page > this.pagination.total_pages) {
                return;
            }

            this.load(page);
        },
        openModal(row) {
            this.activeTx = row;
            this.modalOpen = true;
        },
        openEventModal(event) {
            if (!event?.exists) {
                return;
            }

            this.activeEvent = event;
            this.eventModalOpen = true;
        },
        closeModals() {
            this.modalOpen = false;
            this.eventModalOpen = false;
        },
        formatAmount(value, currency) {
            const amount = Number(value || 0);
            const cur = currency || 'SGD';
            return cur + ' ' + amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
        formatCount(value) {
            return Number(value || 0).toLocaleString();
        },
    }));
});
</script>

<section
    class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start"
    x-data="rmPaymentTransactions()"
    @keydown.escape.window="closeModals()"
>
    <div class="lg:col-span-12 space-y-6">
        <div class="bg-white border border-slate-200 rounded-xl shadow-sm relative">
            <div class="p-5 border-b border-slate-200">
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold">Payment Transactions</h2>
                        <p class="text-sm text-slate-500 mt-1">
                            HitPay charges with registration reference numbers (RM-).
                            <span
                                class="inline-flex rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700 ml-1"
                                x-text="environmentLabel"
                            ></span>
                        </p>
                    </div>
                </div>
            </div>

            <div x-show="loading" class="p-10">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="relative size-12">
                        <div class="absolute inset-0 rounded-full border-4 border-slate-200"></div>
                        <div class="absolute inset-0 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-700">Loading transactions from HitPay...</p>
                    <p class="mt-1 text-xs text-slate-500">Fetching charges with RM- reference numbers.</p>
                </div>

                <div class="mt-8 space-y-3 animate-pulse">
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <div class="h-12 rounded-lg bg-slate-100"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <div x-show="!loading && error !== ''" x-cloak class="p-5" style="display: none;">
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800" x-text="error"></div>
                <button
                    type="button"
                    class="mt-4 rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition"
                    @click="load(pagination.current_page || 1)"
                >
                    Try again
                </button>
            </div>

            <div x-show="!loading && error === ''" x-cloak style="display: none;">
                <div class="p-5 border-b border-slate-200">
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div class="rounded-xl border border-slate-200 bg-slate-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total transactions</p>
                            <p class="mt-2 text-3xl font-bold text-slate-900" x-text="formatCount(summary.total)"></p>
                        </div>
                        <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Succeeded</p>
                            <p class="mt-2 text-3xl font-bold text-emerald-800" x-text="formatCount(summary.succeeded_count)"></p>
                        </div>
                        <div class="rounded-xl border border-blue-200 bg-blue-50 p-4">
                            <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Succeeded amount</p>
                            <p class="mt-2 text-3xl font-bold text-blue-800" x-text="formatAmount(summary.total_amount)"></p>
                        </div>
                    </div>
                </div>

                <template x-if="rows.length === 0">
                    <div class="p-6 text-slate-600 text-center">
                        <h3 class="text-lg font-semibold text-slate-700">No transactions found</h3>
                        <p class="mt-1 text-sm text-slate-500">No HitPay charges with RM- reference numbers were returned.</p>
                    </div>
                </template>

                <template x-if="rows.length > 0">
                    <div>
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-slate-200">
                                <thead class="bg-slate-50">
                                    <tr>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Customer</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Contact Info</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Event</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Method</th>
                                        <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Date/Time</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-100 bg-white">
                                    <template x-for="row in rows" :key="row.charge_id || row.payment_request_id">
                                        <tr class="hover:bg-slate-50/80">
                                            <td class="px-4 py-3 text-sm">
                                                <p class="font-medium text-slate-800 capitalize" x-text="row.customer_name"></p>
                                                <template x-if="row.payment_request_id">
                                                    <button
                                                        type="button"
                                                        class="text-left text-[11px] text-indigo-600 hover:text-indigo-800 hover:underline truncate max-w-[12rem] font-mono mt-0.5"
                                                        title="View payment details"
                                                        @click="openModal(row)"
                                                        x-text="row.payment_request_id"
                                                    ></button>
                                                </template>
                                                <template x-if="!row.payment_request_id">
                                                    <p class="text-xs text-slate-500 mt-1">N/A</p>
                                                </template>
                                            </td>
                                            <td class="px-4 py-3 text-sm text-slate-600">
                                                <p class="text-[11px] flex gap-1 items-center">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                                    </svg>
                                                    <span x-text="row.customer_email"></span>
                                                </p>
                                                <p class="text-[11px] flex gap-1 items-center text-slate-500">
                                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                                    </svg>
                                                    <span x-text="row.customer_phone"></span>
                                                </p>
                                            </td>
                                            <td class="px-4 py-3 text-sm">
                                                <template x-if="row.event_id > 0 && row.event?.exists">
                                                    <button
                                                        type="button"
                                                        class="text-left font-medium text-indigo-700 hover:text-indigo-900 hover:underline text-xs"
                                                        title="View event details"
                                                        @click="openEventModal(row.event)"
                                                        x-html="row.event.title"
                                                    ></button>
                                                </template>
                                                <template x-if="row.event_id > 0 && row.event && !row.event.exists">
                                                    <div>
                                                        <p class="font-medium text-slate-800">#<span x-text="row.event_id"></span></p>
                                                        <p class="text-[11px] text-rose-600 mt-0.5" x-text="row.event.error || 'Event not found.'"></p>
                                                    </div>
                                                </template>
                                                <template x-if="row.event_id < 1">
                                                    <p class="font-medium text-slate-500 text-xs">No event associated to this transaction.</p>
                                                </template>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-xs">
                                                <span
                                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                                    :class="row.is_succeeded ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                                    x-text="row.status"
                                                ></span>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-700" x-text="row.amount_display"></td>
                                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600">
                                                <div class="flex items-center gap-2">
                                                    <img
                                                        x-show="row.payment_method_logo"
                                                        :src="row.payment_method_logo"
                                                        :alt="row.payment_method || 'Payment method'"
                                                        class="h-6 w-auto object-contain shrink-0"
                                                    >
                                                    <span x-text="row.payment_method"></span>
                                                </div>
                                            </td>
                                            <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600" x-text="row.paid_display"></td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                        </div>

                        <div
                            x-show="pagination.total_pages > 1"
                            class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 border-t border-slate-200 px-4 py-4"
                        >
                            <p class="text-sm text-slate-600">
                                Showing
                                <span class="font-semibold text-slate-900" x-text="pagination.from"></span>
                                –
                                <span class="font-semibold text-slate-900" x-text="pagination.to"></span>
                                of
                                <span class="font-semibold text-slate-900" x-text="formatCount(pagination.total)"></span>
                            </p>
                            <div class="flex items-center gap-2">
                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                                    :class="pagination.has_prev && !loading ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'"
                                    :disabled="!pagination.has_prev || loading"
                                    @click="goToPage(pagination.current_page - 1)"
                                >
                                    Previous
                                </button>

                                <span class="text-sm text-slate-600 px-2">
                                    Page <span x-text="pagination.current_page"></span>
                                    of <span x-text="pagination.total_pages"></span>
                                </span>

                                <button
                                    type="button"
                                    class="rounded-lg border px-3 py-2 text-sm font-medium transition"
                                    :class="pagination.has_next && !loading ? 'border-slate-300 bg-white text-slate-700 hover:bg-slate-50' : 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'"
                                    :disabled="!pagination.has_next || loading"
                                    @click="goToPage(pagination.current_page + 1)"
                                >
                                    Next
                                </button>
                            </div>
                        </div>
                    </div>
                </template>
            </div>
        </div>
    </div>

    <div
        x-show="modalOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50"
        style="display: none;"
        @click.self="closeModals()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="payment-tx-modal-title"
    >
        <div
            x-show="modalOpen"
            x-transition
            class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl border border-slate-200"
            @click.stop
        >
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 id="payment-tx-modal-title" class="text-lg font-semibold text-slate-900">Payment Transaction Details</h3>
                    <p class="mt-1 text-xs font-mono text-slate-500" x-text="activeTx?.payment_request_id || 'N/A'"></p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    @click="closeModals()"
                    aria-label="Close"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4">
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Customer</dt>
                        <dd class="mt-1 font-medium text-slate-900 capitalize" x-text="activeTx?.customer_name || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.status || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                        <dd class="mt-1 text-slate-900 break-all" x-text="activeTx?.customer_email || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.customer_phone || 'N/A'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Event</dt>
                        <dd class="mt-1 text-slate-900">
                            <template x-if="activeTx?.event_id > 0 && activeTx?.event?.exists">
                                <div>
                                    <p class="font-medium" x-html="activeTx.event.title"></p>
                                    <p class="text-xs text-slate-500 mt-1">
                                        ID <span x-text="activeTx.event_id"></span>
                                        <template x-if="activeTx.event.program_code">
                                            <span> · Code <span x-text="activeTx.event.program_code"></span></span>
                                        </template>
                                    </p>
                                    <template x-if="activeTx.event.date_display">
                                        <p class="text-xs text-slate-500 mt-1" x-text="activeTx.event.date_display"></p>
                                    </template>
                                    <template x-if="activeTx.event.venue">
                                        <p class="text-xs text-slate-500 mt-1" x-text="activeTx.event.venue"></p>
                                    </template>
                                    <template x-if="activeTx.event.active_until_display">
                                        <p class="text-xs text-slate-500 mt-1">
                                            Registration closes <span x-text="activeTx.event.active_until_display"></span>
                                        </p>
                                    </template>
                                </div>
                            </template>
                            <template x-if="activeTx?.event_id > 0 && activeTx?.event && !activeTx.event.exists">
                                <div>
                                    <p class="font-medium">#<span x-text="activeTx.event_id"></span></p>
                                    <p class="text-xs text-rose-600 mt-1" x-text="activeTx.event.error || 'Event not found.'"></p>
                                </div>
                            </template>
                            <template x-if="!activeTx?.event_id || activeTx.event_id < 1">
                                <span>N/A</span>
                            </template>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Amount</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.amount_display || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Payment Method</dt>
                        <dd class="mt-1">
                            <div class="flex items-center gap-2 text-slate-900">
                                <img
                                    x-show="activeTx?.payment_method_logo"
                                    :src="activeTx?.payment_method_logo"
                                    :alt="activeTx?.payment_method || 'Payment method'"
                                    class="h-30 w-auto object-contain shrink-0"
                                >
                                <span x-text="activeTx?.payment_method || 'N/A'"></span>
                            </div>
                        </dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Payment Date/Time</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.paid_display || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Transaction Date/Time</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.created_display || 'N/A'"></dd>
                    </div>
                    <div class="sm:col-span-2">
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Remark</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeTx?.remark || 'N/A'"></dd>
                    </div>
                </dl>
            </div>

            <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition"
                    @click="closeModals()"
                >
                    Close
                </button>
            </div>
        </div>
    </div>

    <div
        x-show="eventModalOpen"
        x-transition.opacity
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50"
        style="display: none;"
        @click.self="closeModals()"
        role="dialog"
        aria-modal="true"
        aria-labelledby="event-details-modal-title"
    >
        <div
            x-show="eventModalOpen"
            x-transition
            class="w-full max-w-lg max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl border border-slate-200"
            @click.stop
        >
            <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                <div>
                    <h3 id="event-details-modal-title" class="text-lg font-semibold text-slate-900" x-html="activeEvent?.title || 'Event Details'"></h3>
                    <p class="mt-1 text-xs text-slate-500">
                        Event ID <span x-text="activeEvent?.event_id || 'N/A'"></span>
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                    @click="closeModals()"
                    aria-label="Close"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="px-5 py-4">
                <dl class="grid grid-cols-1 gap-y-4 text-sm">
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Program Code</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeEvent?.program_code || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Date</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeEvent?.date_display || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Venue</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeEvent?.venue || 'N/A'"></dd>
                    </div>
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Registration Closes</dt>
                        <dd class="mt-1 text-slate-900" x-text="activeEvent?.active_until_display || 'N/A'"></dd>
                    </div>
                </dl>
            </div>

            <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                <button
                    type="button"
                    class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition"
                    @click="closeModals()"
                >
                    Close
                </button>
            </div>
        </div>
    </div>
</section>
