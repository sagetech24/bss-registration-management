<?php
$form_schema = is_array($form_schema ?? null) ? $form_schema : ['fields' => []];
$group_limits = is_array($group_limits ?? null) ? $group_limits : ['min' => 1, 'max' => 1, 'require_all_members' => false];
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$pricing_preview = is_array($pricing_preview ?? null) ? $pricing_preview : [];
$members_input = is_array($members_input ?? null) ? $members_input : [];
$guests_input = is_array($guests_input ?? null) ? $guests_input : [];
$active_promotion = is_array($active_promotion ?? null) ? $active_promotion : null;
$guest_schema = is_array($guest_schema ?? null) ? $guest_schema : ['fields' => [], 'enabled' => false, 'label_singular' => 'Guest', 'label_plural' => 'Guests', 'min' => 0, 'max' => 0, 'price' => 0];
$event_currency = (string) ($event_currency ?? 'SGD');
$event_coverage = rm_registration_coverage($registration_config);
$phone_country_codes = rm_phone_country_codes();
$mode = (string) ($registration_config['mode'] ?? 'group_flat');
$require_all_members = !empty($group_limits['require_all_members']);
$schema_json = wp_json_encode($form_schema);
$guest_schema_json = wp_json_encode($guest_schema);
$limits_json = wp_json_encode([
    'min'                 => (int) ($group_limits['min'] ?? 1),
    'max'                 => (int) ($group_limits['max'] ?? 1),
    'require_all_members' => $require_all_members,
]);
$members_json = wp_json_encode($members_input !== [] ? $members_input : []);
$guests_json = wp_json_encode($guests_input !== [] ? $guests_input : []);
$pricing_json = wp_json_encode($pricing_preview);
$coverage_json = wp_json_encode($event_coverage);
$phone_codes_json = wp_json_encode($phone_country_codes);
$privacy_policy_url = function_exists('get_privacy_policy_url') ? get_privacy_policy_url() : '';
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_local_class = 'w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_dial_class = 'min-w-[6rem] rounded-l-lg rounded-r-none border border-r-0 border-slate-300 bg-slate-50 px-2 py-2 text-sm text-slate-700 focus:border-indigo-500 focus:outline-none';
$phone_fixed_class = 'inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600';
?>

<div
    x-data="rmRegisterWizard()"
    x-init="init(<?php echo esc_attr($schema_json); ?>, <?php echo esc_attr($limits_json); ?>, <?php echo esc_attr($members_json); ?>, <?php echo esc_attr($pricing_json); ?>, <?php echo esc_attr($guest_schema_json); ?>, <?php echo esc_attr($guests_json); ?>, <?php echo esc_attr(wp_json_encode($event_currency)); ?>, <?php echo esc_attr($coverage_json); ?>, <?php echo esc_attr($phone_codes_json); ?>, <?php echo esc_attr(wp_json_encode($mode)); ?>)"
    class="space-y-6"
