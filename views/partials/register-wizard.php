<?php
$form_schema = is_array($form_schema ?? null) ? $form_schema : ['fields' => []];
$group_limits = is_array($group_limits ?? null) ? $group_limits : ['min' => 1, 'max' => 1];
$registration_config = is_array($registration_config ?? null) ? $registration_config : [];
$pricing_preview = is_array($pricing_preview ?? null) ? $pricing_preview : [];
$members_input = is_array($members_input ?? null) ? $members_input : [];
$mode = (string) ($registration_config['mode'] ?? 'group_flat');
$schema_json = wp_json_encode($form_schema);
$limits_json = wp_json_encode($group_limits);
$members_json = wp_json_encode($members_input !== [] ? $members_input : []);
$pricing_json = wp_json_encode($pricing_preview);
$input_class = 'w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none';
?>

<div
    x-data="rmRegisterWizard()"
    x-init="init(<?php echo esc_attr($schema_json); ?>, <?php echo esc_attr($limits_json); ?>, <?php echo esc_attr($members_json); ?>, <?php echo esc_attr($pricing_json); ?>)"
    class="space-y-6"
>
    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-600">
        <template x-for="(label, index) in stepLabels" :key="index">
            <div class="flex items-center gap-2">
                <span
                    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-semibold"
                    :class="step === index ? 'bg-indigo-700 text-white' : (step > index ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-200 text-slate-600')"
                    x-text="index + 1"
                ></span>
                <span :class="step === index ? 'font-medium text-slate-900' : ''" x-text="label"></span>
                <span x-show="index < stepLabels.length - 1" class="text-slate-300 hidden sm:inline">→</span>
            </div>
        </template>
    </div>

    <form method="post" action="<?php echo esc_url($page_url); ?>" @submit="prepareSubmit">
        <?php wp_nonce_field('rm_register', 'rm_register_nonce'); ?>
        <input type="hidden" name="members_json" :value="serializedMembers" />

        <div x-show="step === 0" class="space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Group leader</h3>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                <template x-for="field in schema.fields" :key="'leader-' + field.key">
                    <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                        <label class="block text-sm font-medium text-slate-700 mb-2" x-text="field.label + (field.required ? ' *' : '')"></label>
                        <template x-if="field.type === 'textarea'">
                            <textarea class="<?php echo esc_attr($input_class); ?>" rows="3" x-model="members[0][field.key]"></textarea>
                        </template>
                        <template x-if="field.type === 'select'">
                            <select class="<?php echo esc_attr($input_class); ?>" x-model="members[0][field.key]">
                                <option value="">Please select</option>
                                <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                    <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                </template>
                            </select>
                        </template>
                        <template x-if="!['textarea','select','checkbox','checkbox_group','radio'].includes(field.type)">
                            <input
                                class="<?php echo esc_attr($input_class); ?>"
                                :type="inputType(field.type)"
                                x-model="members[0][field.key]"
                            />
                        </template>
                    </div>
                </template>
            </div>
            <div class="pt-2 flex justify-end">
                <button type="button" @click="nextFromLeader()" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800">
                    Continue to members
                </button>
            </div>
        </div>

        <div x-show="step === 1" class="space-y-4">
            <div class="flex items-center justify-between">
                <h3 class="text-base font-semibold text-slate-900">Additional members</h3>
                <p class="text-sm text-slate-500"><span x-text="members.length"></span> of <span x-text="limits.max"></span></p>
            </div>

            <template x-for="(member, mIndex) in members" :key="'wrap-' + mIndex">
                <div x-show="mIndex > 0" class="rounded-lg border border-slate-200 p-4 space-y-4">
                    <h4 class="text-sm font-medium text-slate-800">Member <span x-text="mIndex + 1"></span></h4>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
                        <template x-for="field in schema.fields" :key="'m-' + mIndex + '-' + field.key">
                            <div :class="wideField(field) ? 'sm:col-span-2' : ''">
                                <label class="block text-sm font-medium text-slate-700 mb-2" x-text="field.label + (field.required ? ' *' : '')"></label>
                                <template x-if="field.type === 'textarea'">
                                    <textarea class="<?php echo esc_attr($input_class); ?>" rows="3" x-model="members[mIndex][field.key]"></textarea>
                                </template>
                                <template x-if="field.type === 'select'">
                                    <select class="<?php echo esc_attr($input_class); ?>" x-model="members[mIndex][field.key]">
                                        <option value="">Please select</option>
                                        <template x-for="opt in (field.options || [])" :key="opt.value || opt">
                                            <option :value="opt.value || opt" x-text="opt.label || opt"></option>
                                        </template>
                                    </select>
                                </template>
                                <template x-if="!['textarea','select','checkbox','checkbox_group','radio'].includes(field.type)">
                                    <input class="<?php echo esc_attr($input_class); ?>" :type="inputType(field.type)" x-model="members[mIndex][field.key]" />
                                </template>
                            </div>
                        </template>
                    </div>
                </div>
            </template>

            <div class="flex gap-3">
                <button type="button" x-show="members.length < limits.max" @click="addMember()" class="rounded-lg border border-indigo-200 bg-indigo-50 px-4 py-2 text-sm font-medium text-indigo-700 hover:bg-indigo-100">Add member</button>
                <button type="button" x-show="members.length > limits.min" @click="removeMember()" class="rounded-lg border border-slate-200 px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Remove last member</button>
            </div>

            <div class="pt-2 flex justify-between">
                <button type="button" @click="step = 0" class="text-sm font-medium text-slate-700 hover:text-slate-900">Back</button>
                <button type="button" @click="nextToSummary()" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800">Review summary</button>
            </div>
        </div>

        <div x-show="step === 2" class="space-y-4">
            <h3 class="text-base font-semibold text-slate-900">Summary</h3>
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
            </div>
            <div class="flex items-center justify-between rounded-lg bg-slate-50 border border-slate-200 p-4">
                <span class="text-sm font-medium text-slate-700">Total (estimated)</span>
                <span class="text-lg font-semibold text-slate-900" x-text="totalDisplay"></span>
            </div>
            <p class="text-xs text-slate-500">Final amount is calculated on the server when you submit.</p>
            <div class="pt-2 flex justify-between">
                <button type="button" @click="step = 1" class="text-sm font-medium text-slate-700 hover:text-slate-900">Back</button>
                <button type="submit" class="rounded-lg bg-indigo-700 px-5 py-2.5 text-sm font-medium text-white hover:bg-indigo-800">Submit registration</button>
            </div>
        </div>
    </form>
