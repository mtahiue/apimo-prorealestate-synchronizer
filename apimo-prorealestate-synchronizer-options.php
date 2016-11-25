<?php

class ApimoProrealestateSynchronizerSettingsPage
{
    /**
     * Holds the values to be used in the fields callbacks
     */
    private $options;

    /**
     * Start up
     */
    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
    }

    /**
     * Add options page
     */
    public function add_plugin_page()
    {
        add_options_page(
            'Apimo API & WP Pro Real Estate 7 synchronizer settings',
            'Apimo API',
            'administrator',
            'apimo-prorealestate-synchronizer-settings',
            array($this, 'create_admin_page')
        );
    }

    /**
     * Options page callback
     */
    public function create_admin_page()
    {
        // Set class property
        $this->options = get_option('apimo_prorealestate_synchronizer_settings_options');
        ?>
        <div class="wrap">
            <h1>Apimo API & WP Pro Real Estate 7 synchronizer settings</h1>
            <form method="post" action="options.php">
                <?php
                // This prints out all hidden setting fields
                settings_fields('apimo_prorealestate_synchronizer_settings_options_group');
                do_settings_sections('apimo-prorealestate-synchronizer-settings');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Register and add settings
     */
    public function page_init()
    {
        register_setting(
            'apimo_prorealestate_synchronizer_settings_options_group', // Option group
            'apimo_prorealestate_synchronizer_settings_options', // Option name
            array($this, 'sanitize') // Sanitize
        );

        add_settings_section(
            'apimo_prorealestate_synchronizer_settings_options_group_section', // ID
            'API connection parameters', // Title
            array($this, 'print_section_info'), // Callback
            'apimo-prorealestate-synchronizer-settings' // Page
        );

        add_settings_field(
            'apimo_api_provider', // ID
            'Provider', // Title
            array($this, 'provider_callback'), // Callback
            'apimo-prorealestate-synchronizer-settings', // Page
            'apimo_prorealestate_synchronizer_settings_options_group_section' // Section
        );

        add_settings_field(
            'apimo_api_token',
            'Token',
            array($this, 'token_callback'),
            'apimo-prorealestate-synchronizer-settings',
            'apimo_prorealestate_synchronizer_settings_options_group_section'
        );

        add_settings_field(
            'apimo_api_agency',
            'Agency',
            array($this, 'agency_callback'),
            'apimo-prorealestate-synchronizer-settings',
            'apimo_prorealestate_synchronizer_settings_options_group_section'
        );
    }

    /**
     * Sanitize each setting field as needed
     *
     * @param array $input Contains all settings fields as array keys
     */
    public function sanitize($input)
    {
        $new_input = array();
        if (isset($input['apimo_api_provider']))
            $new_input['apimo_api_provider'] = sanitize_text_field($input['apimo_api_provider']);

        if (isset($input['apimo_api_token']))
            $new_input['apimo_api_token'] = sanitize_text_field($input['apimo_api_token']);

        if (isset($input['apimo_api_agency']))
            $new_input['apimo_api_agency'] = sanitize_text_field($input['apimo_api_agency']);

        return $new_input;
    }

    /**
     * Print the Section text
     */
    public function print_section_info()
    {
        print 'Enter API settings below:';
    }

    /**
     * Get the settings option array and print one of its values
     */
    public function provider_callback()
    {
        printf(
            '<input type="text" id="apimo_api_provider" name="apimo_prorealestate_synchronizer_settings_options[apimo_api_provider]" value="%s" />',
            isset($this->options['apimo_api_provider']) ? esc_attr($this->options['apimo_api_provider']) : ''
        );
    }

    public function token_callback()
    {
        printf(
            '<input type="text" id="apimo_api_token" name="apimo_prorealestate_synchronizer_settings_options[apimo_api_token]" value="%s" />',
            isset($this->options['apimo_api_token']) ? esc_attr($this->options['apimo_api_token']) : ''
        );
    }

    public function agency_callback()
    {
        printf(
            '<input type="text" id="apimo_api_agency" name="apimo_prorealestate_synchronizer_settings_options[apimo_api_agency]" value="%s" />',
            isset($this->options['apimo_api_agency']) ? esc_attr($this->options['apimo_api_agency']) : ''
        );
    }
}