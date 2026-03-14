<?php
/**
 * KYC (Know Your Customer) Manager
 *
 * Handles email verification, KYC status tracking, admin columns,
 * user-profile section, and WooCommerce purchase restrictions.
 *
 * @package PremierBioLabs\ResearchAccessGate
 * @since   2.1.0
 */

declare(strict_types=1);

namespace PremierBioLabs\ResearchAccessGate;

defined('ABSPATH') || exit;

class KYC {

    /**
     * KYC status values
     */
    public const STATUS_PENDING  = 'pending';
    public const STATUS_VERIFIED = 'verified';
    public const STATUS_REJECTED = 'rejected';

    /**
     * User-meta keys managed by this class
     */
    public const META_KYC_STATUS        = 'rag_kyc_status';
    public const META_EMAIL_VERIFIED     = 'rag_email_verified';
    public const META_EMAIL_VERIFY_TOKEN = 'rag_email_verify_token';
    public const META_EMAIL_VERIFY_EXPIRY = 'rag_email_verify_expiry';
    public const META_AGE_CONFIRMED      = 'rag_age_confirmed';
    public const META_ORGANIZATION       = 'rag_organization';

    private Settings $settings;

    public function __construct(Settings $settings) {
        $this->settings = $settings;
    }

    /* ------------------------------------------------------------------
     *  Hook registration (called from Plugin::register_hooks)
     * ----------------------------------------------------------------*/

    /**
     * Register all KYC-related WordPress hooks.
     */
    public function init(): void {
        // Admin: user-list columns
        add_filter('manage_users_columns', [$this, 'add_user_columns']);
        add_filter('manage_users_custom_column', [$this, 'render_user_column'], 10, 3);
        add_filter('manage_users_sortable_columns', [$this, 'sortable_user_columns']);
        add_action('pre_get_users', [$this, 'sort_user_columns']);

        // Admin: user-profile section
        add_action('show_user_profile', [$this, 'render_profile_section']);
        add_action('edit_user_profile', [$this, 'render_profile_section']);
        add_action('personal_options_update', [$this, 'save_profile_section']);
        add_action('edit_user_profile_update', [$this, 'save_profile_section']);

        // WooCommerce: block checkout for unverified users
        if ($this->settings->get('restrict_unverified_purchases') === 'yes') {
            add_action('woocommerce_checkout_process', [$this, 'restrict_checkout'], 10);
            add_action('woocommerce_before_checkout_form', [$this, 'checkout_notice'], 10);
        }
    }

    /* ------------------------------------------------------------------
     *  KYC status helpers
     * ----------------------------------------------------------------*/

    /**
     * Set initial KYC data for a newly registered user.
     *
     * @param int    $user_id      WordPress user ID.
     * @param string $organization Organization / institution name.
     * @param bool   $age_confirmed Whether the user confirmed 21+ age.
     */
    public function set_initial_status(int $user_id, string $organization, bool $age_confirmed): void {
        update_user_meta($user_id, self::META_KYC_STATUS, self::STATUS_PENDING);
        update_user_meta($user_id, self::META_EMAIL_VERIFIED, 'no');
        update_user_meta($user_id, self::META_AGE_CONFIRMED, $age_confirmed ? 'yes' : 'no');

        if (!empty($organization)) {
            update_user_meta($user_id, self::META_ORGANIZATION, $organization);
        }
    }

    /**
     * Get the KYC status for a user.
     *
     * @param int $user_id WordPress user ID.
     * @return string One of the STATUS_* constants.
     */
    public static function get_status(int $user_id): string {
        $status = get_user_meta($user_id, self::META_KYC_STATUS, true);
        if (in_array($status, [self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_REJECTED], true)) {
            return $status;
        }
        return self::STATUS_PENDING;
    }

