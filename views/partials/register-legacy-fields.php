<?php
/**
 * Legacy hardcoded registration fields (non-v2 events).
 */
$title_options = rm_registration_title_options();
?>

<div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
    <div>
        <label for="nric" class="block text-sm font-medium text-slate-700 mb-2">NRIC (Last 4-digit) <span class="text-rose-500">*</span></label>
        <input id="nric" name="nric" type="text" value="<?php echo esc_attr($form_input['nric']); ?>" required placeholder="e.g. 1234" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['nric']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['nric'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['nric']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="title" class="block text-sm font-medium text-slate-700 mb-2">Title <span class="text-rose-500">*</span></label>
        <select id="title" name="title" required class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['title']) ? 'border-rose-400' : ''; ?>">
            <option value="">Please select title</option>
            <?php foreach ($title_options as $title_option) : ?>
                <option value="<?php echo esc_attr($title_option); ?>" <?php selected($form_input['title'], $title_option); ?>><?php echo esc_html($title_option); ?></option>
            <?php endforeach; ?>
        </select>
        <?php if (isset($form_errors['title'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['title']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="christianName" class="block text-sm font-medium text-slate-700 mb-2">Christian name <span class="text-rose-500">*</span></label>
        <input id="christianName" name="christianName" type="text" value="<?php echo esc_attr($form_input['christianName']); ?>" required placeholder="e.g. John" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['christianName']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['christianName'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['christianName']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="familyName" class="block text-sm font-medium text-slate-700 mb-2">Family name <span class="text-rose-500">*</span></label>
        <input id="familyName" name="familyName" type="text" value="<?php echo esc_attr($form_input['familyName']); ?>" required autocomplete="family-name" placeholder="e.g. Tan" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['familyName']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['familyName'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['familyName']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="givenName" class="block text-sm font-medium text-slate-700 mb-2">Given name <span class="text-rose-500">*</span></label>
        <input id="givenName" name="givenName" type="text" value="<?php echo esc_attr($form_input['givenName']); ?>" required autocomplete="given-name" placeholder="e.g. James" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['givenName']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['givenName'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['givenName']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="certificateName" class="block text-sm font-medium text-slate-700 mb-2">Certificate name <span class="text-rose-500">*</span></label>
        <input id="certificateName" name="certificateName" type="text" value="<?php echo esc_attr($form_input['certificateName']); ?>" required placeholder="e.g. James Tan" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['certificateName']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['certificateName'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['certificateName']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="email" class="block text-sm font-medium text-slate-700 mb-2">Email address <span class="text-rose-500">*</span></label>
        <input
            id="email"
            name="email"
            type="email"
            value="<?php echo esc_attr($form_input['email']); ?>"
            required
            autocomplete="email"
            inputmode="email"
            pattern="^[a-zA-Z0-9.!#$%&'*+/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)+$"
            title="Please enter a valid email address (e.g. name@example.com)"
            placeholder="e.g. name@example.com"
            class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['email']) ? 'border-rose-400' : ''; ?>"
        />
        <?php if (isset($form_errors['email'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['email']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="contact" class="block text-sm font-medium text-slate-700 mb-2">Contact number <span class="text-rose-500">*</span></label>
        <input id="contact" name="contact" type="tel" value="<?php echo esc_attr($form_input['contact']); ?>" required autocomplete="tel" placeholder="e.g. 91234567" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['contact']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['contact'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['contact']); ?></p><?php endif; ?>
    </div>
    <div class="sm:col-span-2">
        <label for="address1" class="block text-sm font-medium text-slate-700 mb-2">Address 1 <span class="text-rose-500">*</span></label>
        <input id="address1" name="address1" type="text" value="<?php echo esc_attr($form_input['address1']); ?>" required autocomplete="address-line1" placeholder="e.g. 123 Street Name" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['address1']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['address1'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['address1']); ?></p><?php endif; ?>
    </div>
    <div class="sm:col-span-2">
        <label for="address2" class="block text-sm font-medium text-slate-700 mb-2">Address 2</label>
        <input id="address2" name="address2" type="text" value="<?php echo esc_attr($form_input['address2']); ?>" autocomplete="address-line2" placeholder="e.g. Unit #01-01" class="<?php echo esc_attr($input_class); ?>" />
    </div>
    <div>
        <label for="postcode" class="block text-sm font-medium text-slate-700 mb-2">Postal code <span class="text-rose-500">*</span></label>
        <input id="postcode" name="postcode" type="text" value="<?php echo esc_attr($form_input['postcode']); ?>" required autocomplete="postal-code" placeholder="e.g. 123456" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['postcode']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['postcode'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['postcode']); ?></p><?php endif; ?>
    </div>
    <div>
        <label for="churchName" class="block text-sm font-medium text-slate-700 mb-2">Church name <span class="text-rose-500">*</span></label>
        <input id="churchName" name="churchName" type="text" value="<?php echo esc_attr($form_input['churchName']); ?>" required placeholder="e.g. Church of Singapore" class="<?php echo esc_attr($input_class); ?> <?php echo isset($form_errors['churchName']) ? 'border-rose-400' : ''; ?>" />
        <?php if (isset($form_errors['churchName'])) : ?><p class="mt-1 text-sm text-rose-600"><?php echo esc_html($form_errors['churchName']); ?></p><?php endif; ?>
    </div>
</div>
