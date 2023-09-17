<?php
/*
Plugin Name: MyConnector Educativa Integrator
Description: A plugin to integrate with MyConnector API.
Version: 1.0
Author: Andrei Nastase
*/

// Activation hook
register_activation_hook(__FILE__, 'myconnector_activate_plugin');

function myconnector_activate_plugin() {
    
}

// Deactivation hook
register_deactivation_hook(__FILE__, 'myconnector_deactivate_plugin');

function myconnector_deactivate_plugin() {
    
}

function myconnector_enqueue_scripts() {
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-validation', 'https://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.19.3/jquery.validate.min.js', array('jquery'), '1.19.3', true);
    wp_add_inline_script('jquery-validation', 'jQuery.noConflict();');

    // Enqueue Tailwind CSS stylesheet
    wp_enqueue_style('tailwind-css', 'https://cdn.jsdelivr.net/npm/tailwindcss@2.2.15/dist/tailwind.min.css');

    // Enqueue the flag list
    wp_enqueue_style('flag-list-css', 'https://cdnjs.cloudflare.com/ajax/libs/flag-icon-css/3.5.0/css/flag-icon.min.css');

    // Enqueue libphonenumber-js
    wp_enqueue_script('libphonenumber-js', 'https://cdnjs.cloudflare.com/ajax/libs/libphonenumber-js/1.9.4/libphonenumber-js.min.js', array('jquery'), null, true);

    // Enqueue inputmask
    wp_enqueue_script('inputmask-js', 'https://cdn.jsdelivr.net/npm/inputmask@5.0.6/dist/jquery.inputmask.min.js', array('jquery'), null, true);

    wp_enqueue_script('myconnector-script', plugin_dir_url(__FILE__) . 'myconnector-script.js', array('jquery'), null, true);

    // Pass JSON URL to the script
    wp_localize_script('myconnector-script', 'myconnector_params', array(
        'jsonUrl' => plugin_dir_url(__FILE__) . 'countries-counties-cities.json',
        'plugin_dir_url' => plugin_dir_url(__FILE__)
    ));
}

add_action('wp_enqueue_scripts', 'myconnector_enqueue_scripts');


