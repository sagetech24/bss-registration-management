<?php
$selected_event = $selected_event ?? null;
$selected_event_code = $selected_event_code ?? '';
$selected_event_id = (int) ($selected_event_id ?? 0);
$registrants_api_url = $registrants_api_url ?? add_query_arg(
    [
        'action'     => 'event-registrants-data',
        'event_id'   => $selected_event_id,
        'event_code' => $selected_event_code,
    ],
    $page_url ?? rm_page_url()
);
$payment_details_api_url = $payment_details_api_url ?? add_query_arg(
    [
        'action'   => 'registrant-payment-details',
        'event_id' => $selected_event_id,
    ],
    $page_url ?? rm_page_url()
);
$profile_api_url = $profile_api_url ?? add_query_arg(
    [
        'action'   => 'registrant-profile',
        'event_id' => $selected_event_id,
    ],
    $page_url ?? rm_page_url()
);
$event_title = is_array($selected_event) ? ($selected_event['title'] ?? 'Selected Event') : 'Selected Event';
$event_code_label = is_array($selected_event)
    ? ($selected_event['programCode'] ?? $selected_event_code)
    : $selected_event_code;
$event_thumb_url = is_array($selected_event) && isset($selected_event['thumb'])
    ? trim((string) $selected_event['thumb'])
    : '';
$aside_date = is_array($selected_event) && !empty($selected_event['date'])
    ? (string) $selected_event['date']
    : 'N/A';
$aside_time = is_array($selected_event) && !empty($selected_event['time'])
    ? (string) $selected_event['time']
    : 'N/A';
$aside_venue = is_array($selected_event) && !empty($selected_event['venue'])
    ? (string) $selected_event['venue']
    : 'N/A';
$aside_price = is_array($selected_event) && (float) ($selected_event['price'] ?? 0) > 0
    ? '$' . number_format_i18n((float) $selected_event['price'], 2)
    : 'FREE';
$event_is_free = is_array($selected_event) && rm_event_is_free($selected_event);
$registration_form_url = $selected_event_code !== ''
    ? rm_registration_url(['event_code' => $selected_event_code])
    : '';
$registrants_config = [
    'apiUrl'             => esc_url_raw($registrants_api_url),
    'paymentDetailsUrl'  => esc_url_raw($payment_details_api_url),
    'profileUrl'         => esc_url_raw($profile_api_url),
    'eventId'            => $selected_event_id,
    'eventIsFree'        => $event_is_free,
];
?>

<script>
document.addEventListener('alpine:init', () => {
    Alpine.data('rmEventRegistrants', () => ({
        loading: true,
        error: '',
        rows: [],
        summary: {
            total: 0,
            paid_count: 0,
            pending_count: 0,
            total_revenue: 0,
        },
        modalOpen: false,
        paymentLoading: false,
        paymentError: '',
        paymentDetails: null,
        profileModalOpen: false,
        profileLoading: false,
        profileError: '',
        profile: null,
        apiUrl: <?php echo wp_json_encode($registrants_config['apiUrl']); ?>,
        paymentDetailsUrl: <?php echo wp_json_encode($registrants_config['paymentDetailsUrl']); ?>,
        profileUrl: <?php echo wp_json_encode($registrants_config['profileUrl']); ?>,
        eventId: <?php echo (int) $registrants_config['eventId']; ?>,
        eventIsFree: <?php echo !empty($registrants_config['eventIsFree']) ? 'true' : 'false'; ?>,
        async init() {
            if (this.eventId < 1) {
                this.loading = false;
                this.error = 'Event id is required to load registrants.';
                return;
            }

            await this.load();
        },
        async load() {
            this.loading = true;
            this.error = '';

            try {
                const response = await fetch(this.apiUrl, {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.error = data.error || 'Failed to load registrants.';
                    this.rows = [];
                    return;
                }

                this.rows = data.registrant_rows || [];
                this.summary = data.registrants_summary || this.summary;
            } catch (e) {
                this.error = 'Failed to load registrants.';
                this.rows = [];
            } finally {
                this.loading = false;
            }
        },
        async openPaymentDetails(row) {
            if (!row?.has_payment || !row.payment_request_id) {
                return;
            }

            this.modalOpen = true;
            this.paymentLoading = true;
            this.paymentError = '';
            this.paymentDetails = null;

            try {
                const url = new URL(this.paymentDetailsUrl, window.location.origin);
                url.searchParams.set('payment_request_id', row.payment_request_id);

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.paymentError = data.error || 'Failed to load payment details.';
                    return;
                }

                this.paymentDetails = data.details || null;
            } catch (e) {
                this.paymentError = 'Failed to load payment details from HitPay.';
            } finally {
                this.paymentLoading = false;
            }
        },
        closeModal() {
            this.modalOpen = false;
            this.paymentLoading = false;
            this.paymentError = '';
            this.paymentDetails = null;
        },
        async openProfile(row) {
            if (!row?.registrant_id) {
                return;
            }

            this.profileModalOpen = true;
            this.profileLoading = true;
            this.profileError = '';
            this.profile = null;

            try {
                const url = new URL(this.profileUrl, window.location.origin);
                url.searchParams.set('registrant_id', row.registrant_id);

                const response = await fetch(url.toString(), {
                    credentials: 'same-origin',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });

                if (!response.ok) {
                    throw new Error('Request failed');
                }

                const data = await response.json();

                if (!data.ok) {
                    this.profileError = data.error || 'Failed to load registrant profile.';
                    return;
                }

                this.profile = data.profile || null;
            } catch (e) {
                this.profileError = 'Failed to load registrant profile.';
            } finally {
                this.profileLoading = false;
            }
        },
        closeProfileModal() {
            this.profileModalOpen = false;
            this.profileLoading = false;
            this.profileError = '';
            this.profile = null;
        },
        closeAllModals() {
            this.closeModal();
            this.closeProfileModal();
        },
        formatCount(value) {
            return Number(value || 0).toLocaleString();
        },
        formatAmount(value) {
            const amount = Number(value || 0);
            return '$' + amount.toLocaleString(undefined, {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2,
            });
        },
    }));
});
</script>