</div>

<script>
function rmRegisterWizard() {
    return {
        step: 0,
        stepLabels: ['Leader', 'Members', 'Summary'],
        schema: { fields: [] },
        limits: { min: 1, max: 1 },
        members: [],
        serializedMembers: '[]',
        pricing: {},
        init(schema, limits, members, pricing) {
            this.schema = schema || { fields: [] };
            this.limits = limits || { min: 1, max: 1 };
            this.pricing = pricing || {};
            this.members = Array.isArray(members) && members.length ? members : [this.emptyMember()];
            while (this.members.length < this.limits.min) this.addMember();
        },
        emptyMember() {
            const member = {};
            (this.schema.fields || []).forEach((field) => {
                member[field.key] = field.type === 'checkbox' ? false : (field.type === 'checkbox_group' ? [] : '');
            });
            return member;
        },
        wideField(field) {
            return ['textarea', 'radio', 'checkbox_group'].includes(field.type);
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
            if (this.members.length > this.limits.min) this.members.pop();
        },
        validateMember(member) {
            for (const field of (this.schema.fields || [])) {
                if (!field.required) continue;
                const val = member[field.key];
                if (field.type === 'checkbox' && !val) return false;
                if (field.type === 'checkbox_group' && (!Array.isArray(val) || !val.length)) return false;
                if (val === '' || val === null || val === undefined) return false;
            }
            return true;
        },
        nextFromLeader() {
            if (!this.validateMember(this.members[0])) {
                alert('Please complete all required leader fields.');
                return;
            }
            this.step = 1;
        },
        nextToSummary() {
            for (let i = 0; i < this.members.length; i++) {
                if (!this.validateMember(this.members[i])) {
                    alert('Please complete all required fields for member ' + (i + 1) + '.');
                    return;
                }
            }
            this.step = 2;
        },
        memberLabel(member, index) {
            const name = [member.given_name, member.family_name].filter(Boolean).join(' ');
            return (index === 0 ? 'Leader' : 'Member ' + (index + 1)) + (name ? ': ' + name : '');
        },
        memberPriceDisplay(index) {
            const item = (this.pricing.member_pricing || [])[index];
            if (!item) return '—';
            const price = parseFloat(item.unit_price || 0);
            return price > 0 ? '$' + price.toFixed(2) : 'FREE';
        },
        get totalDisplay() {
            let total = 0;
            for (let i = 0; i < this.members.length; i++) {
                const item = (this.pricing.member_pricing || [])[i];
                total += item ? parseFloat(item.unit_price || 0) : 0;
            }
            return total > 0 ? '$' + total.toFixed(2) : 'FREE';
        },
        prepareSubmit() {
            this.serializedMembers = JSON.stringify(this.members);
        }
    };
}
</script>
