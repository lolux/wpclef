<?php

class ClefInternalSettings { 
    const MS_ENABLED_OPTION = "clef_multisite_enabled";
    const MS_ALLOW_OVERRIDE_OPTION = 'clef_multsite_allow_override';
    const MS_OVERRIDE_OPTION = 'clef_multisite_override';

    private static $instance = null;

    public $use_individual_settings;
    private $settings;

    private function __construct() {
        $this->use_individual_settings = $this->check_individual_settings();
        $this->settings = $this->get_site_option();
    }

    private function check_individual_settings() {
        if (!$this->is_multisite_enabled()) return true;

        $override = false;

        if (get_site_option(self::MS_ALLOW_OVERRIDE_OPTION)) {
            $override = get_option(self::MS_OVERRIDE_OPTION, 'undefined');

            // check to see whether the override is set (it would not be set
            // if the blog had previously been used without multisite 
            // enabled). sets it if it is null.
            if ($override == "undefined") {
                $override = !!get_option(CLEF_OPTIONS_NAME);
                add_option(self::MS_OVERRIDE_OPTION, $override);
            }

        }

        return $override && !is_network_admin();
    }

    public function get($name) {
        return isset($this->settings[$name]) ? $this->settings[$name] : null;
    }

    public function set($name, $value) {
        if ($value && $this->get($name) !== $value) {
            $this->settings[$name] = $value;
            $this->update_site_option();
        }
    }

    public function remove($name) {
        $value = $this->get($name);
        if ($value) {
            unset($this->settings[$name]);
            $this->update_site_option();
        }
        return $value;
    }

    public function get_site_option() {
        $getter = $this->use_individual_settings ? 'get_option' : 'get_site_option';
        return $getter(CLEF_OPTIONS_NAME);
    }

    public function update_site_option() {
        $setter = $this->use_individual_settings ? 'update_option' : 'update_site_option';
        return $setter(CLEF_OPTIONS_NAME, $this->settings);
    }

    /**
     * Returns whether Clef is activated network-wide and whether it has 
     * been enabled on the whole network. 
     *
     * @return bool
     */
    public function is_multisite_enabled() {
        return is_plugin_active_for_network('wpclef/wpclef.php') && 
            get_site_option(self::MS_ENABLED_OPTION);
    }

    /**
        * Returns whether passwords are disabled site-wide.
        *
        * @return bool
        */
    public function passwords_disabled() {
        return $this->get('clef_password_settings_disable_passwords') 
            || $this->get('clef_password_settings_force') 
            || $this->get('clef_password_settings_disable_certain_passwords') != "Disabled";
    }

    /**
        * Returns whether passwords are disabled for a specific user based on 
        * user roles.
        *
        * @param WP_User $user
        * @return bool
        */
    public function passwords_are_disabled_for_user($user) {
        if (!$this->is_configured()) return false;

        $disabled = false;

        if ($this->get('clef_password_settings_force')) {
            $disabled = true;
        }

        if ($this->get( 'clef_password_settings_disable_passwords' ) && get_user_meta($user->ID, 'clef_id')) {
            $disabled = true;
        }

        $disable_certain_passwords = 
            $this->get( 'clef_password_settings_disable_certain_passwords');

        if ($disable_certain_passwords && $disable_certain_passwords != 'Disabled') {
            $max_role = strtolower($disable_certain_passwords);
            $role_map = array( 
                "subscriber",
                "editor",
                "author",
                "administrator",
                "super administrator"
            );

            foreach ($user->roles as &$role) {
                $rank = array_search($role, $role_map);
                if ($rank != 0 && $rank >= array_search($max_role, $role_map)) {
                    $disabled = true;
                    break;
                }
            } 

            if ($max_role == "super administrator" && is_super_admin($user->ID)) {
                $disabled = true;
            }
        }

        return $disabled;
    }

    public function xml_passwords_enabled() {
        return !$this->passwords_disabled() || 
            ($this->passwords_disabled() && 
             $this->get('clef_password_settings_xml_allowed'));
    }

    public function is_configured() {
        $app_id = $this->get('clef_settings_app_id');
        $app_secret = $this->get('clef_settings_app_secret');

        return $app_id && $app_secret && !empty($app_id) && !empty($app_secret);
    }

    public function multisite_disallow_settings_override() {
        return $this->is_multisite_enabled() && !get_site_option(self::MS_ALLOW_OVERRIDE_OPTION);
    }

    public static function start() {
        if (!isset(self::$instance) || self::$instance === null) {
            self::$instance = new self;
        }
        return self::$instance;
    }

    public static function get_instance() {
        return self::start();
    }
}

?>
