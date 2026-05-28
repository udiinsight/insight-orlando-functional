<?php if(!defined('ABSPATH')) { die(); }  


add_action('plugins_loaded', function() {

                

	// Code Snippet Code
     
add_action('bricks/form/custom_action', function($form) {
    $form_fields = $form->get_fields();

    $phone    = $form_fields['form-field-tdulaw'] ?? '';
    $email    = $form_fields['form-field-cdc55c'] ?? '';
    $birthday = $form_fields['form-field-ofbfux'] ?? '';

    if (!$email) return;

    $user = get_user_by('email', $email);
    if (!$user) return;

    // Update billing phone in WP
    if ($phone) {
        update_user_meta($user->ID, 'billing_phone', $phone);
    }

    if ($birthday) {
        // Convert d/m/Y → Y-m-d (FunnelKit format)
        $dt = DateTime::createFromFormat('d/m/Y', $birthday);
        if (!$dt) return;
        $birthday_fk = $dt->format('Y-m-d');

        // Save to WP user meta
        update_user_meta($user->ID, 'bwfan_birthday_date', $birthday_fk);

        $api_key  = 'YOUR_FUNNELKIT_API_KEY';
        $site_url = get_site_url();
        $payload  = [
            'headers' => ['Content-Type' => 'application/json'],
            'body'    => json_encode([
                'email'  => $email,
                'f_name' => $user->first_name,
                'l_name' => $user->last_name,
                'fields' => ['7' => $birthday_fk],
            ]),
        ];

        // Step 1: Try to get the contact ID by email
        $get_response = wp_remote_get(
            "{$site_url}/wp-json/funnelkit-automations/contact?api_key={$api_key}&email=" . urlencode($email)
        );

        $get_body = json_decode(wp_remote_retrieve_body($get_response), true);
        $contact_id = $get_body['data']['contact']['contact']['id'] ?? null;

        if ($contact_id) {
            // Step 2a: Contact exists → update it
            wp_remote_post(
                "{$site_url}/wp-json/funnelkit-automations/contact/update/{$contact_id}?api_key={$api_key}",
                $payload
            );
        } else {
            // Step 2b: Contact doesn't exist → create it
            wp_remote_post(
                "{$site_url}/wp-json/funnelkit-automations/contact/add?api_key={$api_key}",
                $payload
            );
        }
    }
}, 10, 1);
    // End Code Snippet Code

}, 10);