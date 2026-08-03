<?php
$input_class = $input_class ?? 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_dial_class = $phone_dial_class ?? 'rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-2 py-2 text-sm focus:border-indigo-500 focus:outline-none';
$phone_fixed_class = $phone_fixed_class ?? 'inline-flex items-center rounded-l-lg border border-r-0 border-slate-300 bg-slate-50 px-3 text-sm text-slate-600';
$phone_local_class = $phone_local_class ?? 'w-full rounded-r-lg rounded-l-none border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>

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
                                rows="3"
                                x-model="guests[gIdx][field.key]"
                                :placeholder="fieldPlaceholder(field)"
                                :required="!!field.required"
                            ></textarea>
                        </template>
                        <template x-if="field.type === 'select'">
                            <select
                                class="<?php echo esc_attr($input_class); ?>"
                                x-model="guests[gIdx][field.key]"
                                :required="!!field.required"
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
                                    <select class="<?php echo esc_attr($phone_dial_class); ?>" x-model="guests[gIdx][field.key + '__dial']" @change="syncPhone(guests[gIdx], field.key)">
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
                                    type="tel"
                                    inputmode="numeric"
                                    x-model="guests[gIdx][field.key + '__local']"
                                    @input="syncPhone(guests[gIdx], field.key)"
                                    :placeholder="fieldPlaceholder(field)"
                                    :required="!!field.required"
                                />
                            </div>
                        </template>
                        <template x-if="!['textarea','select','checkbox','checkbox_group','radio','phone'].includes(field.type)">
                            <input
                                class="<?php echo esc_attr($input_class); ?>"
                                :type="inputType(field.type)"
                                x-model="guests[gIdx][field.key]"
                                :placeholder="field.type === 'date' ? '' : fieldPlaceholder(field)"
                                :required="!!field.required"
                            />
                        </template>
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
            <span x-text="t('wizard.btn.add_guest', { guest: (guestSchema.label_singular || 'guest').toLowerCase() })"></span>
        </button>
        <button
            type="button"
            x-show="guests.length > 1"
            @click="removeGuest()"
            class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
        >
            <?php echo esc_html($t('guest_manage.btn.remove_last')); ?>
            <span x-text="(guestSchema.label_singular || 'guest').toLowerCase()"></span>
        </button>
    </div>
</fieldset>