<section
    class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start"
    x-data="rmEventRegistrants()"
    @keydown.escape.window="closeAllModals()"
>
    <div class="lg:col-span-12 space-y-6">
        <?php if ($selected_event_code !== '' && is_array($selected_event)) : ?>
            <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
                <div class="lg:col-span-9">
                    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-5">
                        <p class="text-xs font-semibold uppercase tracking-wider text-indigo-600 mb-6">
                            Program Code:
                            <span class="bg-indigo-100 text-indigo-800 px-2.5 py-1.5 rounded-full font-extrabold"><?php echo esc_html((string) $event_code_label); ?></span>
                        </p>
                        <h3 class="mt-2 text-2xl font-semibold text-slate-900">
                            <?php echo $event_title; ?>
                        </h3>
                        <div class="mt-3 grid grid-cols-1 sm:grid-cols-2 gap-2 text-sm text-slate-600">
                            <?php if (!empty($selected_event['description'])) : ?>
                                <div class="sm:col-span-2 text-sm text-slate-600">
                                    <span class="font-medium italic text-slate-800">Description:</span>
                                    <div class="mt-1 text-slate-700 leading-relaxed"><?php echo wp_kses_post((string) $selected_event['description']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="mt-6 mb-12">
                        <div class="grid grid-cols-1 sm:grid-cols-1 xl:grid-cols-3 gap-4">
                            <div class="rounded-xl shadow-md border border-amber-200 bg-amber-50 p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Total registrants</p>
                                <template x-if="loading">
                                    <div class="mt-2 h-9 w-16 rounded-lg bg-amber-100 animate-pulse"></div>
                                </template>
                                <p
                                    x-show="!loading"
                                    x-cloak
                                    class="mt-2 text-3xl font-bold text-slate-900"
                                    x-text="formatCount(summary.total)"
                                ></p>
                            </div>
                            <div class="rounded-xl shadow-md border border-emerald-200 bg-emerald-50 p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-emerald-700">Paid / confirmed</p>
                                <template x-if="loading">
                                    <div class="mt-2 h-9 w-16 rounded-lg bg-emerald-100 animate-pulse"></div>
                                </template>
                                <p
                                    x-show="!loading"
                                    x-cloak
                                    class="mt-2 text-3xl font-bold text-emerald-800"
                                    x-text="formatCount(summary.paid_count)"
                                ></p>
                            </div>
                            <div class="rounded-xl shadow-md border border-blue-200 bg-blue-50 p-4">
                                <p class="text-xs font-medium uppercase tracking-wide text-blue-700">Total revenue</p>
                                <template x-if="loading">
                                    <div class="mt-2 h-9 w-24 rounded-lg bg-blue-100 animate-pulse"></div>
                                </template>
                                <p
                                    x-show="!loading"
                                    x-cloak
                                    class="mt-2 text-3xl font-bold text-green-800"
                                    x-text="formatAmount(summary.total_revenue)"
                                ></p>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="lg:col-span-3 bg-white border border-slate-200 rounded-xl shadow-sm">
                    <?php if ($event_thumb_url !== '') : ?>
                        <img
                            class="w-full h-auto object-cover rounded-t-xl"
                            src="<?php echo esc_url($event_thumb_url); ?>"
                            alt="<?php echo esc_attr($event_title); ?>"
                        >
                    <?php else : ?>
                        <div class="flex h-full min-h-[12rem] items-center justify-center rounded-t-xl bg-slate-100 text-sm text-slate-400">
                            No image
                        </div>
                    <?php endif; ?>
                    <div class="p-4">
                        <table class="w-full text-sm border-collapse">
                            <tbody>
                                <tr>
                                    <td class="py-1 pr-3 align-top text-slate-500 whitespace-nowrap">Date</td>
                                    <td class="py-1 align-top font-medium text-slate-800"><?php echo esc_html($aside_date); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-1 pr-3 align-top text-slate-500 whitespace-nowrap">Time</td>
                                    <td class="py-1 align-top font-medium text-slate-800"><?php echo esc_html($aside_time); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-1 pr-3 align-top text-slate-500 whitespace-nowrap">Venue</td>
                                    <td class="py-1 align-top font-medium text-slate-800"><?php echo esc_html($aside_venue); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-1 pr-3 align-top text-slate-500 whitespace-nowrap">Price</td>
                                    <td class="py-1 align-top font-medium text-slate-800"><?php echo esc_html($aside_price); ?></td>
                                </tr>
                            </tbody>
                        </table>
                        <!-- add here the event link and the event registration link -->
                        <?php if ($registration_form_url !== '') : ?>
                            <div class="mt-4">
                                <a
                                    href="<?php echo esc_url($registration_form_url); ?>"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    class="inline-flex w-full items-center justify-center gap-2 rounded-full bg-indigo-700 p-3 text-sm font-medium text-white hover:bg-indigo-800 transition"
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-4">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 0 0 6 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 0 1 6 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 0 1 6-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0 0 18 18a8.967 8.967 0 0 0-6 2.292m0-14.25v14.25" />
                                    </svg>
                                    View Registration Form
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div x-show="loading" class="bg-white border border-slate-200 rounded-xl shadow-sm p-10">
                <div class="flex flex-col items-center justify-center text-center">
                    <div class="relative size-12">
                        <div class="absolute inset-0 rounded-full border-4 border-slate-200"></div>
                        <div class="absolute inset-0 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
                    </div>
                    <p class="mt-4 text-sm font-medium text-slate-700">Loading registrants...</p>
                    <p class="mt-1 text-xs text-slate-500">Fetching confirmed registrant records from the database.</p>
                </div>

                <div class="mt-8 space-y-3 animate-pulse">
                    <?php for ($i = 0; $i < 5; $i++) : ?>
                        <div class="h-12 rounded-lg bg-slate-100"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <div x-show="!loading && error !== ''" x-cloak class="space-y-4" style="display: none;">
                <div class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800" x-text="error"></div>
                <button
                    type="button"
                    class="rounded-lg bg-indigo-700 px-4 py-2 text-sm font-medium text-white hover:bg-indigo-800 transition"
                    @click="load()"
                >
                    Try again
                </button>
            </div>



            <div
                x-show="!loading && error === '' && rows.length === 0"
                x-cloak
                class="p-6 text-slate-600 border border-slate-200 rounded-xl bg-white text-center"
                style="display: none;"
            >
                <h3 class="text-lg font-semibold text-slate-700">No registrants yet</h3>
                <p class="mt-1 text-sm text-slate-500">No registrant records were found for this event.</p>
            </div>

            <div
                x-show="!loading && error === '' && rows.length > 0"
                x-cloak
                class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden"
                style="display: none;"
            >
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Order number</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Registrant</th>
                                <th scope="col" x-show="!eventIsFree" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Payment Method</th>
                                <th scope="col" x-show="!eventIsFree" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Status</th>
                                <th scope="col" x-show="!eventIsFree" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Amount</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Email sent</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Registered</th>
                                <th scope="col" class="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wider text-slate-600">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100 bg-white">
                            <template x-for="row in rows" :key="row.registrant_id || row.order_number + row.email">
                                <tr class="hover:bg-slate-50/80">
                                    <td class="whitespace-nowrap px-4 py-3 text-xs font-mono font-semibold text-slate-800" x-text="row.order_number || 'N/A'"></td>
                                    <td class="px-4 py-3 text-sm">
                                        <p class="font-medium text-slate-800 text-lg" x-text="row.full_name"></p>
                                        <div class="flex flex-col">
                                            <p class="text-[11px] flex gap-1 items-center">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a2.25 2.25 0 0 1-2.25 2.25h-15a2.25 2.25 0 0 1-2.25-2.25V6.75m19.5 0A2.25 2.25 0 0 0 19.5 4.5h-15a2.25 2.25 0 0 0-2.25 2.25m19.5 0v.243a2.25 2.25 0 0 1-1.07 1.916l-7.5 4.615a2.25 2.25 0 0 1-2.36 0L3.32 8.91a2.25 2.25 0 0 1-1.07-1.916V6.75" />
                                                </svg>
                                                <span x-text="row.email || 'No Email'"></span>
                                            </p>
                                            <p class="text-[11px] flex gap-1 items-center text-slate-500">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-3">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 1.5H8.25A2.25 2.25 0 0 0 6 3.75v16.5a2.25 2.25 0 0 0 2.25 2.25h7.5A2.25 2.25 0 0 0 18 20.25V3.75a2.25 2.25 0 0 0-2.25-2.25H13.5m-3 0V3h3V1.5m-3 0h3m-3 18.75h3" />
                                                </svg>
                                                <span x-text="row.phone || 'No phone'"></span>
                                            </p>
                                        </div>
                                    </td>
                                    <td x-show="!eventIsFree" class="px-4 py-3 text-xs text-slate-700">
                                        <div class="flex items-center gap-2">
                                            <img
                                                x-show="row.charge_payment_method_logo"
                                                :src="row.charge_payment_method_logo"
                                                :alt="row.charge_payment_method || 'Payment method'"
                                                class="h-6 w-16 object-cover"
                                            >
                                        </div>
                                        <p
                                            class="mt-0.5 text-[10px] truncate max-w-[10rem] font-mono text-slate-500"
                                            x-show="row.payment_request_id"
                                            x-text="row.payment_request_id || 'N/A'"
                                        ></p>
                                    </td>
                                    <td x-show="!eventIsFree" class="whitespace-nowrap px-4 py-3 text-xs">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="row.is_paid ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800'"
                                            x-text="row.payment_status"
                                        ></span>
                                    </td>
                                    <td x-show="!eventIsFree" class="whitespace-nowrap px-4 py-3 text-xs text-slate-700" x-text="row.charge_amount_display"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs">
                                        <span
                                            class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                            :class="row.email_sent ? 'bg-emerald-100 text-emerald-800' : 'bg-rose-100 text-rose-800'"
                                            x-text="row.email_sent_label"
                                        ></span>
                                    </td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs text-slate-600" x-text="row.date_display"></td>
                                    <td class="whitespace-nowrap px-4 py-3 text-xs">
                                        <button
                                            x-show="eventIsFree"
                                            type="button"
                                            class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-xs font-medium text-indigo-700 transition hover:bg-indigo-100"
                                            @click="openProfile(row)"
                                        >
                                            View Profile
                                        </button>
                                        <button
                                            x-show="!eventIsFree"
                                            type="button"
                                            class="rounded-full border px-2 py-1 text-[11px] transition"
                                            :class="row.has_payment
                                                ? 'border-indigo-200 bg-indigo-50 text-indigo-700 hover:bg-indigo-100'
                                                : 'border-slate-200 bg-slate-50 text-slate-400 cursor-not-allowed'"
                                            :disabled="!row.has_payment"
                                            @click="openPaymentDetails(row)"
                                        >
                                            Payment Details
                                        </button>
                                    </td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </div>

            <div
                x-show="modalOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 top-[-25px]"
                style="display: none;"
                @click.self="closeModal()"
                role="dialog"
                aria-modal="true"
                aria-labelledby="registrant-payment-modal-title"
            >
                <div
                    x-show="modalOpen"
                    x-transition
                    class="w-full max-w-2xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl border border-slate-200"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h3 id="registrant-payment-modal-title" class="text-lg font-semibold text-slate-900">Payment Details</h3>
                            <p class="mt-1 font-mono text-xs text-slate-500" x-text="paymentDetails?.payment?.payment_request_id ? 'Request ID: '+paymentDetails?.payment?.payment_request_id : ''"></p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                            @click="closeModal()"
                            aria-label="Close"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-5 py-4">
                        <div x-show="paymentLoading" class="flex flex-col items-center justify-center py-8">
                            <div class="relative size-10">
                                <div class="absolute inset-0 rounded-full border-4 border-slate-200"></div>
                                <div class="absolute inset-0 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">Loading charge and payment details from HitPay...</p>
                        </div>

                        <div x-show="!paymentLoading && paymentError !== ''" x-cloak class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm" x-text="paymentError"></div>

                        <div
                            x-show="!paymentLoading && paymentError === '' && paymentDetails"
                            x-cloak
                            class="space-y-6"
                        >
                            <div>
                                <p class="mt-1 text-sm text-slate-600">Payment request details generated by hitpay after submitting the registration form.</p>

                                <dl class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6 text-sm">
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Customer</dt>
                                        <dd class="mt-1 font-medium text-slate-900 capitalize" x-text="paymentDetails?.payment?.customer_name || 'N/A'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                                        <dd class="mt-1 text-slate-900 break-all" x-text="paymentDetails?.payment?.customer_email || 'N/A'"></dd>
                                    </div>
                                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm items-center">
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Reference</dt>
                                            <dd class="mt-1 font-mono text-xs text-slate-900 break-all" x-text="paymentDetails?.payment?.reference_number || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Amount</dt>
                                            <dd class="mt-1 text-slate-900" x-text="paymentDetails?.payment?.amount_display || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                                            <dd class="mt-1 text-slate-900 bg-green-100 border border-green-400 text-green-800 rounded-full inline-block px-2.5 py-1 text-xs" x-text="paymentDetails?.payment?.status || 'N/A'"></dd>
                                        </div>
                                    </div>
                                    <div class="sm:col-span-2">
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Purpose</dt>
                                        <dd class="mt-1 text-slate-900" x-text="paymentDetails?.payment?.purpose || 'N/A'"></dd>
                                    </div>
                                </dl>
                            </div>
                            <div class="border-t border-slate-200 pt-6">
                                <h4 class="text-sm font-semibold text-slate-900">Payment Charge Details</h4>
                                <p class="mt-1 text-sm text-slate-600">Payment charge details made through hitpay.</p>

                                <div
                                    x-show="paymentDetails?.charge_error"
                                    x-cloak
                                    class="mt-4 p-4 bg-amber-50 border border-amber-200 rounded-lg text-amber-800 text-sm"
                                    x-text="paymentDetails?.charge_error"
                                ></div>

                                <dl
                                    x-show="paymentDetails?.charge"
                                    x-cloak
                                    class="mt-4 grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-6 text-sm"
                                >
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Charge ID</dt>
                                        <dd class="mt-1 font-mono text-[11px] text-slate-900 break-all inline-block border border-amber-100 bg-amber-50 rounded-lg px-2 py-1" x-text="paymentDetails?.charge?.charge_id || 'N/A'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Customer</dt>
                                        <dd class="mt-1 font-medium text-slate-900 capitalize" x-text="paymentDetails?.charge?.customer_name || 'N/A'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Email</dt>
                                        <dd class="mt-1 text-slate-900 break-all" x-text="paymentDetails?.charge?.customer_email || 'N/A'"></dd>
                                    </div>
                                    <div>
                                        <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Phone</dt>
                                        <dd class="mt-1 text-slate-900" x-text="paymentDetails?.charge?.customer_phone || 'N/A'"></dd>
                                    </div>
                                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm items-center">
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Reference</dt>
                                            <dd class="mt-1 font-mono text-xs text-slate-900 break-all" x-text="paymentDetails?.charge?.reference_number || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Amount</dt>
                                            <dd class="mt-1 text-slate-900" x-text="paymentDetails?.charge?.amount_display || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Status</dt>
                                            <dd class="mt-1 text-slate-900 rounded-full inline-block px-2.5 py-1 text-xs font-semibold bg-green-100 border border-green-400 text-green-800" x-text="paymentDetails?.charge?.status || 'N/A'"></dd>
                                        </div>
                                    </div>

                                    <div class="sm:col-span-2 grid grid-cols-1 sm:grid-cols-3 gap-x-6 gap-y-4 text-sm items-center">
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Payment Method</dt>
                                            <dd class="mt-1">
                                                <div class="flex items-center gap-2 text-slate-900">
                                                    <img
                                                        x-show="paymentDetails?.charge?.payment_method_logo"
                                                        :src="paymentDetails?.charge?.payment_method_logo"
                                                        :alt="paymentDetails?.charge?.payment_method || 'Payment method'"
                                                        class="h-6 w-16 object-cover"
                                                    >
                                                </div>
                                            </dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Paid</dt>
                                            <dd class="mt-1 text-slate-900" x-text="paymentDetails?.charge?.paid_display || 'N/A'"></dd>
                                        </div>
                                        <div>
                                            <dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Created</dt>
                                            <dd class="mt-1 text-slate-900" x-text="paymentDetails?.charge?.created_display || 'N/A'"></dd>
                                        </div>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                        <button
                            type="button"
                            class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition"
                            @click="closeModal()"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>

            <div
                x-show="profileModalOpen"
                x-transition.opacity
                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 top-[-25px]"
                style="display: none;"
                @click.self="closeProfileModal()"
                role="dialog"
                aria-modal="true"
                aria-labelledby="registrant-profile-modal-title"
            >
                <div
                    x-show="profileModalOpen"
                    x-transition
                    class="w-full max-w-3xl max-h-[90vh] overflow-y-auto rounded-xl bg-white shadow-xl border border-slate-200"
                    @click.stop
                >
                    <div class="flex items-start justify-between gap-4 border-b border-slate-200 px-5 py-4">
                        <div>
                            <h3 id="registrant-profile-modal-title" class="text-lg font-semibold text-slate-900">Registrant Profile</h3>
                            <p class="mt-1 text-sm font-medium text-slate-700" x-text="profile?.full_name || 'N/A'"></p>
                            <p class="mt-0.5 text-xs font-mono text-slate-500" x-text="profile?.order_number || 'N/A'"></p>
                        </div>
                        <button
                            type="button"
                            class="rounded-lg p-2 text-slate-400 hover:bg-slate-100 hover:text-slate-600 transition"
                            @click="closeProfileModal()"
                            aria-label="Close"
                        >
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <div class="px-5 py-4">
                        <div x-show="profileLoading" class="flex flex-col items-center justify-center py-8">
                            <div class="relative size-10">
                                <div class="absolute inset-0 rounded-full border-4 border-slate-200"></div>
                                <div class="absolute inset-0 rounded-full border-4 border-indigo-600 border-t-transparent animate-spin"></div>
                            </div>
                            <p class="mt-3 text-sm text-slate-600">Loading registrant profile...</p>
                        </div>

                        <div x-show="!profileLoading && profileError !== ''" x-cloak class="p-4 bg-rose-50 border border-rose-200 rounded-lg text-rose-800 text-sm" x-text="profileError"></div>

                        <dl
                            x-show="!profileLoading && profileError === '' && profile"
                            x-cloak
                            class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-4 text-sm"
                        >
                            <template x-for="field in profile?.fields || []" :key="field.key">
                                <div :class="field.key === 'note' ? 'sm:col-span-2' : ''">
                                    <dt class="text-xs font-medium uppercase tracking-wide text-slate-500" x-text="field.label"></dt>
                                    <dd class="mt-1 text-slate-900">
                                        <pre
                                            x-show="field.key === 'note'"
                                            class="whitespace-pre-wrap break-words rounded-lg bg-slate-50 border border-slate-200 p-3 text-xs font-mono text-slate-800"
                                            x-text="field.value"
                                        ></pre>
                                        <span
                                            x-show="field.key !== 'note'"
                                            class="break-words"
                                            x-text="field.value"
                                        ></span>
                                    </dd>
                                </div>
                            </template>
                        </dl>
                    </div>

                    <div class="flex justify-end border-t border-slate-200 px-5 py-4">
                        <button
                            type="button"
                            class="rounded-lg bg-slate-100 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-200 transition"
                            @click="closeProfileModal()"
                        >
                            Close
                        </button>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</section>