    /**
     * Mark a user as KYC-verified.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function verify_user(int $user_id): void {
        update_user_meta($user_id, self::META_KYC_STATUS, self::STATUS_VERIFIED);

        /**
         * Fires after a user is KYC-verified.
         *
         * @param int $user_id
         */
        do_action('rag_kyc_user_verified', $user_id);
    }

    /**
     * Mark a user as KYC-rejected.
     *
     * @param int $user_id WordPress user ID.
     */
    public static function reject_user(int $user_id): void {
        update_user_meta($user_id, self::META_KYC_STATUS, self::STATUS_REJECTED);

        /**
         * Fires after a user is KYC-rejected.
         *
         * @param int $user_id
         */
        do_action('rag_kyc_user_rejected', $user_id);
    }

    /* ------------------------------------------------------------------
     *  Email verification
     * ----------------------------------------------------------------*/

    /**
     * Generate a verification token, store it, and send the verification e-mail.
     *
     * @param int $user_id WordPress user ID.
     * @return bool True if the email was sent.
     */
    public function send_verification_email(int $user_id): bool {
        $user = get_userdata($user_id);
        if (!$user) {
            return false;
        }

        $token = bin2hex(random_bytes(32));
        update_user_meta($user_id, self::META_EMAIL_VERIFY_TOKEN, hash('sha256', $token));
        update_user_meta($user_id, self::META_EMAIL_VERIFY_EXPIRY, time() + 2 * DAY_IN_SECONDS);

        $verify_url = add_query_arg([
            'rag_verify' => '1',
            'uid'        => $user_id,
            'token'      => $token,
        ], home_url('/'));

        $company   = $this->settings->get('company_name', 'Research Access Gate');
        $site_name = get_bloginfo('name');

        $subject = sprintf(
            /* translators: %s: site name */
            __('[%s] Verify your email address', 'research-access-gate'),
            $site_name
        );

        $message = sprintf(
            /* translators: 1: first name, 2: company, 3: verification URL, 4: site name */
            __(
                "Hello %1\$s,\n\n"
                . "Thank you for registering with %2\$s.\n\n"
                . "Please verify your email address by clicking the link below:\n\n"
                . "%3\$s\n\n"
                . "This link will expire in 48 hours.\n\n"
                . "If you did not create an account, please ignore this email.\n\n"
                . "— %4\$s",
                'research-access-gate'
            ),
            $user->first_name ?: $user->user_login,
            esc_html($company),
            esc_url($verify_url),
            esc_html($site_name)
        );

        $headers = ['Content-Type: text/plain; charset=UTF-8'];

        return wp_mail($user->user_email, $subject, $message, $headers);
    }

    /**
     * Handle email-verification callback (front-end GET request).
     *
     * Hooked into `template_redirect` by Plugin.
     */
    public function handle_email_verification(): void {
        if (empty($_GET['rag_verify'])) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
            return;
        }

        $user_id = absint($_GET['uid'] ?? 0); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
        $token   = sanitize_text_field(wp_unslash($_GET['token'] ?? '')); // phpcs:ignore WordPress.Security.NonceVerification.Recommended

        if ($user_id <= 0 || empty($token)) {
            wp_die(
                esc_html__('Invalid verification link.', 'research-access-gate'),
                esc_html__('Verification Failed', 'research-access-gate'),
                ['response' => 400]
            );
            return;
        }

        $stored_hash = get_user_meta($user_id, self::META_EMAIL_VERIFY_TOKEN, true);

        if (empty($stored_hash) || !hash_equals($stored_hash, hash('sha256', $token))) {
            wp_die(
                esc_html__('This verification link is invalid or has already been used.', 'research-access-gate'),
                esc_html__('Verification Failed', 'research-access-gate'),
                ['response' => 400]
            );
            return;
        }

        // Check token expiration (48 hours)
        $expiry = (int) get_user_meta($user_id, self::META_EMAIL_VERIFY_EXPIRY, true);
        if ($expiry > 0 && time() > $expiry) {
            delete_user_meta($user_id, self::META_EMAIL_VERIFY_TOKEN);
            delete_user_meta($user_id, self::META_EMAIL_VERIFY_EXPIRY);
            wp_die(
                esc_html__('This verification link has expired. Please log in and request a new one.', 'research-access-gate'),
                esc_html__('Link Expired', 'research-access-gate'),
                ['response' => 400]
            );
            return;
        }

        // Mark verified
        update_user_meta($user_id, self::META_EMAIL_VERIFIED, 'yes');
        delete_user_meta($user_id, self::META_EMAIL_VERIFY_TOKEN);
        delete_user_meta($user_id, self::META_EMAIL_VERIFY_EXPIRY);

        // If all KYC criteria met automatically, promote to verified
        $this->maybe_auto_verify($user_id);

        /**
         * Fires after a user's email address is verified.
         *
         * @param int $user_id
         */
        do_action('rag_email_verified', $user_id);

        // Redirect to my-account or home with success message
        $redirect = function_exists('wc_get_page_permalink')
            ? wc_get_page_permalink('myaccount')
            : home_url('/');

        $redirect = add_query_arg('rag_email_verified', '1', $redirect);

        wp_safe_redirect($redirect);
        exit;
    }

    /**
     * Auto-verify a user if all automated criteria are met.
     *
     * @param int $user_id WordPress user ID.
     */
    private function maybe_auto_verify(int $user_id): void {
        $email_ok       = get_user_meta($user_id, self::META_EMAIL_VERIFIED, true) === 'yes';
        $terms_ok       = get_user_meta($user_id, 'rag_terms_accepted', true) === 'yes';
        $age_ok         = get_user_meta($user_id, self::META_AGE_CONFIRMED, true) === 'yes';
        $current_status = self::get_status($user_id);

        if ($current_status === self::STATUS_PENDING && $email_ok && $terms_ok && $age_ok) {
            self::verify_user($user_id);
        }
    }

    /* ------------------------------------------------------------------
     *  Admin – Users list columns
     * ----------------------------------------------------------------*/

    /**
     * Add KYC columns to the Users list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_user_columns(array $columns): array {
        $columns['rag_kyc_status']   = __('KYC Status', 'research-access-gate');
        $columns['rag_organization'] = __('Organization', 'research-access-gate');
        return $columns;
    }

    /**
     * Render custom column content.
     *
     * @param string $output      Existing column content.
     * @param string $column_name Column identifier.
     * @param int    $user_id     User ID.
     * @return string Column HTML.
     */
    public function render_user_column(string $output, string $column_name, int $user_id): string {
        switch ($column_name) {
            case 'rag_kyc_status':
                $status = self::get_status($user_id);
                $labels = [
                    self::STATUS_PENDING  => '<span style="color:#996800;">&#9679; ' . esc_html__('Pending', 'research-access-gate') . '</span>',
                    self::STATUS_VERIFIED => '<span style="color:#00a32a;">&#9679; ' . esc_html__('Verified', 'research-access-gate') . '</span>',
                    self::STATUS_REJECTED => '<span style="color:#d63638;">&#9679; ' . esc_html__('Rejected', 'research-access-gate') . '</span>',
                ];
                return $labels[$status] ?? esc_html($status);

            case 'rag_organization':
                $org = get_user_meta($user_id, self::META_ORGANIZATION, true);
                return esc_html($org ?: '—');
        }

        return $output;
    }

    /**
     * Make KYC Status column sortable.
     *
     * @param array $columns Sortable columns.
     * @return array Modified columns.
     */
    public function sortable_user_columns(array $columns): array {
        $columns['rag_kyc_status'] = 'rag_kyc_status';
        return $columns;
    }

    /**
     * Handle sorting by KYC status.
     *
     * @param \WP_User_Query $query User query.
     */
    public function sort_user_columns(\WP_User_Query $query): void {
        if (!is_admin()) {
            return;
        }

        $orderby = $query->get('orderby');
        if ($orderby === 'rag_kyc_status') {
            $query->set('meta_key', self::META_KYC_STATUS);
            $query->set('orderby', 'meta_value');
        }
    }

    /* ------------------------------------------------------------------
     *  Admin – User profile section
     * ----------------------------------------------------------------*/

    /**
     * Render the KYC section on user profile pages.
     *
     * @param \WP_User $user User object.
     */
    public function render_profile_section(\WP_User $user): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        $kyc_status     = self::get_status($user->ID);
        $email_verified = get_user_meta($user->ID, self::META_EMAIL_VERIFIED, true) === 'yes';
        $age_confirmed  = get_user_meta($user->ID, self::META_AGE_CONFIRMED, true) === 'yes';
        $organization   = get_user_meta($user->ID, self::META_ORGANIZATION, true);
        $business_type  = get_user_meta($user->ID, 'rag_business_type', true);
        $phone          = get_user_meta($user->ID, 'rag_phone', true);
        $terms_accepted = get_user_meta($user->ID, 'rag_terms_accepted', true) === 'yes';
        $terms_at       = get_user_meta($user->ID, 'rag_terms_accepted_at', true);
        $terms_ip       = get_user_meta($user->ID, 'rag_terms_accepted_ip', true);
        $terms_version  = get_user_meta($user->ID, 'rag_terms_version', true);

        wp_nonce_field('rag_kyc_profile_save', 'rag_kyc_nonce');
        ?>
        <h2><?php esc_html_e('KYC / Research Access Gate', 'research-access-gate'); ?></h2>
        <table class="form-table" role="presentation">
            <tr>
                <th><label for="rag_kyc_status"><?php esc_html_e('KYC Status', 'research-access-gate'); ?></label></th>
                <td>
                    <select name="rag_kyc_status" id="rag_kyc_status">
                        <option value="<?php echo esc_attr(self::STATUS_PENDING); ?>" <?php selected($kyc_status, self::STATUS_PENDING); ?>><?php esc_html_e('Pending', 'research-access-gate'); ?></option>
                        <option value="<?php echo esc_attr(self::STATUS_VERIFIED); ?>" <?php selected($kyc_status, self::STATUS_VERIFIED); ?>><?php esc_html_e('Verified', 'research-access-gate'); ?></option>
                        <option value="<?php echo esc_attr(self::STATUS_REJECTED); ?>" <?php selected($kyc_status, self::STATUS_REJECTED); ?>><?php esc_html_e('Rejected', 'research-access-gate'); ?></option>
                    </select>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e('Email Verified', 'research-access-gate'); ?></th>
                <td><?php echo $email_verified ? '<span style="color:green;">&#10003; ' . esc_html__('Yes', 'research-access-gate') . '</span>' : '<span style="color:#996800;">' . esc_html__('No', 'research-access-gate') . '</span>'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Age 21+ Confirmed', 'research-access-gate'); ?></th>
                <td><?php echo $age_confirmed ? '<span style="color:green;">&#10003; ' . esc_html__('Yes', 'research-access-gate') . '</span>' : '<span style="color:#996800;">' . esc_html__('No', 'research-access-gate') . '</span>'; ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Organization / Institution', 'research-access-gate'); ?></th>
                <td><?php echo esc_html($organization ?: '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Business Type', 'research-access-gate'); ?></th>
                <td><?php echo esc_html($business_type ?: '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Phone', 'research-access-gate'); ?></th>
                <td><?php echo esc_html($phone ?: '—'); ?></td>
            </tr>
            <tr>
                <th><?php esc_html_e('Terms Accepted', 'research-access-gate'); ?></th>
                <td>
                    <?php if ($terms_accepted): ?>
                        <span style="color:green;">&#10003; <?php esc_html_e('Yes', 'research-access-gate'); ?></span>
                        <?php if ($terms_at): ?>
                            <br><small><?php printf(esc_html__('Date: %s', 'research-access-gate'), esc_html($terms_at)); ?></small>
                        <?php endif; ?>
                        <?php if ($terms_ip): ?>
                            <br><small><?php printf(esc_html__('IP: %s', 'research-access-gate'), esc_html($terms_ip)); ?></small>
                        <?php endif; ?>
                        <?php if ($terms_version): ?>
                            <br><small><?php printf(esc_html__('Plugin version: %s', 'research-access-gate'), esc_html($terms_version)); ?></small>
                        <?php endif; ?>
                    <?php else: ?>
                        <span style="color:#996800;"><?php esc_html_e('No', 'research-access-gate'); ?></span>
                    <?php endif; ?>
                </td>
            </tr>
        </table>
        <?php
    }

    /**
     * Save KYC fields from the user-profile page.
     *
     * @param int $user_id User ID being saved.
     */
    public function save_profile_section(int $user_id): void {
        if (!current_user_can('manage_options')) {
            return;
        }

        if (
            !isset($_POST['rag_kyc_nonce'])
            || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['rag_kyc_nonce'])), 'rag_kyc_profile_save')
        ) {
            return;
        }

        $new_status = sanitize_text_field(wp_unslash($_POST['rag_kyc_status'] ?? ''));
        $allowed    = [self::STATUS_PENDING, self::STATUS_VERIFIED, self::STATUS_REJECTED];

        if (in_array($new_status, $allowed, true)) {
            $old_status = self::get_status($user_id);
            update_user_meta($user_id, self::META_KYC_STATUS, $new_status);

            if ($old_status !== $new_status) {
                if ($new_status === self::STATUS_VERIFIED) {
                    /** This action is documented above. */
                    do_action('rag_kyc_user_verified', $user_id);
                } elseif ($new_status === self::STATUS_REJECTED) {
                    /** This action is documented above. */
                    do_action('rag_kyc_user_rejected', $user_id);
                }
            }
        }
    }

    /* ------------------------------------------------------------------
     *  WooCommerce checkout restriction
     * ----------------------------------------------------------------*/

    /**
     * Block checkout for users that have not been KYC-verified.
     */
    public function restrict_checkout(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $status = self::get_status(get_current_user_id());

        if ($status !== self::STATUS_VERIFIED) {
            wc_add_notice(
                __('Your account is pending KYC verification. You cannot place orders until your account has been verified.', 'research-access-gate'),
                'error'
            );
        }
    }

    /**
     * Show an informational notice above the checkout form.
     */
    public function checkout_notice(): void {
        if (!is_user_logged_in()) {
            return;
        }

        $status = self::get_status(get_current_user_id());

        if ($status !== self::STATUS_VERIFIED) {
            wc_print_notice(
                __('Your account is pending KYC verification. Please check your email for a verification link or contact support.', 'research-access-gate'),
                'notice'
            );
        }
    }

    /* ------------------------------------------------------------------
     *  Helpers
     * ----------------------------------------------------------------*/

    /**
     * Get all meta keys managed by this class (for uninstall cleanup).
     *
     * @return string[]
     */
    public static function get_meta_keys(): array {
        return [
            self::META_KYC_STATUS,
            self::META_EMAIL_VERIFIED,
            self::META_EMAIL_VERIFY_TOKEN,
            self::META_EMAIL_VERIFY_EXPIRY,
            self::META_AGE_CONFIRMED,
            self::META_ORGANIZATION,
        ];
    }
}