>
    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
        <template x-for="(label, index) in stepLabels" :key="index">
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                    :class="stepperIndex === index ? 'bg-indigo-700 text-white' : (stepperIndex > index ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600')"
                    x-text="index + 1"
                ></span>
                <span :class="stepperIndex === index ? 'font-medium text-slate-900' : ''" x-text="label"></span>
                <span x-show="index < stepLabels.length - 1" class="text-slate-300 hidden sm:inline">→</span>
            </div>
        </template>
    </div>

    <div class="bg-white border border-slate-200 rounded-xl shadow-sm p-6">
        <form method="post" action="<?php echo esc_url($page_url); ?>" @submit="prepareSubmit($event)">
            <?php wp_nonce_field('rm_register', 'rm_register_nonce'); ?>
            <?php if ($active_promotion !== null) : ?>
                <input type="hidden" name="event_promotion_id" value="<?php echo esc_attr((string) (int) $active_promotion['id']); ?>" />
            <?php endif; ?>
            <input type="hidden" name="members_json" :value="serializedMembers" />
            <input type="hidden" name="guests_json" :value="serializedGuests" />

            <div x-show="step === 0" class="space-y-4">
                <fieldset class="rounded-lg border border-slate-200 p-4 space-y-4">
                    <legend class="text-sm font-medium text-slate-700 px-1">
                        <?php echo esc_html($mode === 'group_flat' || $mode === 'group_per_head' ? 'Registration Leader Information' : 'Registration Information'); ?>
                    </legend>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <template x-for="field in schema.fields" :key="'leader-' + field.key">
                            <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                    <span x-text="field.label"></span>
                                    <span x-show="field.required" class="text-rose-500">*</span>
                                </label>
                                <template x-if="field.type === 'textarea'">
                                    <textarea
                                        class="<?php echo esc_attr($input_class); ?>"
                                        :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                        rows="3"
                                        x-model="members[0][field.key]"
                                        :placeholder="fieldPlaceholder(field)"
                                        :required="!!field.required"
                                        @input="delete fieldErrors[field.key]"
                                    ></textarea>
                                </template>
                                <template x-if="field.type === 'select'">
                                    <select
                                        class="<?php echo esc_attr($input_class); ?>"
                                        :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                        x-model="members[0][field.key]"
                                        :required="!!field.required"
                                        @change="delete fieldErrors[field.key]"
                                    >
                                        <option value="" x-text="fieldPlaceholder(field)"></option>
                                        <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                            <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="field.type === 'phone'">
                                    <div class="flex">
                                        <template x-if="coverage === 'international'">
                                            <select class="<?php echo esc_attr($phone_dial_class); ?>" x-model="members[0][field.key + '__dial']" @change="syncPhone(members[0], field.key); delete fieldErrors[field.key]">
                                                <template x-for="cc in phoneCountryCodes" :key="cc.code + cc.dial">
                                                    <option :value="cc.dial" x-text="cc.dial + ' ' + cc.code"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="coverage !== 'international'">
                                            <span class="<?php echo esc_attr($phone_fixed_class); ?>">+65</span>
                                        </template>
                                        <input
                                            class="<?php echo esc_attr($phone_local_class); ?>"
                                            :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                            type="tel"
                                            inputmode="numeric"
                                            x-model="members[0][field.key + '__local']"
                                            @input="syncPhone(members[0], field.key); delete fieldErrors[field.key]"
                                            :placeholder="fieldPlaceholder(field)"
                                            :required="!!field.required"
                                        />
                                    </div>
                                </template>
                                <template x-if="!['textarea','select','checkbox','checkbox_group','radio','phone'].includes(field.type)">
                                    <input
                                        class="<?php echo esc_attr($input_class); ?>"
                                        :class="fieldErrors[field.key] ? 'border-rose-400' : ''"
                                        :type="inputType(field.type)"
                                        x-model="members[0][field.key]"
                                        :placeholder="field.type === 'date' ? '' : fieldPlaceholder(field)"
                                        :required="!!field.required"
                                        @input="delete fieldErrors[field.key]"
                                        @blur="validateFieldLive(field, members[0], 'leader')"
                                    />
                                </template>
                                <p x-show="fieldErrors[field.key]" class="mt-1 text-sm text-rose-600" x-text="fieldErrors[field.key]" x-cloak></p>
                            </div>
                        </template>
                    </div>
                </fieldset>
                <div class="pt-2 flex justify-end">
                    <button type="button" @click="nextFromLeader()" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800">Next</button>
                </div>
            </div>

            <div x-show="step === 1" class="space-y-4">
                <h3 class="text-sm text-slate-700" x-text="membersStepHeading"></h3>
                <p class="text-sm text-slate-500" x-show="!isIndividual">
                    <span class="italic font-medium">Note:</span>
                    This package requires exactly <span x-text="limits.max"></span> registrant(s). Please fill up the additional information below
                </p>

                <fieldset x-show="!isIndividual" class="rounded-lg border border-slate-200 p-4 space-y-4">
                    <legend class="text-sm font-medium text-slate-700 px-1">
                        More Members
                        <span class="text-slate-400 font-normal">(<span x-text="members.length"></span> of <span x-text="limits.max"></span>)</span>
                    </legend>

                    <template x-for="(member, mIndex) in members" :key="'wrap-' + mIndex">
                        <div x-show="mIndex > 0" class="rounded-lg border border-slate-200 p-4 space-y-4">
                            <h4 class="text-sm font-medium text-slate-800">Member <span x-text="mIndex + 1"></span></h4>
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                <template x-for="field in schema.fields" :key="'m-' + mIndex + '-' + field.key">
                                    <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                                        <label class="block text-sm font-medium text-slate-700 mb-2">
                                            <span x-text="field.label"></span>
                                            <span x-show="field.required" class="text-rose-500">*</span>
                                        </label>
                                        <template x-if="field.type === 'textarea'">
                                            <textarea
                                                class="<?php echo esc_attr($input_class); ?>"
                                                :class="memberErrors[mIndex] && memberErrors[mIndex][field.key] ? 'border-rose-400' : ''"
                                                rows="3"
                                                x-model="members[mIndex][field.key]"
                                                :placeholder="fieldPlaceholder(field)"
                                                :required="!!field.required"
                                                @input="memberErrors[mIndex] && delete memberErrors[mIndex][field.key]"
                                            ></textarea>
                                        </template>
                                        <template x-if="field.type === 'select'">
                                            <select
                                                class="<?php echo esc_attr($input_class); ?>"
                                                :class="memberErrors[mIndex] && memberErrors[mIndex][field.key] ? 'border-rose-400' : ''"
                                                x-model="members[mIndex][field.key]"
                                                :required="!!field.required"
                                                @change="memberErrors[mIndex] && delete memberErrors[mIndex][field.key]"
                                            >
                                                <option value="" x-text="fieldPlaceholder(field)"></option>
                                                <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                                    <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                                </template>
                                            </select>
                                        </template>
                                        <template x-if="field.type === 'phone'">
                                            <div class="flex">
                                                <template x-if="coverage === 'international'">
                                                    <select class="<?php echo esc_attr($phone_dial_class); ?>" x-model="members[mIndex][field.key + '__dial']" @change="syncPhone(members[mIndex], field.key); memberErrors[mIndex] && delete memberErrors[mIndex][field.key]">
                                                        <template x-for="cc in phoneCountryCodes" :key="cc.code + cc.dial">
                                                            <option :value="cc.dial" x-text="cc.dial + ' ' + cc.code"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="coverage !== 'international'">
                                                    <span class="<?php echo esc_attr($phone_fixed_class); ?>">+65</span>
                                                </template>
                                                <input
                                                    class="<?php echo esc_attr($phone_local_class); ?>"
                                                    :class="memberErrors[mIndex] && memberErrors[mIndex][field.key] ? 'border-rose-400' : ''"
                                                    type="tel"
                                                    inputmode="numeric"
                                                    x-model="members[mIndex][field.key + '__local']"
                                                    @input="syncPhone(members[mIndex], field.key); memberErrors[mIndex] && delete memberErrors[mIndex][field.key]"
                                                    :placeholder="fieldPlaceholder(field)"
                                                    :required="!!field.required"
                                                />
                                            </div>
                                        </template>
                                        <template x-if="!['textarea','select','checkbox','checkbox_group','radio','phone'].includes(field.type)">
                                            <input
                                                class="<?php echo esc_attr($input_class); ?>"
                                                :class="memberErrors[mIndex] && memberErrors[mIndex][field.key] ? 'border-rose-400' : ''"
                                                :type="inputType(field.type)"
                                                x-model="members[mIndex][field.key]"
                                                :placeholder="field.type === 'date' ? '' : fieldPlaceholder(field)"
                                                :required="!!field.required"
                                                @input="memberErrors[mIndex] && delete memberErrors[mIndex][field.key]"
                                            />
                                        </template>
                                        <p x-show="memberErrors[mIndex] && memberErrors[mIndex][field.key]" class="mt-1 text-sm text-rose-600" x-text="memberErrors[mIndex] && memberErrors[mIndex][field.key]" x-cloak></p>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </template>

                    <div class="flex gap-3">
                        <button
                            type="button"
                            x-show="!limits.require_all_members && members.length < limits.max"
                            @click="addMember()"
                            class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                        >Add member</button>
                        <button
                            type="button"
                            x-show="!limits.require_all_members && members.length > limits.min"
                            @click="removeMember()"
                            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                        >Remove last member</button>
                        <!-- <p x-show="limits.require_all_members" class="text-sm text-slate-500">
                            This package requires exactly <span x-text="limits.max"></span> registrant(s).
                        </p> -->
                    </div>
                </fieldset>

                <template x-if="guestSchema.enabled">
                    <div class="space-y-2 mb-2">
                        <h3 class="text-md font-medium text-slate-700"><span x-text="guestSchema.label_plural || 'Guests'"></span> Registration Add-On</h3>
                        <p class="text-sm text-slate-500" x-show="!isIndividual">
                            Event package allows to register <span x-text="guestSchema.label_plural || 'Guests'"></span>. Please fill up the additional information below
                        </p>
                        <fieldset class="rounded-lg border border-slate-200 p-4 space-y-4">
                            <legend class="text-sm font-medium text-slate-700 px-1">
                                <span x-text="guestSchema.label_plural || 'Guests'"></span>
                                <span class="text-slate-400 font-normal">(<span x-text="guests.length"></span> of <span x-text="guestSchema.max"></span><template x-if="guestSchema.price > 0"><span> · <span x-text="formatCurrency(parseFloat(guestSchema.price))"></span> each</span></template>)</span>
                            </legend>
    
                            <template x-for="(guest, gIdx) in guests" :key="'guest-' + gIdx">
                                <div class="rounded-lg border border-slate-200 p-4 space-y-4">
                                    <h4 class="text-sm font-medium text-slate-800">
                                        <span x-text="guestSchema.label_singular || 'Guest'"></span> <span x-text="gIdx + 1"></span>
                                    </h4>
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                                        <template x-for="field in guestSchema.fields" :key="'g-' + gIdx + '-' + field.key">
                                            <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                                                <label class="block text-sm font-medium text-slate-700 mb-2">
                                                    <span x-text="field.label"></span>
                                                    <span x-show="field.required" class="text-rose-500">*</span>
                                                </label>
                                                <template x-if="field.type === 'textarea'">
                                                    <textarea
                                                        class="<?php echo esc_attr($input_class); ?>"
                                                        :class="guestErrors[gIdx] && guestErrors[gIdx][field.key] ? 'border-rose-400' : ''"
                                                        rows="3"
                                                        x-model="guests[gIdx][field.key]"
                                                        :placeholder="fieldPlaceholder(field)"
                                                        :required="!!field.required"
                                                        @input="guestErrors[gIdx] && delete guestErrors[gIdx][field.key]"
                                                    ></textarea>
                                                </template>
                                                <template x-if="field.type === 'select'">
                                                    <select
                                                        class="<?php echo esc_attr($input_class); ?>"
                                                        :class="guestErrors[gIdx] && guestErrors[gIdx][field.key] ? 'border-rose-400' : ''"
                                                        x-model="guests[gIdx][field.key]"
                                                        :required="!!field.required"
                                                        @change="guestErrors[gIdx] && delete guestErrors[gIdx][field.key]"
                                                    >
                                                        <option value="" x-text="fieldPlaceholder(field)"></option>
                                                        <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                                            <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                                        </template>
                                                    </select>
                                                </template>
                                                <template x-if="field.type === 'phone'">
                                                    <div class="flex">
                                                        <template x-if="coverage === 'international'">
                                                            <select class="<?php echo esc_attr($phone_dial_class); ?>" x-model="guests[gIdx][field.key + '__dial']" @change="syncPhone(guests[gIdx], field.key); guestErrors[gIdx] && delete guestErrors[gIdx][field.key]">
                                                                <template x-for="cc in phoneCountryCodes" :key="cc.code + cc.dial">
                                                                    <option :value="cc.dial" x-text="cc.dial + ' ' + cc.code"></option>
                                                                </template>
                                                            </select>
                                                        </template>
                                                        <template x-if="coverage !== 'international'">
                                                            <span class="<?php echo esc_attr($phone_fixed_class); ?>">+65</span>
                                                        </template>
                                                        <input
                                                            class="<?php echo esc_attr($phone_local_class); ?>"
                                                            :class="guestErrors[gIdx] && guestErrors[gIdx][field.key] ? 'border-rose-400' : ''"
                                                            type="tel"
                                                            inputmode="numeric"
                                                            x-model="guests[gIdx][field.key + '__local']"
                                                            @input="syncPhone(guests[gIdx], field.key); guestErrors[gIdx] && delete guestErrors[gIdx][field.key]"
                                                            :placeholder="fieldPlaceholder(field)"
                                                            :required="!!field.required"
                                                        />
                                                    </div>
                                                </template>
                                                <template x-if="!['textarea','select','checkbox','checkbox_group','radio','phone'].includes(field.type)">
                                                    <input
                                                        class="<?php echo esc_attr($input_class); ?>"
                                                        :class="guestErrors[gIdx] && guestErrors[gIdx][field.key] ? 'border-rose-400' : ''"
                                                        :type="inputType(field.type)"
                                                        x-model="guests[gIdx][field.key]"
                                                        :placeholder="field.type === 'date' ? '' : fieldPlaceholder(field)"
                                                        :required="!!field.required"
                                                        @input="guestErrors[gIdx] && delete guestErrors[gIdx][field.key]"
                                                    />
                                                </template>
                                                <p x-show="guestErrors[gIdx] && guestErrors[gIdx][field.key]" class="mt-1 text-sm text-rose-600" x-text="guestErrors[gIdx] && guestErrors[gIdx][field.key]" x-cloak></p>
                                            </div>
                                        </template>
                                    </div>
                                </div>
                            </template>
    
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    x-show="guests.length < guestSchema.max"
                                    @click="addGuest()"
                                    class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100"
                                >
                                    Add <span x-text="(guestSchema.label_singular || 'Guest').toLowerCase()"></span>
                                </button>
                                <button
                                    type="button"
                                    x-show="guests.length > guestSchema.min"
                                    @click="removeGuest()"
                                    class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
                                >
                                    Remove last <span x-text="(guestSchema.label_singular || 'guest').toLowerCase()"></span>
                                </button>
                            </div>
                        </fieldset>
                    </div>
                </template>

                <div class="pt-2 flex justify-between">
                    <button type="button" @click="step = 0" class="text-sm font-medium text-slate-700 hover:text-slate-900">Back</button>
                    <button type="button" @click="nextToSummary()" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800">Next</button>
                </div>
            </div>

            <div x-show="step === 2" class="space-y-4">
                <h3 class="text-base font-semibold text-slate-900">Review Summary & Checkout</h3>
                <div class="rounded-lg border border-slate-200 divide-y divide-slate-100">
                    <template x-for="(member, index) in members" :key="'summary-' + index">
                        <div class="p-4 flex items-start justify-between gap-4">
                            <div>
                                <p class="text-sm font-medium text-slate-900" x-text="memberLabel(member, index)"></p>
                                <p class="text-sm text-slate-600" x-text="member.email || '—'"></p>
                            </div>
                            <p class="text-sm font-medium text-slate-800" x-text="memberPriceDisplay(index)"></p>
                        </div>
                    </template>
                    <template x-for="(guest, gIdx) in guests" :key="'gsummary-' + gIdx">
                        <div class="p-4 flex items-start justify-between gap-4 bg-slate-50/50">
                            <div>
                                <p class="text-sm font-medium text-slate-900" x-text="guestLabel(guest, gIdx)"></p>
                                <template x-for="field in guestSchema.fields" :key="'gs-' + gIdx + '-' + field.key">
                                    <p class="text-xs text-slate-500" x-show="guest[field.key] && guest[field.key] !== ''" x-text="field.label + ': ' + guest[field.key]"></p>
                                </template>
                                <!-- <p class="mt-0.5 text-[11px] font-medium text-indigo-600" x-text="guestSchema.label_singular || 'Guest'"></p> -->
                            </div>
                            <p class="text-sm font-medium text-slate-800" x-text="guestPriceDisplay()"></p>
                        </div>
                    </template>
                </div>
                <div class="flex items-center justify-between rounded-lg bg-slate-50 border border-slate-200 p-4">
                    <span class="text-sm font-medium text-slate-700">Total (estimated)</span>
                    <span class="text-lg font-semibold text-slate-900" x-text="totalDisplay"></span>
                </div>
                <p class="text-xs text-slate-500">Final amount is calculated on the server when you submit.</p>
                <div class="pt-2 flex justify-between">
                    <button type="button" @click="backFromSummary()" class="text-sm font-medium text-slate-700 hover:text-slate-900">Back</button>
                    <template x-if="step === 2">
                        <button
                            type="submit"
                            :disabled="isSubmitting"
                            class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white disabled:cursor-not-allowed disabled:opacity-60"
                            :class="!isSubmitting && 'hover:bg-indigo-800'"
                            x-text="isSubmitting ? 'Checking out....' : 'Checkout'"
                        ></button>
                    </template>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function rmRegisterWizard() {
    return {
        step: 0,
        isSubmitting: false,
        mode: 'group_flat',
        get isIndividual() {
            return this.mode === 'individual' || this.limits.max <= 1;
        },
        get skipsMembersStep() {
            return this.isIndividual && !this.guestSchema.enabled;
        },
        get stepLabels() {
            if (this.isIndividual && this.guestSchema.enabled) {
                return ['Registrant', (this.guestSchema.label_plural || 'Guests'), 'Review Summary & Checkout'];
            }
            if (this.isIndividual) {
                return ['Registrant', 'Review Summary & Checkout'];
            }
            return ['Leader', 'Members', 'Review Summary & Checkout'];
        },
        get stepperIndex() {
            if (this.skipsMembersStep) {
                return this.step === 0 ? 0 : 1;
            }
            return this.step;
        },
        get membersStepHeading() {
            if (!this.isIndividual) {
                return 'Additional Member';
            }
            const addonName = this.guestSchema.label_plural || this.guestSchema.label_singular || 'Guest';
            const optional = this.guestSchema.min === 0 ? ' (Optional)' : '';
            return 'Event package allows to register ' + addonName + '. Kindly fill-up the additional information below.' + optional;
        },
        schema: { fields: [] },
        guestSchema: { fields: [], enabled: false, label_singular: 'Guest', label_plural: 'Guests', min: 0, max: 0, price: 0 },
        limits: { min: 1, max: 1, require_all_members: false },
        members: [],
        guests: [],
        fieldErrors: {},
        memberErrors: {},
        guestErrors: {},
        serializedMembers: '[]',
        serializedGuests: '[]',
        pricing: {},
        currency: 'SGD',
        coverage: 'local',
        phoneCountryCodes: [],
        get defaultDial() {
            return '+65';
        },
        init(schema, limits, members, pricing, guestSchema, guestsInput, currency, coverage, phoneCountryCodes, mode) {
            this.mode = mode || 'group_flat';
            this.schema = schema || { fields: [] };
            this.guestSchema = Object.assign(
                { fields: [], enabled: false, label_singular: 'Guest', label_plural: 'Guests', min: 0, max: 0, price: 0 },
                guestSchema || {}
            );
            this.limits = Object.assign({ min: 1, max: 1, require_all_members: false }, limits || {});
            this.pricing = pricing || {};
            this.currency = currency || 'SGD';
            this.coverage = coverage || 'local';
            this.phoneCountryCodes = Array.isArray(phoneCountryCodes) ? phoneCountryCodes : [];
            this.members = Array.isArray(members) && members.length
                ? members.map((m) => this.hydratePhoneFields(m, this.schema.fields))
                : [this.emptyMember()];
            const target = this.limits.require_all_members ? this.limits.max : this.limits.min;
            while (this.members.length < target) this.addMember();
            if (this.limits.require_all_members && this.members.length > this.limits.max) {
                this.members = this.members.slice(0, this.limits.max);
            }
            if (this.guestSchema.enabled) {
                this.guests = Array.isArray(guestsInput) && guestsInput.length
                    ? guestsInput.map((g) => this.hydratePhoneFields(g, this.guestSchema.fields))
                    : [];
                while (this.guests.length < this.guestSchema.min) this.addGuest();
            }
        },
        emptyMember() {
            const member = {};
            (this.schema.fields || []).forEach((field) => {
                if (field.type === 'phone') {
                    member[field.key] = '';
                    member[field.key + '__dial'] = this.defaultDial;
                    member[field.key + '__local'] = '';
                } else {
                    member[field.key] = field.type === 'checkbox' ? false : (field.type === 'checkbox_group' ? [] : '');
                }
            });
            return member;
        },
        emptyGuest() {
            const guest = {};
            (this.guestSchema.fields || []).forEach((field) => {
                if (field.type === 'phone') {
                    guest[field.key] = '';
                    guest[field.key + '__dial'] = this.defaultDial;
                    guest[field.key + '__local'] = '';
                } else {
                    guest[field.key] = field.type === 'checkbox' ? false : (field.type === 'checkbox_group' ? [] : '');
                }
            });
            return guest;
        },
        hydratePhoneFields(row, fields) {
            const data = Object.assign({}, row || {});
            (fields || []).forEach((field) => {
                if (field.type !== 'phone') return;
                const parts = this.splitPhone(data[field.key] || '');
                data[field.key + '__dial'] = parts.dial;
                data[field.key + '__local'] = parts.local;
                this.syncPhone(data, field.key);
            });
            return data;
        },
        splitPhone(full) {
            const value = String(full || '').trim();
            if (this.coverage !== 'international') {
                let digits = value.replace(/\D+/g, '');
                if (digits.startsWith('65') && digits.length > 8) {
                    digits = digits.slice(2);
                }
                return { dial: this.defaultDial, local: digits };
            }
            if (!value) {
                return { dial: this.defaultDial, local: '' };
            }
            const normalized = value.startsWith('+') ? value : '+' + value.replace(/^\+/, '');
            const codes = [...this.phoneCountryCodes].sort((a, b) => String(b.dial).length - String(a.dial).length);
            for (const cc of codes) {
                if (normalized.startsWith(cc.dial)) {
                    return {
                        dial: cc.dial,
                        local: normalized.slice(cc.dial.length).replace(/\D+/g, ''),
                    };
                }
            }
            return { dial: this.defaultDial, local: value.replace(/\D+/g, '') };
        },
        composePhone(dial, local) {
            let code = String(dial || this.defaultDial).trim() || this.defaultDial;
            if (!code.startsWith('+')) code = '+' + code.replace(/^\+/, '');
            let digits = String(local || '').replace(/\D+/g, '').replace(/^0+/, '');
            const dialDigits = code.replace(/^\+/, '');
            if (digits.startsWith(dialDigits)) {
                digits = digits.slice(dialDigits.length);
            }
            return digits ? code + digits : '';
        },
        syncPhone(row, key) {
            if (!row) return;
            if (this.coverage !== 'international') {
                row[key + '__dial'] = this.defaultDial;
            }
            row[key] = this.composePhone(row[key + '__dial'] || this.defaultDial, row[key + '__local'] || '');
        },
        serializeRow(row, fields) {
            const out = {};
            (fields || []).forEach((field) => {
                if (field.type === 'phone') {
                    this.syncPhone(row, field.key);
                    out[field.key] = row[field.key] || '';
                } else {
                    out[field.key] = row[field.key];
                }
            });
            return out;
        },
        wideField(field) {
            return ['textarea', 'radio', 'checkbox_group'].includes(field.type);
        },
        fieldPlaceholder(field) {
            if (!field) return '';
            const placeholder = String(field.placeholder || '').trim();
            if (placeholder !== '') return placeholder;
            return String(field.label || '');
        },
        inputType(type) {
            if (type === 'email') return 'email';
            if (type === 'number') return 'number';
            if (type === 'date') return 'date';
            if (type === 'phone') return 'tel';
            return 'text';
        },
        addMember() {
            if (this.members.length < this.limits.max) this.members.push(this.emptyMember());
        },
        removeMember() {
            if (this.limits.require_all_members) return;
            if (this.members.length > this.limits.min) this.members.pop();
        },
        addGuest() {
            if (this.guests.length < this.guestSchema.max) this.guests.push(this.emptyGuest());
        },
        removeGuest() {
            if (this.guests.length > this.guestSchema.min) this.guests.pop();
        },
        isValidEmail(value) {
            const email = String(value || '').trim();
            if (email === '') return false;
            // Practical format check aligned with common email rules.
            return /^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$/.test(email);
        },
        isEmailField(field) {
            return field && (field.type === 'email' || field.key === 'email' || field.maps_to === 'email');
        },
        validateFieldLive(field, data, scope, index) {
            if (!this.isEmailField(field)) return;
            const message = this.fieldValidationMessage(field, data);
            if (scope === 'leader') {
                if (message) this.fieldErrors[field.key] = message;
                else delete this.fieldErrors[field.key];
                return;
            }
            if (scope === 'member') {
                if (!this.memberErrors[index]) this.memberErrors[index] = {};
                if (message) this.memberErrors[index][field.key] = message;
                else delete this.memberErrors[index][field.key];
                return;
            }
            if (scope === 'guest') {
                if (!this.guestErrors[index]) this.guestErrors[index] = {};
                if (message) this.guestErrors[index][field.key] = message;
                else delete this.guestErrors[index][field.key];
            }
        },
        fieldValidationMessage(field, data) {
            if (!field || !data) return '';
            const label = field.label || field.key || 'This field';
            if (field.type === 'phone') {
                this.syncPhone(data, field.key);
            }

            const val = data[field.key];
            if (field.type === 'checkbox') {
                return field.required && !val ? (label + ' is required.') : '';
            }
            if (field.type === 'checkbox_group') {
                return field.required && (!Array.isArray(val) || !val.length)
                    ? (label + ' is required.')
                    : '';
            }

            const stringValue = val === null || val === undefined ? '' : String(val).trim();
            if (field.required && stringValue === '') {
                return label + ' is required.';
            }
            if (stringValue === '') {
                return '';
            }

            if (this.isEmailField(field) && !this.isValidEmail(stringValue)) {
                return 'Please enter a valid email address.';
            }

            if (field.type === 'number' && Number.isNaN(Number(stringValue))) {
                return label + ' must be a number.';
            }

            if (field.type === 'date' && Number.isNaN(Date.parse(stringValue))) {
                return 'Please enter a valid date.';
            }

            if (field.type === 'phone' || field.key === 'contact' || field.maps_to === 'contact') {
                const digits = stringValue.replace(/\D+/g, '');
                const localDigits = this.coverage === 'international'
                    ? digits
                    : digits.replace(/^65/, '');
                if (this.coverage === 'international') {
                    if (localDigits.length < 6 || localDigits.length > 15) {
                        return 'Please enter a valid contact number.';
                    }
                } else if (localDigits.length !== 8) {
                    return 'Please enter a valid 8-digit contact number.';
                }
            }

            if ((field.key === 'nric' || field.maps_to === 'nric') && !/^[0-9]{4}$/.test(stringValue)) {
                return 'Please enter the last 4 digits of your NRIC.';
            }

            if (
                this.coverage !== 'international'
                && (field.key === 'postcode' || field.maps_to === 'postcode')
                && !/^[0-9]{6}$/.test(stringValue)
            ) {
                return 'Please enter a valid 6-digit postal code.';
            }

            return '';
        },
        validateRow(fields, data) {
            const errors = {};
            let firstMessage = '';
            (fields || []).forEach((field) => {
                const message = this.fieldValidationMessage(field, data);
                if (message) {
                    errors[field.key] = message;
                    if (!firstMessage) firstMessage = message;
                }
            });
            return { errors, firstMessage };
        },
        validateMember(member) {
            return this.validateRow(this.schema.fields, member);
        },
        validateGuest(guest) {
            return this.validateRow(this.guestSchema.fields, guest);
        },
        nextFromLeader() {
            const result = this.validateMember(this.members[0]);
            this.fieldErrors = result.errors;
            if (result.firstMessage) {
                alert(result.firstMessage);
                return;
            }
            if (this.skipsMembersStep) {
                this.nextToSummary();
                return;
            }
            this.step = 1;
        },
        backFromSummary() {
            this.step = this.skipsMembersStep ? 0 : 1;
        },
        nextToSummary() {
            if (this.limits.require_all_members && this.members.length !== this.limits.max) {
                alert('This package requires exactly ' + this.limits.max + ' registrant(s).');
                return;
            }

            this.memberErrors = {};
            for (let i = 0; i < this.members.length; i++) {
                const result = this.validateMember(this.members[i]);
                if (result.firstMessage) {
                    if (i === 0) {
                        this.fieldErrors = result.errors;
                    } else {
                        this.memberErrors[i] = result.errors;
                    }
                    alert(result.firstMessage + (i > 0 ? ' (Member ' + (i + 1) + ')' : ''));
                    if (i === 0) this.step = 0;
                    return;
                }
            }

            this.guestErrors = {};
            if (this.guestSchema.enabled) {
                const gLabel = (this.guestSchema.label_singular || 'guest').toLowerCase();
                if (this.guests.length < this.guestSchema.min) {
                    alert('At least ' + this.guestSchema.min + ' ' + gLabel + '(s) required.');
                    return;
                }
                for (let g = 0; g < this.guests.length; g++) {
                    const result = this.validateGuest(this.guests[g]);
                    if (result.firstMessage) {
                        this.guestErrors[g] = result.errors;
                        alert(result.firstMessage + ' (' + gLabel + ' ' + (g + 1) + ')');
                        return;
                    }
                }
            }
            this.step = 2;
        },
        memberLabel(member, index) {
            const name = [member.given_name, member.family_name].filter(Boolean).join(' ');
            if (this.isIndividual) {
                return (name ? name : 'Registrant');
            }
            return (index === 0 ? 'Leader' : 'Member ' + (index + 1)) + (name ? ': ' + name : '');
        },
        guestLabel(guest, index) {
            const name = [guest.given_name, guest.family_name].filter(Boolean).join(' ');
            const label = this.guestSchema.label_singular || 'Guest';
            return label + ' ' + (index + 1) + (name ? ': ' + name : '');
        },
        formatCurrency(amount) {
            if (amount <= 0) return 'FREE';
            const decimals = amount % 1 === 0 ? 0 : 2;
            return this.currency + ' ' + amount.toFixed(decimals);
        },
        memberPriceDisplay(index) {
            const item = (this.pricing.member_pricing || [])[index];
            if (!item) return '—';
            return this.formatCurrency(parseFloat(item.unit_price || 0));
        },
        guestPriceDisplay() {
            return this.formatCurrency(parseFloat(this.guestSchema.price || 0));
        },
        get totalDisplay() {
            let total = 0;
            if (this.pricing.total_display && !this.guestSchema.enabled) return this.pricing.total_display;
            for (let i = 0; i < this.members.length; i++) {
                const item = (this.pricing.member_pricing || [])[i];
                total += item ? parseFloat(item.unit_price || 0) : 0;
            }
            if (this.guestSchema.enabled) {
                total += this.guests.length * parseFloat(this.guestSchema.price || 0);
            }
            return this.formatCurrency(total);
        },
        prepareSubmit(event) {
            if (this.step === 2 && this.isSubmitting) {
                if (event) event.preventDefault();
                return;
            }

            // Enter in an input submits the form even on earlier steps (submit button is only hidden via CSS).
            // Block that and advance with the same validation as the Next buttons.
            if (this.step !== 2) {
                if (event) event.preventDefault();
                if (this.step === 0) {
                    this.nextFromLeader();
                } else if (this.step === 1) {
                    this.nextToSummary();
                }
                return;
            }

            if (this.limits.require_all_members && this.members.length !== this.limits.max) {
                if (event) event.preventDefault();
                this.step = 1;
                alert('This package requires exactly ' + this.limits.max + ' registrant(s).');
                return;
            }

            for (let i = 0; i < this.members.length; i++) {
                const result = this.validateMember(this.members[i]);
                if (result.firstMessage) {
                    if (event) event.preventDefault();
                    if (i === 0) {
                        this.fieldErrors = result.errors;
                        this.step = 0;
                    } else {
                        this.memberErrors[i] = result.errors;
                        this.step = 1;
                    }
                    alert(result.firstMessage + (i > 0 ? ' (Member ' + (i + 1) + ')' : ''));
                    return;
                }
            }

            if (this.guestSchema.enabled) {
                const gLabel = (this.guestSchema.label_singular || 'guest').toLowerCase();
                if (this.guests.length < this.guestSchema.min) {
                    if (event) event.preventDefault();
                    this.step = 1;
                    alert('At least ' + this.guestSchema.min + ' ' + gLabel + '(s) required.');
                    return;
                }
                for (let g = 0; g < this.guests.length; g++) {
                    const result = this.validateGuest(this.guests[g]);
                    if (result.firstMessage) {
                        if (event) event.preventDefault();
                        this.guestErrors[g] = result.errors;
                        this.step = 1;
                        alert(result.firstMessage + ' (' + gLabel + ' ' + (g + 1) + ')');
                        return;
                    }
                }
            }

            this.serializedMembers = JSON.stringify(
                this.members.map((m) => this.serializeRow(m, this.schema.fields))
            );
            this.serializedGuests = this.guestSchema.enabled
                ? JSON.stringify(this.guests.map((g) => this.serializeRow(g, this.guestSchema.fields)))
                : '[]';
            this.isSubmitting = true;
        }
    };
}
</script>