// Shortcode handler
function myconnector_form_shortcode($atts) {
    $atts = shortcode_atts(array(
        'event' => '',
        'ticket' => '',
        'redirect' => '',
        //'form_id' => '',
    ), $atts);

    $event_id = isset($atts['event']) ? $atts['event'] : '';
    $ticket_id = isset($atts['ticket']) ? $atts['ticket'] : '';
    $redirect = isset($atts['redirect']) ? $atts['redirect'] : '';
    //$form_id = $atts['form_id'];

    $manual_registration_api_url = "https://apiv3.myconnector.ro/v1/events/{$event_id}/monitoring/attendees";
    //$forms_api_url = "https://apiv3.myconnector.ro/v1/events/{$event_id}/registration/forms/{$form_id}";

    // Get the current site's language
    $current_site = get_blog_details();
    $site_path = parse_url($current_site->path, PHP_URL_PATH);
    $language_code = trim($site_path, '/');
    
    // Include the language-specific translation file
    $translations = include(plugin_dir_path(__FILE__) . "languages/{$language_code}.php");

    ob_start();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Validate form data
        $api_data = myconnector_prepare_form_data($language_code, $ticket_id, $event_id);
        if (!$api_data) {
            echo "Error: Invalid form data.";
            return;
        }
        $email = "educativa@myconnector.ro";
        $password = "myCeducativa2018";
        $device = "admin";

        $token = myconnector_api_login($email, $password, $device);
        if (!$token) {
            // Check for specific error messages in the API response
            $apiResponse = json_decode(wp_remote_retrieve_body($response), true);
            if (isset($apiResponse['message']) && !empty($apiResponse['message'])) {
                echo 'Error: ' . implode(', ', $apiResponse['message']);
            } else {
                echo "Error: Failed to obtain the bearer token.";
            }
            return;
        }

        // Submit form data to the API
        $response = myconnector_submit_form_data($token, $api_data, $manual_registration_api_url);
        if (is_wp_error($response)) {
            echo 'Error: ' . $response->get_error_message();
        } else {
            $api_response = json_decode(wp_remote_retrieve_body($response), true);
            if ($api_response['status']) {
                if (isset($api_response['data']['errors']) && in_array("A registration already exists with this email address.", $api_response['data']['errors'])) {
                    echo '<div id="alert">
                            <div>
                                <h4>Atenție!</h4>
                                <p>Există deja o persoană înregistrată la eveniment cu adresa de e-mail folosită.</p>
                                <button onclick="closePopup()">OK, am înțeles</button>
                            </div>
                        </div>';
                } else {
                    // echo 'Form submitted successfully!<br><br>';
                    // echo 'What is being sent to the API<br><br>';
                    // echo '<pre>';
                    // print_r($response);
                    // echo '</pre>';
                    // echo 'What is the API Response<br><br>';
                    // echo '<pre>';
                    // print_r($api_response);
                    // echo '</pre>';
                    $base_url = get_bloginfo('url') . '/';
                    $redirect_url = trailingslashit($base_url) . $redirect;
                    wp_redirect($redirect_url);
                    exit;
                }
            } elseif (isset($api_response['error'])) {
                echo 'Error: ' . $api_response['error'];
            } else {
                echo 'Fail.';
                echo '<strong>Response:</strong><br>';
                var_dump($response);
            }
        }
    }

    ?>
    <script>
        jQuery(document).ready(function($) {
            if (typeof jQuery.fn.validate === 'function') {
                console.log('jQuery Validation plugin loaded.');
                
                $('#myconnector-form').validate({
                    rules: {
                        email: {
                            required: true,
                            email: true
                        },
                        mobile_phone: {
                            required: true,
                            digits: true
                        }
                        // Add more validation rules for other fields
                    },
                    messages: {
                        email: {
                            required: 'Please enter a valid email address',
                            email: 'Please enter a valid email address'
                        },
                        mobile_phone: {
                            required: 'Please enter a valid mobile phone number',
                            digits: 'Please enter digits only'
                        }
                        // Add more error messages for other fields
                    }
                });
            } else {
                console.log('jQuery Validation plugin not recognized.');
            }
        });

        jQuery(document).ready(function($) {
            var countries = {
                "RO": {name: "Romania", mask: "+40 999 999 999"},
                "GR": {name: "Greece", mask: "+30 999 999 9999"},
                "MD": {name: "Moldova", mask: "+373 9999 9999"},
                "AL": {name: "Albania", mask: "+355 999 999 999"},
                "AD": {name: "Andorra", mask: "+376 999 999"},
                "AT": {name: "Austria", mask: "+43 (999) 999 9999"},
                "BY": {name: "Belarus", mask: "+375(99)999 99 99"},
                "BE": {name: "Belgium", mask: "+32 (999) 999 999"},
                "BA": {name: "Bosnia and Herzegovina", mask: "+387 99 9999"},
                "BG": {name: "Bulgaria", mask: "+359 (999) 999 999"},
                "HR": {name: "Croatia", mask: "+385 99 999 999"},
                "CZ": {name: "Czech Republic", mask: "+420 (999) 999 999"},
                "DK": {name: "Denmark", mask: "+45 99 99 99 99"},
                "EE": {name: "Estonia", mask: "+372 9999 9999"},
                "FI": {name: "Finland", mask: "+358 (999) 999 99 99"},
                "FR": {name: "France", mask: "+33 (999) 999 999"},
                "DE": {name: "Germany", mask: "+49(9999)999 9999"},
                "HU": {name: "Hungary", mask: "+36 (999) 999 999"},
                "IS": {name: "Iceland", mask: "+354 999 9999"},
                "IE": {name: "Ireland", mask: "+353 (999) 999 999"},
                "IT": {name: "Italy", mask: "+39 (999) 9999 999"},
                "LV": {name: "Latvia", mask: "+371 99 999 999"},
                "LI": {name: "Liechtenstein", mask: "+423 (999) 999 9999"},
                "LT": {name: "Lithuania", mask: "+370 (999) 99 999"},
                "LU": {name: "Luxembourg", mask: "+352 (999) 999 999"},
                "MT": {name: "Malta", mask: "+356 9999 9999"},
                "MC": {name: "Monaco", mask: "+377 (999) 999 999"},
                "ME": {name: "Montenegro", mask: "+382 99 999 999"},
                "NL": {name: "Netherlands", mask: "+31 99 999 9999"},
                "MK": {name: "North Macedonia", mask: "+389 99 999 999"},
                "PL": {name: "Poland", mask: "+48 (999) 999 999"},
                "PT": {name: "Portugal", mask: "+351 99 999 9999"},
                "SM": {name: "San Marino", mask: "+378 9999 999999"},
                "RS": {name: "Serbia", mask: "+381 99 999 9999"},
                "SK": {name: "Slovakia", mask: "+421 (999) 999 999"},
                "SI": {name: "Slovenia", mask: "+386 99 999 999"},
                "ES": {name: "Spain", mask: "+34 (999) 999 999"},
                "SE": {name: "Sweden", mask: "+46 99 999 9999"},
                "CH": {name: "Switzerland", mask: "+41 99 999 9999"},
                "UA": {name: "Ukraine", mask: "+380 (99) 999 99 99"},
                "GB": {name: "United Kingdom", mask: "+44 99 9999 9999"},
                "VA": {name: "Vatican City", mask: "+39 6 698 99999"},
            };

            // Populate the #countrySelector dropdown with country names and masks
            var countrySelector = $('#countrySelector');
            for (var code in countries) {
                var country = countries[code];
                var option = $('<option>', {
                    value: code,
                    text: country.name,
                    'data-mask': country.mask,
                    'data-flag': myconnector_params.plugin_dir_url + 'flags/' + code.toLowerCase() + '.svg'
                });
                countrySelector.append(option);
            }

            // Apply the input mask and set the flag when the country is selected
            $('#countrySelector').change(function() {
                var mask = $(this).find(':selected').data('mask');
                $('#phone').inputmask(mask);

                // Update the displayed value after selection
                var selectedCode = $(this).val();
                var selectedFlag = $(this).find(':selected').data('flag');
                
                // Set the background image (flag) and the value (country code) for the select element
                $(this).css('background-image', 'url(' + selectedFlag + ')').next('.selected-country-display').text(selectedCode);
            });

            // Trigger change to set the initial mask and flag
            countrySelector.trigger('change');

            $("#phone").on("blur", function() {
                var phoneNumber = $(this).val();
                var countryCode = countrySelector.val();
                var isValid = isValidNumber(phoneNumber, countryCode);

                if (isValid) {
                    $("#phone-error").text("");
                } else {
                    $("#phone-error").text("Invalid phone number.");
                }
            });
        });

        function isValidNumber(phoneNumber, countryCode) {
            var parsedNumber = libphonenumber.parse(phoneNumber, countryCode);
            var valid = libphonenumber.isValidNumber(parsedNumber);
            return valid;
        }

        function closePopup() {
            document.getElementById('alert').style.display = 'none';
        }

        document.addEventListener('DOMContentLoaded', function() {
            var currentEventId = "<?php echo $event_id; ?>";
            var form = document.querySelector('#myconnector-form');
            form.addEventListener('submit', function() {
                var inputs = form.querySelectorAll('input[type="checkbox"], input[type="radio"], option');
                
                inputs.forEach(function(input) {
                    var eventValue = input.getAttribute('data-value-' + currentEventId);
                    if (eventValue) {
                        input.value = eventValue;
                    }
                });
            });
        });
        
    </script>

    <style>
        .form-subtitle {
            background-color: #e5f3ff;
        }
        #countrySelector {
            background-repeat: no-repeat;
            background-position: 0px center;
            padding-left: 40px;
            appearance: none;
        }

        #countrySelector:after {
            content: '▼';
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
        }

        #countrySelector:focus + .selected-country-display {
            display: inline-block;
        }
        #alert{
            position: fixed;
            top: 0; 
            left: 0;
            width: 100%; 
            height: 100%; 
            background-color: rgba(0,0,0,0.7); 
            z-index: 9999; 
            text-align: center; 
            padding-top: 20%;
        }
        #alert > div {
            background-color: #fff; 
            padding: 20px; 
            border-radius: 10px; 
            display: inline-block;
        }
        #alert h4 {
            color:#cc0000;
            font-size: 1.5rem;
        }
        #alert button {
            border: 1px solid #ccc;
            padding: 0.5rem 1.5rem;
            border-radius: 50px;
            color: #646464;
            font-weight: bold;
        }
        #alert button:hover {
            background:#fafbfc;
        }
        #alert p {
            max-width: 400px;
        }
    </style>

    <div class="w-full">
        <form id="myconnector-form" method="post" class="flex flex-col gap-y-4 bg-white border border-solid border-gray-200 rounded-lg px-8 pt-6 pb-8 mb-4 text-base max-w-768:p-4">
            <div class="flex flex-row items-center gap-x-4">
                <img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/personal_details.svg" class="w-10 max-w-768::w-8">
                <h3 class="form-title text-xl font-semibold max-w-768:mb-0"><?php echo $translations['text_1'];?></h3>
            </div>
            <div class="flex flex-row items-center gap-x-8 max-w-768:flex-col gap-y-2">
                <div class="flex-1 max-w-768:w-full max-w-768:mb-2 flex flex-col">
                    <label for="last_name" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_2'];?> *</label>
                    <input type="text" name="last_name" id="last_name" required class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                </div>

                <div class="flex-1 max-w-768:w-full">
                    <label for="first_name" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_3'];?> *</label>
                    <input type="text" name="first_name" id="first_name" required class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                </div>
            </div>

            <div class="flex flex-row items-center gap-x-8 max-w-768:flex-col gap-y-2">
                <div class="flex-1 max-w-768:w-full">
                    <label for="email" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_4'];?> <em class="text-red-700">*</em></label>
                    <input type="email" name="email" id="email" required class="appearance-none block w-full bg-gray-50 text-gray-700 border border-gray-200 rounded py-3 px-4 leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                </div>

                <div class="flex-1 max-w-768:w-full">
                    <label for="phone" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_5'];?> *</label>
                    <div class="flex flex-row items-center gap-x-1">
                        <select class="focus-none w-8 bg-no-repeat bg-contain bg-center-left" id="countrySelector"></select>
                        <span class="w-8 text-center text-gray-500 font-bold selected-country-display"></span>
                        <input type="tel" name="phone" id="phone" class="phone-input block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                    </div>
                    <span id="phone-error" class="text-red-500 text-xs"></span>
                </div>
            </div>

            <div class="flex flex-row items-center gap-x-8 text-base font-semibold max-w-768:flex-col gap-y-2">
                <div id="country-row" class="flex-1 max-w-768:w-full">
                    <label for="country" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_6'];?> *</label>
                    <select name="country" id="country" required class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <option value=""><?php echo $translations['form_5'];?></option>
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
                <div id="county-row" style="display: none;" class="flex-1 max-w-768:w-full">
                    <label for="county" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_7'];?></label>
                    <select name="county" id="county" class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>

                <div id="city-row" style="display: none;" class="flex-1 max-w-768:w-full">
                    <label for="city" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_8'];?></label>
                    <select name="city" id="city" class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
            </div>
            
            <div class="flex flex-row items-center gap-x-4 mt-6">
                <img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/academic_details.svg" class="w-10">
                <h3 class="form-title text-xl font-semibold max-w-768:mb-0"><?php echo $translations['text_9'];?></h3>
            </div>

            <div class="flex flex-row items-center gap-x-8 max-w-768:flex-col gap-y-2">
                <div class="flex-1 max-w-768:w-full">
                    <label for="academic_status" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_10'];?> *</label>
                    <select name="academic_status" id="academic_status" required class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                        <option value=""><?php echo $translations['form_1'];?></option>
                        <option data-value-1508="110194" data-value-1504="110266" data-value-1515="110359"><?php echo $translations['text_11'];?></option>
                        <option data-value-1508="110195" data-value-1504="110267" data-value-1515="110360"><?php echo $translations['text_12'];?></option>
                        <option data-value-1508="110196" data-value-1504="110268" data-value-1515="110361"><?php echo $translations['text_13'];?></option>
                        <option data-value-1508="110197" data-value-1504="110269" data-value-1515="110362"><?php echo $translations['text_107'];?></option>
                        <option data-value-1508="110198" data-value-1504="110270" data-value-1515="110363"><?php echo $translations['text_14'];?></option>
                        <option data-value-1508="110199" data-value-1504="110271" data-value-1515="110364"><?php echo $translations['text_15'];?></option>
                        <option data-value-1508="110201" data-value-1504="110273" data-value-1515="110366"><?php echo $translations['text_16'];?></option>
                        <option data-value-1508="110200" data-value-1504="110272" data-value-1515="110365"><?php echo $translations['text_17'];?></option>
                    </select>
                </div>
                <div id="studentGrade" class="flex-1 hidden max-w-768:w-full">
                    <label for="grade" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_18'];?> *</label>
                    <select name="grade" id="grade" required class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                        <option value=""><?php echo $translations['form_2'];?></option>
                        <option data-value-1508="110247" data-value-1504="110348" data-value-1515="110441"><?php echo $translations['text_19'];?></option>
                        <option data-value-1508="110246" data-value-1504="110347" data-value-1515="110440"><?php echo $translations['text_20'];?></option>
                        <option data-value-1508="110245" data-value-1504="110346" data-value-1515="110439"><?php echo $translations['text_21'];?></option>
                        <option data-value-1508="110244" data-value-1504="110345" data-value-1515="110438"><?php echo $translations['text_22'];?></option>
                        <option data-value-1508="110248" data-value-1504="110349" data-value-1515="110442"><?php echo $translations['text_23'];?></option>
                    </select>
                </div>
                <div id="bachelorYear" class="flex-1 hidden max-w-768:w-full">
                    <label for="bachelor_year" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_24'];?> *</label>
                    <select name="bachelor_year" id="bachelor_year" required class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                        <option value=""><?php echo $translations['form_3'];?></option>
                        <option data-value-1508="110249" data-value-1504="110351" data-value-1515="110444"><?php echo $translations['text_25'];?></option>
                        <option data-value-1508="110250" data-value-1504="110352" data-value-1515="110445"><?php echo $translations['text_26'];?></option>
                        <option data-value-1508="110251" data-value-1504="110353" data-value-1515="110446"><?php echo $translations['text_27'];?></option>
                        <option data-value-1508="110252" data-value-1504="110354" data-value-1515="110447"><?php echo $translations['text_28'];?></option>
                        <option data-value-1508="110253" data-value-1504="110355" data-value-1515="110448"><?php echo $translations['text_29'];?></option>
                        <option data-value-1508="110254" data-value-1504="110356" data-value-1515="110449"><?php echo $translations['text_30'];?></option>
                    </select>
                </div>
                <div id="masterYear" class="flex-1 hidden max-w-768:w-full">
                    <label for="master_year" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_31'];?> *</label>
                    <select name="master_year" id="master_year" required class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500">
                        <option value=""><?php echo $translations['form_4'];?></option>
                        <option data-value-1508="110152" data-value-1504="110357" data-value-1515="110450"><?php echo $translations['text_32'];?></option>
                        <option data-value-1508="110153" data-value-1504="110358" data-value-1515="110451"><?php echo $translations['text_33'];?></option>
                    </select>
                </div>
            </div>

            <div id="school-location-selector" style="display: none;" class="flex flex-row items-center gap-x-4 mt-6 max-w-768:flex-col">
                <div id="school-city-row" style="display: none;" class="flex-1 max-w-768:w-full">
                    <label for="school_city" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_105'];?></label>
                    <select name="school_city" id="school_city" class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>

                <div id="school-county-row" style="display: none;" class="flex-1 max-w-768:w-full">
                    <label for="school_county" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_106'];?></label>
                    <select name="school_county" id="school_county" class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
            </div>

            <div id="school-selector" style="display: none;" class="flex flex-row items-center gap-x-4 mt-6 max-w-768:flex-col">
                <div id="school-row" style="display: none;" class="flex-1 max-w-768:w-full">
                    <label for="school" class="block text-sm font-bold text-gray-700 uppercase mb-1"><?php echo $translations['text_104'];?></label>
                    <select name="school" id="school" class="block appearance-none w-full bg-gray-50 border border-gray-200 text-gray-700 py-3 px-4 pr-8 rounded leading-tight focus:outline-none focus:bg-white focus:border-gray-500" >
                        <!-- Options will be populated dynamically -->
                    </select>
                </div>
            </div>

            <div class="flex flex-row items-center gap-x-4 mt-6">
                <img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/user-interests.svg" class="w-10">
                <h3 class="form-title text-xl font-semibold max-w-768:mb-0"><?php echo $translations['text_101'];?></h3>
            </div>

            <div class="flex flex-col items-start gap-y-2 mb-6">
                <h4 class="form-subtitle p-4 inline-flex rounded"><?php echo $translations['text_34'];?></h4>
                <div class="checkbox-group grid grid-cols-4 gap-4 w-full max-w-768:grid-cols-2 leading-5 items-center">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110156" data-value-1504="110274" data-value-1515="110367"><?php echo $translations['text_35'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110157" data-value-1504="110275" data-value-1515="110368"><?php echo $translations['text_36'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110158" data-value-1504="110276" data-value-1515="110369"><?php echo $translations['text_37'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110159" data-value-1504="110277" data-value-1515="110370"><?php echo $translations['text_38'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110160" data-value-1504="110278" data-value-1515="110371"><?php echo $translations['text_39'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110161" data-value-1504="110279" data-value-1515="110372"><?php echo $translations['text_40'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110162" data-value-1504="110280" data-value-1515="110373"><?php echo $translations['text_41'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110163" data-value-1504="110281" data-value-1515="110374"><?php echo $translations['text_42'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110164" data-value-1504="110282" data-value-1515="110375"><?php echo $translations['text_43'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110165" data-value-1504="110283" data-value-1515="110376"><?php echo $translations['text_44'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="programme_of_interest[]" data-value-1508="110166" data-value-1504="110284" data-value-1515="110377"><?php echo $translations['text_45'];?></label>
                </div>
            </div>

            <div class="flex flex-col items-start gap-y-2 mb-6">
                <h4 class="form-subtitle p-4 inline-flex rounded"><?php echo $translations['text_46'];?></h4>
                <div class="checkbox-group grid grid-cols-5 max-w-768:grid-cols-2 gap-4 w-full leading-5 items-center">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="start_of_studies" data-value-1508="110169" data-value-1504="110285" data-value-1515="110378">2024</label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="start_of_studies" data-value-1508="110170" data-value-1504="110286" data-value-1515="110379">2025</label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="start_of_studies" data-value-1508="110171" data-value-1504="110287" data-value-1515="110380">2026</label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="start_of_studies" data-value-1508="110172" data-value-1504="110288" data-value-1515="110381">2027</label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="start_of_studies" data-value-1508="110173" data-value-1504="110289" data-value-1515="110382"><?php echo $translations['text_47'];?></label>
                </div>
            </div>

            <div class="flex flex-col items-start gap-y-2 mb-6">
                <h4 class="form-subtitle p-4 inline-flex rounded"><?php echo $translations['text_48'];?></h4>
                <div class="checkbox-group grid grid-cols-5 max-w-768:grid-cols-2 gap-4 items-center leading-5 w-full">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110204" data-value-1504="110291" data-value-1515="110384"><?php echo $translations['text_49'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110205" data-value-1504="110292" data-value-1515="110385"><?php echo $translations['text_50'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110207" data-value-1504="110294" data-value-1515="110387"><?php echo $translations['text_51'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110208" data-value-1504="110295" data-value-1515="110388"><?php echo $translations['text_52'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110209" data-value-1504="110296" data-value-1515="110389"><?php echo $translations['text_53'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110210" data-value-1504="110297" data-value-1515="110390"><?php echo $translations['text_54'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110211" data-value-1504="110298" data-value-1515="110391"><?php echo $translations['text_55'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110212" data-value-1504="110299" data-value-1515="110392"><?php echo $translations['text_56'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110213" data-value-1504="110300" data-value-1515="110393"><?php echo $translations['text_57'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110214" data-value-1504="110301" data-value-1515="110394"><?php echo $translations['text_58'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110215" data-value-1504="110302" data-value-1515="110395"><?php echo $translations['text_59'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110216" data-value-1504="110303" data-value-1515="110396"><?php echo $translations['text_60'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110217" data-value-1504="110304" data-value-1515="110397"><?php echo $translations['text_61'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110218" data-value-1504="110305" data-value-1515="110398"><?php echo $translations['text_62'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110220" data-value-1504="110307" data-value-1515="110400"><?php echo $translations['text_63'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110258" data-value-1504="110308" data-value-1515="110401"><?php echo $translations['text_64'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110219" data-value-1504="110306" data-value-1515="110399"><?php echo $translations['text_65'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110206" data-value-1504="110293" data-value-1515="110386"><?php echo $translations['text_66'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110203" data-value-1504="110290" data-value-1515="110383"><?php echo $translations['text_67'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="destination[]" data-value-1508="110259" data-value-1504="110309" data-value-1515="110402"><?php echo $translations['text_68'];?></label>
                </div>
            </div>

            <div class="flex flex-col items-start gap-y-2 mb-6">
                <h4 class="form-subtitle p-4 inline-flex rounded"><?php echo $translations['text_69'];?></h4>
                <div class="checkbox-group grid grid-cols-4 max-w-768:grid-cols-2 gap-4 items-center leading-5 w-full">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110221" data-value-1504="110310" data-value-1515="110403"><?php echo $translations['text_70'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110222" data-value-1504="110311" data-value-1515="110404"><?php echo $translations['text_71'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110223" data-value-1504="110312" data-value-1515="110405"><?php echo $translations['text_72'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110224" data-value-1504="110313" data-value-1515="110406"><?php echo $translations['text_73'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110225" data-value-1504="110314" data-value-1515="110407"><?php echo $translations['text_74'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110226" data-value-1504="110315" data-value-1515="110408"><?php echo $translations['text_75'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110227" data-value-1504="110316" data-value-1515="110409"><?php echo $translations['text_76'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110228" data-value-1504="110317" data-value-1515="110410"><?php echo $translations['text_77'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110229" data-value-1504="110318" data-value-1515="110411"><?php echo $translations['text_78'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110230" data-value-1504="110319" data-value-1515="110412"><?php echo $translations['text_79'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110231" data-value-1504="110320" data-value-1515="110413"><?php echo $translations['text_80'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="area_of_interest[]" data-value-1508="110232" data-value-1504="110321" data-value-1515="110414"><?php echo $translations['text_81'];?></label>
                </div>
            </div>

            <div class="flex flex-col items-start gap-y-2 mb-6">
                <h4 class="form-subtitle p-4 inline-flex rounded"><?php echo $translations['text_82'];?></h4>
                <div class="checkbox-group grid grid-cols-4 max-w-768:grid-cols-2 gap-4 items-center leading-5 w-full">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110233" data-value-1504="110322" data-value-1515="110415"><?php echo $translations['text_83'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110234" data-value-1504="110323" data-value-1515="110416"><?php echo $translations['text_84'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110235" data-value-1504="110324" data-value-1515="110417"><?php echo $translations['text_85'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110236" data-value-1504="110325" data-value-1515="110418"><?php echo $translations['text_86'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110237" data-value-1504="110326" data-value-1515="110419"><?php echo $translations['text_87'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110238" data-value-1504="110327" data-value-1515="110420"><?php echo $translations['text_88'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110239" data-value-1504="110328" data-value-1515="110421"><?php echo $translations['text_89'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110240" data-value-1504="110329" data-value-1515="110422"><?php echo $translations['text_90'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110241" data-value-1504="110330" data-value-1515="110423"><?php echo $translations['text_91'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110242" data-value-1504="110331" data-value-1515="110424"><?php echo $translations['text_92'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="checkbox" name="source[]" data-value-1508="110243" data-value-1504="110332" data-value-1515="110425"><?php echo $translations['text_93'];?></label>
                </div>
            </div>

            <div class="flex flex-row items-center gap-x-8 max-w-768:flex-col gap-y-4">

                <div class="flex-1 max-w-768:w-full flex flex-row items-center gap-x-4 p-4 rounded leading-5" style="background-color: #e5f0f4;">
                    <div class="bg-white rounded p-2 w-20"><img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/subscribe-icon.svg" class=""></div>
                    <div class="flex flex-row items-center gap-x-2">
                        <input type="checkbox" name="newsletter_subscribe" id="newsletter_subscribe">
                        <label for="newsletter_subscribe"><?php echo $translations['text_94'];?></label>
                    </div>
                </div>

                <div class="flex-1 max-w-768:w-full flex flex-row items-center gap-x-4 p-4 rounded leading-5" style="background-color: #e5f3ff;">
                    <div class="bg-white rounded p-2 w-20"><img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/gdpr.svg" class=""></div>
                    <div class="block"><?php echo $translations['text_102'];?></div>
                </div>

            </div>

            <div class="flex flex-row items-center gap-x-4 mt-6">
                <img src="https://iuf.world/ro/wp-content/uploads/sites/2/2023/08/budget.svg" class="w-10">
                <h3 class="form-title text-xl font-semibold max-w-768:mb-0 !leading-6"><?php echo $translations['text_95'];?></h3>
            </div>

            <div class="flex flex-col gap-y-2">
                <div class="checkbox-group grid grid-cols-3 max-w-768:grid-cols-2 gap-4 items-center leading-5 w-full">
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="budget" data-value-1508="110261" data-value-1504="110333" data-value-1515="110426"><?php echo $translations['text_96'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="budget" data-value-1508="110262" data-value-1504="110334" data-value-1515="110427"><?php echo $translations['text_97'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="budget" data-value-1508="110263" data-value-1504="110335" data-value-1515="110428"><?php echo $translations['text_98'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="budget" data-value-1508="110264" data-value-1504="110336" data-value-1515="110429"><?php echo $translations['text_99'];?></label>
                    <label class="flex flex-row gap-x-2 font-semibold"><input type="radio" name="budget" data-value-1508="110265" data-value-1504="110337" data-value-1515="110430"><?php echo $translations['text_100'];?></label>
                </div>
            </div>

            <div class="flex flex-col">
                <input type="hidden" name="utm_source" value="<?php echo isset($_GET['utm_source']) ? sanitize_text_field($_GET['utm_source']) : ''; ?>">
                <input type="hidden" name="utm_campaign" value="<?php echo isset($_GET['utm_campaign']) ? sanitize_text_field($_GET['utm_campaign']) : ''; ?>">
                <input type="hidden" name="utm_medium" value="<?php echo isset($_GET['utm_medium']) ? sanitize_text_field($_GET['utm_medium']) : ''; ?>">
                <input type="hidden" name="utm_content" value="<?php echo isset($_GET['utm_content']) ? sanitize_text_field($_GET['utm_content']) : ''; ?>">
            </div>

            <div class="flex flex-row max-w-768:flex-col gap-y-4 items-center gap-x-8 justify-between">
                <button type="submit" class="text-white justify-center hover:bg-blue-600 font-bold py-2 px-4 rounded inline-flex items-center hover:scale-105" style="background-color: #0088ff;max-width:300px">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" class="fill-current w-4 h-4 mr-2">
                        <path d="M3.478 2.405a.75.75 0 00-.926.94l2.432 7.905H13.5a.75.75 0 010 1.5H4.984l-2.432 7.905a.75.75 0 00.926.94 60.519 60.519 0 0018.445-8.986.75.75 0 000-1.218A60.517 60.517 0 003.478 2.405z" />
                    </svg>

                    <span><?php echo $translations['form_6'];?></span>
                </button>

                <div class="text-sm">
                    <?php echo $translations['text_103'];?>
                </div>
            </div>

        </form>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('myconnector_form', 'myconnector_form_shortcode');


function myconnector_api_login($email, $password, $device) {

    $default_data = array(
        "email" => "educativa@myconnector.ro",
        "password" => "myCeducativa2018",
        "id_timezone" => "3",
        "id_organizer" => "0",
        "id_lang" => "2",
        "device" => "admin"
    );

    if ($device === "client") {
        $data = array(
            "email" => $email,
            "password" => $password,
            "device" => $device,
            "id_timezone" => "3",
            "id_organizer" => "0",
            "id_lang" => "2",
        );
    } else {
        $data = $default_data;
    }
    
    $ch = curl_init();
    $url = "https://apiv3.myconnector.ro/v1/auth/login";
    $headers = array("Accept: application/json");

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $response = curl_exec($ch);
    if(curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch);
    }
    curl_close($ch);

    // echo '<pre>';
    // print_r($response);
    // echo '</pre>';

    $responseData = json_decode($response, true);

    // Debug
    // echo '<pre>API Response for Login Procedure: ';
    // print_r($responseData);
    // echo '</pre>';

    // Check if the token has expired
    $currentDate = new DateTime();
    $tokenExpiryDate = new DateTime($responseData['data']['expires']);
    if ($currentDate >= $tokenExpiryDate) {
        echo "Debug: Token has expired.";
        return false;
    }

    $token = $responseData['data']['token'] ?? false;
    if (!$token) {
        echo "Debug: Token not found in the response.";
    }

    return $token;
}


function myconnector_prepare_form_data($language_code, $ticket_id, $event_id) {
    // Get form data
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $phone = sanitize_text_field($_POST['phone']);
    $country = sanitize_text_field($_POST['country']);
    $county = sanitize_text_field($_POST['county']);
    $city = sanitize_text_field($_POST['city']);
    $academic_status = sanitize_text_field($_POST['academic_status']);
    $grade = sanitize_text_field($_POST['grade']);
    $school = sanitize_text_field($_POST['school']);
    $bachelor_year = sanitize_text_field($_POST['bachelor_year']);
    $master_year = sanitize_text_field($_POST['master_year']);
    $programme_of_interest = isset($_POST['programme_of_interest']) ? $_POST['programme_of_interest'] : array();
    $start_of_studies = sanitize_text_field($_POST['start_of_studies']);
    $destination = isset($_POST['destination']) ? $_POST['destination'] : array();
    $area_of_interest = isset($_POST['area_of_interest']) ? $_POST['area_of_interest'] : array();
    $source = isset($_POST['source']) ? $_POST['source'] : array();
    $newsletter_subscribe = isset($_POST['newsletter_subscribe']) ? true : false;
    $budget = sanitize_text_field($_POST['budget']);

    $utm_source = isset($_POST['utm_source']) ? sanitize_text_field($_POST['utm_source']) : '';
    $utm_campaign = isset($_POST['utm_campaign']) ? sanitize_text_field($_POST['utm_campaign']) : '';
    $utm_medium = isset($_POST['utm_medium']) ? sanitize_text_field($_POST['utm_medium']) : '';
    $utm_content = isset($_POST['utm_content']) ? sanitize_text_field($_POST['utm_content']) : '';

    if($language_code == 'ro') {
        if($event_id == 1508) {
            $api_data = array(
                'firstname' => $first_name,
                'lastname' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'custom[4291]' => $country,
                'custom[4284]' => $county,
                'custom[4285]' => $city,
                'custom[789]' => $academic_status, //Detalii Academice
                'custom[793]' => $grade, // Clasa elev
                'custom[4281]' => $school,
                'custom[795]' => $bachelor_year, //Academic year
                'custom[4277]' => $master_year,
                'custom[4280]' => $programme_of_interest, // Programe interes
                'custom[4283]' => $start_of_studies,
                'custom[790]' => $destination, // Countries of interest
                'custom[791]' => $area_of_interest, // Field of Study
                'custom[792]' => $source, // Source
                'custom[800]' => $newsletter_subscribe ? '110255' : '0', //GDPR check
                'custom[3409]' => $budget, //Budget
                'custom[4290]' => $utm_source,
                'custom[4289]' => $utm_campaign,
                'custom[4278]' => $utm_medium,
                'custom[4279]' => $utm_content,
                'id_ticket' => $ticket_id,
                'settings[activate_user_emails]' => '1',
                'settings[activate_ticket_restrictions]' => '1',

            );
            $excluded_keys = array('custom[4291]', 'custom[4284]', 'custom[4285]', 'custom[4290]', 'custom[4289]', 'custom[4278]', 'custom[4279]','custom[4281]');
        } elseif($event_id == 1504) {
            $api_data = array(
                'firstname' => $first_name,
                'lastname' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'custom[4298]' => $country,
                'custom[4296]' => $county,
                'custom[4297]' => $city,
                'custom[789]' => $academic_status, //Detalii Academice
                'custom[793]' => $grade, // Clasa elev
                'custom[4299]' => $school,
                'custom[795]' => $bachelor_year, //Academic year
                'custom[4277]' => $master_year,
                'custom[4280]' => $programme_of_interest, // Programe interes
                'custom[4283]' => $start_of_studies,
                'custom[790]' => $destination, // Countries of interest
                'custom[791]' => $area_of_interest, // Field of Study
                'custom[792]' => $source, // Source
                'custom[800]' => $newsletter_subscribe ? '110255' : '0', //GDPR check
                'custom[3409]' => $budget, //Budget
                'custom[4293]' => $utm_source,
                'custom[4292]' => $utm_campaign,
                'custom[4294]' => $utm_medium,
                'custom[4295]' => $utm_content,
                'id_ticket' => $ticket_id,
                'settings[activate_user_emails]' => '1',
                'settings[activate_ticket_restrictions]' => '1',
            );
            $excluded_keys = array('custom[4298]', 'custom[4296]', 'custom[4297]', 'custom[4293]', 'custom[4292]', 'custom[4294]', 'custom[4295]','custom[4299]');
        } elseif($event_id == 1515) {
            $api_data = array(
                'firstname' => $first_name,
                'lastname' => $last_name,
                'email' => $email,
                'phone' => $phone,
                'custom[4305]' => $country,
                'custom[4303]' => $county,
                'custom[4304]' => $city,
                'custom[789]' => $academic_status, //Detalii Academice
                'custom[793]' => $grade, // Clasa elev
                'custom[4306]' => $school,
                'custom[795]' => $bachelor_year, //Academic year
                'custom[4277]' => $master_year,
                'custom[4280]' => $programme_of_interest, // Programe interes
                'custom[4283]' => $start_of_studies,
                'custom[790]' => $destination, // Countries of interest
                'custom[791]' => $area_of_interest, // Field of Study
                'custom[792]' => $source, // Source
                'custom[800]' => $newsletter_subscribe ? '110431' : '0', //GDPR check
                'custom[3409]' => $budget, //Budget
                'custom[4301]' => $utm_source,
                'custom[4300]' => $utm_campaign,
                'custom[4302]' => $utm_medium,
                'custom[4307]' => $utm_content,
                'id_ticket' => $ticket_id,
                'settings[activate_user_emails]' => '1',
                'settings[activate_ticket_restrictions]' => '1',
            );
            $excluded_keys = array('custom[4291]', 'custom[4284]', 'custom[4285]', 'custom[4301]', 'custom[4300]', 'custom[4302]', 'custom[4307]', 'custom[4306]');
        }

        // Handle custom key format for 'custom[]'
        foreach ($api_data as $key => $value) {
            if (strpos($key, 'custom[') !== false && !in_array($key, $excluded_keys)) {
                // If the value is an array (for multi-checkbox fields)
                if (is_array($value)) {
                    foreach ($value as $subvalue) {
                        $new_key = $key . '[' . $subvalue . ']';
                        $api_data[$new_key] = $subvalue;
                    }
                    unset($api_data[$key]); // Remove the original key after processing
                } else {
                    $new_key = $key . '[' . $value . ']';
                    $api_data[$new_key] = $value;
                    unset($api_data[$key]);
                }
            }
        }

        // Remove empty fields from $api_data
        $api_data = array_filter($api_data, function($value) {
            return !empty($value) || $value === 0 || $value === '0';
        });
    } elseif($language_code == 'el') {

    }  
    // echo urldecode(http_build_query($api_data));
    // echo '<pre>Data sent to API<br><br>';
    // print_r($api_data);
    // echo '</pre>';

    return $api_data;
}


function myconnector_submit_form_data($token, $api_data, $manual_registration_api_url) {
    $headers = array(
        'Authorization' => 'Bearer ' . $token,
        'Content-Type'  => 'application/x-www-form-urlencoded',
    );

    return wp_remote_post($manual_registration_api_url, array(
        'headers' => $headers,
        'body'    => http_build_query($api_data),
    ));
}


function myconnector_expozanti_shortcode($atts) {
    $email = "educativa@myconnector.ro";
    $password = "myCeducativa2018";
    $device = "admin";
    // Retrieve the API token
    $api_token = myconnector_api_login($email, $password, $device);
    
    if (!$api_token) {
        return 'Error retrieving API token';
    }

    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'event' => '',
    ), $atts);

    // Get the event ID from shortcode attributes
    $event_id = isset($atts['event']) ? $atts['event'] : '';
    
    // Check if event ID is provided
    if (empty($event_id)) {
        return 'Event ID is missing';
    }

    // API URL
    $api_url = "https://apiv3.myconnector.ro/v1/events/{$event_id}/partners/roster/?page=1&per_page=100";
    
    // API Headers with Bearer token
    $headers = array(
        'Authorization' => "Bearer $api_token",
    );
    
    error_log("API URL: $api_url");
    error_log("API Headers: " . print_r($headers, true));

    // Make API request
    $response = wp_remote_get($api_url, array('headers' => $headers));
    
    error_log("API Response: " . print_r($response, true));

    // Check for error in API request
    if (is_wp_error($response)) {
        return 'Error connecting to API';
    }

    // Get response body
    $body = wp_remote_retrieve_body($response);

    // Decode JSON response
    $data = json_decode($body, true);

    // Process and generate content based on API response
    if (isset($data['data']['roster']) && is_array($data['data']['roster'])) {
        $output = '';

        $output .='<style>.partner:before {content: "";
            transform: skewX(-12deg);
            -webkit-transform: skewX(-12deg);
            position: absolute;
            left: -5%;
            top: -7px;
            width: 110%;
            height: calc(100% + 14px);
            border-radius: 8px;
            background: #fff;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);}
            #partners-list{
                max-width:790px;margin:0 auto;
            }
            .partner-website{
                color:#125978;                
            }
            .partner-website:hover{
                color:#0088FF;
            }
            .partner:hover:before {
                content: "";
                left: -2.5%;
                top: -7px;
                width: 105%;
                height: calc(100% + 14px);
                background: #e5f3ff;
            }
            @media screen and (max-width:768px){
                .partner{
                    padding: 1em 0.5rem 1.5rem;
                    border-bottom: 1px solid #ddd;
                }
                .partner:before {
                    display:none;
                }
                .partner .info{
                    max-width:60%;
                }
                .partner-row{
                    align-items: start;
                }
            }</style>';

        usort($data['data']['roster'], function($a, $b) {
            return strcmp($a['type'], $b['type']);
        });

        $output .= '<div class="flex justify-center flex-row items-center gap-x-4 mb-8">';

        // Dropdown filter for countries
        $output .= "<label for='country-filter' class='font-semibold text-sm'>Selectează țara:</label>
                    <select id='country-filter'>
                        <option value=''>Toate țările</option>";

        // Create an array to keep track of unique country names
        $countryNames = array();

        foreach ($data['data']['roster'] as $partner) {
            // Extract partner information and generate content
            $partnerCountryId = $partner['id_country'];
            $partnerCountry = $partner['type'];

            // Add unique country names to the array
            if (!in_array($partnerCountry, $countryNames)) {
                $countryNames[] = $partnerCountry;

                // Add option to the dropdown filter
                $output .= "<option value='$partnerCountry'>$partnerCountry</option>";
            }
        }

        sort($countryNames);

        $output .= "</select></div>";

        $countryCodeMapping = array(
            'Austria' => 'at',
            'Belgium' => 'be',
            'Netherlands' => 'nl',
            'Romania' => 'ro',
            'Greece' => 'gr',
            'France' => 'fr',
            'Germany' => 'de',
            'Finland' => 'fi',
            'Switzerland' => 'ch',
            'Italy' => 'it',
            'Ireland' => 'ie',
            'Denmark' => 'dk',
            'United Kingdom' => 'uk',
            // Add more countries and codes here
        );


        $output .= "<div class='flex flex-col gap-y-12' id='partners-list'>";
        
        foreach ($data['data']['roster'] as $partner) {
            // Extract partner information and generate content
            $partnerId = $partner['id'];
            $partnerCountryId = $partner['id_country'];
            $partnerName = $partner['title'];
            $partnerCountry = $partner['type'];
            $partnerImage = $partner['image'];
            $partnerWebsite = $partner['website'];
            $partnerCity = $partner['city'];
            $partnerDescription = $partner['description'];
            $partnerContent = $partner['content'];
            $partnerCountryCode = isset($countryCodeMapping[$partnerCountry]) ? $countryCodeMapping[$partnerCountry] : '';

            // Generate content for each partner
            $output .= "<div class='partner flex flex-col country-$partnerCountry group hover:scale-105 relative p-6 max-w-768:px-2 rounded-md' data-country='$partnerCountry'>
                            <div class='partner-row flex flex-row items-center gap-x-4 justify-between relative max-w-768:items-start'>
                                <div class='flex flex-col gap-y-1 info'>
                                    <h4 class='font-bold text-xl leading-6'>$partnerName</h4>
                                    <div class='flex flex-row items-center gap-x-4'>
                                        <span class='flex flex-row items-center gap-x-2'>
                                            <span class='flex flex-row items-center gap-x-2 flag-icon flag-icon-$partnerCountryCode'></span>
                                            $partnerCountry
                                        </span>";
            if (!empty($partnerWebsite)) {
                $output .= "<a href='https://$partnerWebsite' target='_blank' class='partner-website flex flex-row items-center gap-x-2 text-sm text-dark-blue font-semibold'>
                                <svg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='1.5' stroke='currentColor' class='w-6 h-6'>
                                    <path stroke-linecap='round' stroke-linejoin='round' d='M15.042 21.672L13.684 16.6m0 0l-2.51 2.225.569-9.47 5.227 7.917-3.286-.672zm-7.518-.267A8.25 8.25 0 1120.25 10.5M8.288 14.212A5.25 5.25 0 1117.25 10.5' />
                                </svg>                                      
                                $partnerWebsite
                            </a>";
            }
            $output .= "</div>
                                </div>
                                <div class='min-w-16 logo'>
                                    <img src='$partnerImage' class='bg-white p-2 rounded h-auto' style='max-width:110px;max-height:6rem;height:auto'>
                                </div>
                            </div>
                            <div class='flex flex-col gap-y-1 mt-2 leading-6 relative'>
                                $partnerDescription
                            </div>
                        </div>";
        }

        $output .= '</div>';
        // JavaScript code for filtering (wrapped in a DOM ready function)
        $output .= "<script>
                        document.addEventListener('DOMContentLoaded', function () {
                            const countryFilter = document.getElementById('country-filter');
                            const partnersList = document.getElementById('partners-list');

                            if (countryFilter && partnersList) {
                                countryFilter.addEventListener('change', function () {
                                    const selectedCountry = countryFilter.value;

                                    const partnerContainers = partnersList.getElementsByClassName('partner');

                                    for (const container of partnerContainers) {
                                        const partnerCountry = container.getAttribute('data-country');

                                        if (selectedCountry === '' || partnerCountry === selectedCountry) {
                                            container.style.display = 'block';
                                        } else {
                                            container.style.display = 'none';
                                        }
                                    }
                                });
                            }
                        });
                    </script>";
    } else {
        $output = 'No partner data available';
    }

    return $output;
}
add_shortcode('myconnector_expozanti', 'myconnector_expozanti_shortcode');


// #####################
// Agenda shortcode
// #####################
function myconnector_agenda_shortcode($atts){
    // Extract shortcode attributes
    $atts = shortcode_atts(array(
        'event' => '',
    ), $atts);

    // Get the event ID from shortcode attributes
    $event_id = isset($atts['event']) ? $atts['event'] : '';

    // Check if event ID is provided
    if (empty($event_id)) {
        return 'Event ID is missing';
    }

    $api_token = '';

    // Check if the form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Capture the form values
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        $device = 'client';

        // Validate and handle the form values
        if (empty($email) || empty($password)) {
            return 'Email and password are required';
        }

        // Retrieve the API token
        $api_token = myconnector_api_login($email, $password, $device);

        if (!$api_token) {
            return 'Error retrieving API token';
        }
    }

    // API URL
    $api_url = "https://apiv3.myconnector.ro/v1/events/{$event_id}/agenda?filters[agenda_day]=all";

    // API Headers with Bearer token
    $headers = array(
        'Authorization' => "Bearer $api_token",
    );

    error_log("API URL: $api_url");
    error_log("API Headers: " . print_r($headers, true));

    // Make API request
    $response = wp_remote_get($api_url, array('headers' => $headers));

    error_log("API Response: " . print_r($response, true));

    // Check for error in API request
    if (is_wp_error($response)) {
        return 'Error connecting to API';
    }

    // Get response body
    $body = wp_remote_retrieve_body($response);

    // Decode JSON response
    $data = json_decode($body, true);

    // Initialize arrays to store session data for each track
    $inspirationSessions = array();
    $educationSessions = array();

    // Find the "Inspiration" and "Education" tracks within the "data" section
    foreach ($data['data'] as $track) {
        $trackTitle = $track['title'];

        if (isset($track['sessions']) && is_array($track['sessions'])) {
            // Loop through sessions within the current track
            foreach ($track['sessions'] as $session) {
                $sessionId = $session['id'];
                $sessionTitle = $session['title'];
                $sessionContent = $session['content'];
                $sessionStart = $session['start'];
                $sessionEnd = $session['end'];
                $sessionType = $session['type'];

                // Separate date and time for session start and end
                $sessionStartDate = date('Y-m-d', strtotime($sessionStart));
                $sessionStartTime = date('H:i:s', strtotime($sessionStart));
                $sessionEndDate = date('Y-m-d', strtotime($sessionEnd));
                $sessionEndTime = date('H:i:s', strtotime($sessionEnd));

                // Initialize an array to store session speakers
                $sessionSpeakers = array();

                // Loop through speakers within the session
                if (isset($session['speakers']) && is_array($session['speakers'])) {
                    foreach ($session['speakers'] as $speaker) {
                        $speakerFirstName = $speaker['firstname'];
                        $speakerLastName = $speaker['lastname'];
                        $speakerJobTitle = $speaker['job_title'];
                        $speakerPhoto = $speaker['image'];

                        // Store speaker data in the session's speaker array
                        $sessionSpeakers[] = array(
                            'first_name' => $speakerFirstName,
                            'last_name' => $speakerLastName,
                            'job_title' => $speakerJobTitle,
                            'photo' => $speakerPhoto,
                        );
                    }
                }

                // Determine the appropriate track to store the session
                if ($trackTitle === 'Inspiration') {
                    $inspirationSessions[] = array(
                        'id' => $sessionId,
                        'title' => $sessionTitle,
                        'content' => $sessionContent,
                        'start_date' => $sessionStartDate,
                        'start_time' => $sessionStartTime,
                        'end_date' => $sessionEndDate,
                        'end_time' => $sessionEndTime,
                        'type' => $sessionType,
                        'speakers' => $sessionSpeakers,
                    );
                } elseif ($trackTitle === 'Education') {
                    $educationSessions[] = array(
                        'id' => $sessionId,
                        'title' => $sessionTitle,
                        'content' => $sessionContent,
                        'start_date' => $sessionStartDate,
                        'start_time' => $sessionStartTime,
                        'end_date' => $sessionEndDate,
                        'end_time' => $sessionEndTime,
                        'type' => $sessionType,
                        'speakers' => $sessionSpeakers,
                    );
                }
            }
        }
    }

    // Sort sessions by date and time for each track
    usort($inspirationSessions, function ($a, $b) {
        // First, compare by start date
        $dateComparison = strcmp($a['start_date'], $b['start_date']);
        if ($dateComparison !== 0) {
            return $dateComparison;
        }

        // If start dates are the same, compare by start time
        return strcmp($a['start_time'], $b['start_time']);
    });

    usort($educationSessions, function ($a, $b) {
        // First, compare by start date
        $dateComparison = strcmp($a['start_date'], $b['start_date']);
        if ($dateComparison !== 0) {
            return $dateComparison;
        }

        // If start dates are the same, compare by start time
        return strcmp($a['start_time'], $b['start_time']);
    });

    // Generate the HTML output for the tabs and sessions
    $output = '<div class="tab flex flex-row items-center justify-center gap-x-4 mb-6">
                    <button class="tablinks py-2 px-6 rounded border border-solid border-blue bg-blue text-white hover:bg-blue hover:text-white" onclick="openTab(event, \'Inspiration\')">Inspiration</button>
                    <button class="tablinks py-2 px-6 rounded border border-solid border-blue hover:bg-blue hover:text-white" onclick="openTab(event, \'Education\')">Education</button>
                </div>';

    // Add a separate div for each tab
    $output .= '<div id="Inspiration" class="tabcontent mx-auto" style="max-width:640px">';
    foreach ($inspirationSessions as $session) {
        $output .= renderSession($session);
    }
    $output .= '</div>';

    $output .= '<div id="Education" class="tabcontent mx-auto" style="display:none;max-width:640px">';
    foreach ($educationSessions as $session) {
        $output .= renderSession($session);
    }
    $output .= '</div>';

    // Tab functionality
    $output .= "<style>
                    .tabcontent p {
                        margin:0;
                    }
                    @media screen and (max-width:768px){
                        .session-description {
                            padding:1rem 0 1.5rem;
                        }
                        .session-meta {
                            row-gap:0.5rem;
                            column-gap:0.5rem;
                        }
                        .speakers {
                            grid-template-columns: repeat(1,minmax(0,1fr));
                            padding: 0;
                        }
                        .speakers > li .text-sm {
                            font-size: 1rem;
                            color: #757575;
                        }
                    }
                </style>";

    $output .= '<div id="loginModal" class="modal fixed inset-0 w-full h-full flex items-center justify-center hidden" style="background:rgba(0,0,0,0.25); max-width:100%;">
                    <div class="modal-dialog bg-white rounded-lg shadow-lg w-1/2">
                        <div class="modal-content rounded-lg">
                            <div class="modal-header bg-blue-600 text-white py-4 text-center px-6">
                                <h5 class="modal-title text-lg font-semibold">Book your seat at <span class="text-base normal-case leading-5 block">' . $session["title"] . '</span></h5>
                            </div>
                            <div class="modal-body p-4">
                                <form id="bookingForm">
                                    <div class="mb-4">
                                        <label for="email" class="block text-gray-700 text-sm font-bold mb-2">E-mail</label>
                                        <input type="email" id="email" name="email" class="w-full border rounded-lg py-2 px-3 focus:outline-none focus:border-blue-400" placeholder="Adresa ta de e-mail">
                                    </div>
                                    <div class="mb-4">
                                        <label for="password" class="block text-gray-700 text-sm font-bold mb-2">Cod acces</label>
                                        <input type="password" id="password" name="password" class="w-full border rounded-lg py-2 px-3 focus:outline-none focus:border-blue-400" placeholder="Introdu codul primit pe email">
                                    </div>
                                    <input type="hidden" id="device" name="device" value="client">
                                </form>
                            </div>
                            <div class="modal-footer bg-gray-100 p-4 text-center flex flex-row justify-between">
                                <button id="closeModal" class="text-sm text-gray-700 hover:text-gray-600 focus:outline-none">Anuleaza rezervarea</button>
                                <button id="submitForm" class="bg-blue-600 hover:bg-blue-700 text-white py-2 px-4 rounded-full focus:outline-none">Trimite rezervarea</button>
                            </div>
                        </div>
                    </div>
                </div>';

    $output .= "<script>
        function openTab(evt, tabName) {
            var i, tabcontent, tablinks;
            tabcontent = document.getElementsByClassName('tabcontent');
            for (i = 0; i < tabcontent.length; i++) {
                tabcontent[i].style.display = 'none';
            }
            tablinks = document.getElementsByClassName('tablinks');
            for (i = 0; i < tablinks.length; i++) {
                tablinks[i].className = tablinks[i].className.replace(' bg-blue text-white', '');
            }
            document.getElementById(tabName).style.display = 'block';
            evt.currentTarget.className += ' bg-blue text-white';
        }
        // Open the 'Inspiration' tab by default
        document.getElementById('Inspiration').style.display = 'block';

        jQuery(document).ready(function ($) {
            console.log('jQuery is working.');
            
            // Open modal when 'Book a Session' link is clicked
            $('.openModalLink').click(function (e) {
                e.preventDefault();
                const sessionId = $(this).data('session-id');
                console.log('Open Modal Clicked for Session ID: ' + sessionId);
                $('#loginModal').removeClass('hidden');
            });
    
            // Close modal when 'Close' button is clicked
            $('#closeModal').click(function () {
                $('#loginModal').addClass('hidden');
            });
    
            // Submit form and pass values to your myconnector_api_login() function
            $('#submitForm').click(function (e) {
                e.preventDefault();
    
                // Retrieve form values
                const email = $('#email').val();
                const password = $('#password').val();
                const device = $('#device').val();

                $('#loginModal').addClass('hidden');
    
                // Call myconnector_api_login() function with the values
                const api_token = myconnector_api_login(email, password, device);
    
                // Make the POST request to book a session
                const sessionId = $(this).data('session-id'); // Get the session ID from the button data attribute
                if (api_token) {
                    if (sessionId) {
                        const postUrl = `https://apiv3.myconnector.ro/v1/event/${event_id}/agenda/sessions/${sessionId}/book`;

                        $.ajax({
                        type: 'POST',
                        url: postUrl,
                        headers: {
                            'Authorization': `Bearer ${api_token}`,
                        },
                        success: function (response) {
                            // Handle the success response here
                        },
                        error: function (error) {
                            // Handle any errors here
                        }
                        });
                    };
                }
            });
        });
    </script>";

    return $output;
}

function renderSession($session) {
    $sessionTitle = $session['title'];
    $sessionContent = html_entity_decode($session['content'], ENT_QUOTES, 'UTF-8');
    $sessionStartDateDay = date('j', strtotime($session['start_date']));
    $sessionStartDateMonth = date('M', strtotime($session['start_date']));
    $sessionStartTime = date('H:i', strtotime($session['start_time']));
    $sessionEndDate = date('j M', strtotime($session['end_date']));
    $sessionEndTime = date('H:i', strtotime($session['end_time']));
    $sessionType = $session['type'];

    // Mapping of English day names to Romanian day names
    $romanianDayNames = [
        'Monday'    => 'Luni',
        'Tuesday'   => 'Marți',
        'Wednesday' => 'Miercuri',
        'Thursday'  => 'Joi',
        'Friday'    => 'Vineri',
        'Saturday'  => 'Sâmbătă',
        'Sunday'    => 'Duminică',
    ];

    // Identify the day of the week in English
    $dayOfWeekEnglish = date('l', strtotime($session['start_date']));

    // Translate the English day name to Romanian
    $dayOfWeekRomanian = $romanianDayNames[$dayOfWeekEnglish];

    // Output session details
    $output = "<div class='session relative p-6 border border-solid border-gray-200 rounded hover:shadow-md hover:scale-90 transition-transform mb-6 max-w-768:p-4'>
                    <div class='flex flex-row items-center gap-x-4'>
                        <div class='rounded-full w-16 h-16 p-4 text-center flex items-center justify-center max-w-768:hidden' style='background-color:#e5f3ff'>
                            <div class='session-meta text-xl text-blue font-bold uppercase leading-4'>$sessionStartDateDay <span class='text-sm'>$sessionStartDateMonth</span></div>
                        </div>
                        <div class='flex flex-col gap-y-1'>
                            <div class='hidden max-w-768:flex flex-row items-center gap-x-4'>
                                <div class='rounded-full w-16 h-16 p-4 text-center flex items-center justify-center' style='background-color:#e5f3ff'>
                                    <div class='session-meta text-xl text-blue font-bold uppercase leading-4'>$sessionStartDateDay <span class='text-sm'>$sessionStartDateMonth</span></div>
                                </div>
                                <h3 class='session-title font-bold !mb-0' style='font-size:1.35rem;line-height:1.5rem'>$sessionTitle</h3>
                            </div>
                            <h3 class='session-title font-bold mr-12 !mb-0 max-w-768:hidden' style='font-size:1.35rem;line-height:1.5rem'>$sessionTitle</h3>
                            <div class='session-meta flex flex-row item-center gap-x-3 max-w-768:flex-wrap max-w-768:gap-2'>
                                <div class='flex flex-row items-center gap-x-2 bg-gray-200 py-1 px-2 rounded text-gray-500'>
                                    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor' class='w-5 h-5'>
                                        <path fill-rule='evenodd' d='M6.75 2.25A.75.75 0 017.5 3v1.5h9V3A.75.75 0 0118 3v1.5h.75a3 3 0 013 3v11.25a3 3 0 01-3 3H5.25a3 3 0 01-3-3V7.5a3 3 0 013-3H6V3a.75.75 0 01.75-.75zm13.5 9a1.5 1.5 0 00-1.5-1.5H5.25a1.5 1.5 0 00-1.5 1.5v7.5a1.5 1.5 0 001.5 1.5h13.5a1.5 1.5 0 001.5-1.5v-7.5z' clip-rule='evenodd' />
                                    </svg>                                    
                                    <div class='text-sm text-gray-900 font-semibold max-w-768:text-xs'>
                                        $dayOfWeekRomanian
                                    </div>
                                </div>
                                <div class='flex flex-row items-center gap-x-2 bg-gray-200 py-1 px-2 rounded text-gray-500'>
                                    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor' class='w-5 h-5'>
                                        <path fill-rule='evenodd' d='M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25zM12.75 6a.75.75 0 00-1.5 0v6c0 .414.336.75.75.75h4.5a.75.75 0 000-1.5h-3.75V6z' clip-rule='evenodd' />
                                    </svg>
                            
                                    <div class='text-sm text-gray-900 font-semibold max-w-768:text-xs'>
                                        $sessionStartTime - $sessionEndTime
                                    </div>
                                </div>
                                <div class='flex flex-row items-center gap-x-2 bg-gray-200 py-1 px-2 rounded text-gray-500'>
                                    <svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='currentColor' class='w-5 h-5'>
                                        <path fill-rule='evenodd' d='M5.25 2.25a3 3 0 00-3 3v4.318a3 3 0 00.879 2.121l9.58 9.581c.92.92 2.39 1.186 3.548.428a18.849 18.849 0 005.441-5.44c.758-1.16.492-2.629-.428-3.548l-9.58-9.581a3 3 0 00-2.122-.879H5.25zM6.375 7.5a1.125 1.125 0 100-2.25 1.125 1.125 0 000 2.25z' clip-rule='evenodd' />
                                    </svg>
                              
                            
                                    <div class='text-sm text-gray-900 font-semibold max-w-768:text-xs'>
                                        $sessionType
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class='bg-blue-600 text-white rounded py-5 px-3 text-center hover:shadow-md'>
                            <a href='#' id='openModal_" . $session["id"] . "' data-session-id='" . $session["id"] . "' class='openModalLink font-bold leading-5 block' style='color:#fff'>Book a Session</a>
                        </div>
                    </div>
                    <div class='flex flex-col gap-y-2 px-4 pt-4 pb-6 text-gray-700 session-description max-w-768:px-2'>$sessionContent</div>";

    // Output the list of speakers for the session
    if (!empty($session['speakers'])) {
        $output .= "<h3 class='uppercase font-bold px-4 text-blue' style='font-size:0.875rem'>Speakers:</h3>";
        $output .= "<ul class='speakers grid grid-cols-2 gap-4 px-4 max-w-768:grid-cols-1' style='margin-bottom:0'>";
        foreach ($session['speakers'] as $speaker) {
            $speakerFirstName = $speaker['first_name'];
            $speakerLastName = $speaker['last_name'];
            $speakerJobTitle = $speaker['job_title'];
            $speakerPhoto = $speaker['photo'];
            $output .= "<li class='flex flex-row items-center gap-x-4'>
                            <img src='$speakerPhoto' class='rounded-full w-20 hover:scale-110 relative transition-transform'>
                            <div class='flex flex-col gap-y-1'>
                                <h4 class='font-bold leading-5 !mb-0' style='margin-bottom:0.25rem;'>$speakerFirstName $speakerLastName</h4>
                                <span class='text-sm leading-4'>$speakerJobTitle</span>
                            </div>
                        </li>";
        }
        $output .= "</ul>";
    }

    $output .= "</div>";

    return $output;
}

add_shortcode('myconnector_agenda', 'myconnector_agenda_shortcode');