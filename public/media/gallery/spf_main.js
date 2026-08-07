class SmartPhoneFieldFree {
    constructor(options) {
        this.options = options;
        this.init();
    }

    init() {
        this.initSmartPhoneFieldFree();
    }

    initSmartPhoneFieldFree() {
        if (typeof intlTelInput === 'undefined') {
            return;
        }
        const input = document.querySelector(this.options.inputId);

        if (!input) {
            console.warn(`Input element not found: ${this.options.inputId}`);
            return;
        }

        const iti = window.intlTelInput(input, this.configuration());

        input.addEventListener('keypress', function (e) {
            const charCode = e.which ? e.which : e.keyCode;
            if (String.fromCharCode(charCode).match(/[^0-9+]/g)) {
                e.preventDefault();
            }
        });

        this.addCountryCodeInputHandler(input, iti);

        input.addEventListener('blur', (e) => {
            this.validateNumber(input, iti);
        });

        input.addEventListener('keyup', (e) => {
            this.formatValidation(input, iti);
        });
    }

    configuration() {
        const field_id = `input_${this.options.fieldId}`;

        let config = {
            initialCountry: this.options.defaultCountry,
            formatOnDisplay: false,
            formatAsYouType: false,
            fixDropdownWidth: true,
            hiddenInput: function (telInputName) {
                return {
                    phone: field_id
                };
            },
            useFullscreenPopup: false
        };

        if (this.options.countrySearch) {
            config.countrySearch = true;
        }

        if (this.options.flag === "flagcode") {
            config.nationalMode = false;
            config.autoHideDialCode = false;
        } else if (this.options.flag === "flagdial" || this.options.flag === "flagwithcode") {
            config.nationalMode = false;
            config.separateDialCode = true;
        } else {
            config.nationalMode = true;
        }

        if (this.options.exIn === 'ex_only') {
            config.onlyCountries = this.options.countries.split(',');
        }

        if (this.options.exIn === 'pre_only') {
            config.excludeCountries = this.options.countries.split(',');
        }

        if (this.options.autoIp) {
            this.detectIPAddress(config);
        }

        if (this.options.placeholder) {
            config.autoPlaceholder = 'off';
        }

        config = gform.applyFilters('gform_spf_options_pre_init', config, this.options.formId, this.options.fieldId);

        return config;
    }

    detectIPAddress(config) {
        const api_url = "https://ipinfo.io/json";
        config.initialCountry = "auto";
        config.geoIpLookup = function (callback) {
            fetch(api_url)
                .then(r => r.json())
                .then(data => {
                    const country = (data && data.country) ? data.country.toLowerCase() : 'us';
                    callback(country);
                })
                .catch(() => callback('us'));
        };
    }

    validateNumber(input, iti) {
        const isValid = iti.isValidNumber();
        const errorMsg = input.parentNode?.parentNode?.querySelector(".error-msg");
        const validMsg = input.parentNode?.parentNode?.querySelector(".valid-msg");

        if (!errorMsg || !validMsg) {
            console.warn('Error or valid message elements not found');
            return;
        }

        if (input.value) {
            if (isValid) {
                errorMsg.classList.add('hide');
                validMsg.classList.remove('hide');
            } else {
                validMsg.classList.add('hide');
                errorMsg.classList.remove('hide');
            }
        } else {
            validMsg.classList.add('hide');
            errorMsg.classList.add('hide');
        }
    }

    formatValidation(input, iti) {
        const isValid = iti.isValidNumber();
        const errorMsg = input.parentNode?.parentNode?.querySelector(".error-msg");
        const validMsg = input.parentNode?.parentNode?.querySelector(".valid-msg");

        if (!errorMsg || !validMsg) {
            console.warn('Error or valid message elements not found');
            return;
        }

        if (input.value) {
            if (isValid) {
                errorMsg.classList.add('hide');
                validMsg.classList.remove('hide');
            } else {
                validMsg.classList.add('hide');
                errorMsg.classList.add('hide');
            }
        } else {
            validMsg.classList.add('hide');
            errorMsg.classList.add('hide');
        }
    }

    addCountryCodeInputHandler(inputElement, iti) {
        if (this.options.flag !== 'flagcode') {
            return;
        }

        const handleCountryChange = (event) => {
            const currentCountryData = iti.getSelectedCountryData();
            const currentCode = `+${currentCountryData.dialCode}`;
            this.updateCountryCodeHandler(event.currentTarget, currentCode);
        };

        inputElement.addEventListener('keydown', handleCountryChange);
        inputElement.addEventListener('input', handleCountryChange);
        inputElement.addEventListener('countrychange', handleCountryChange);
    }

    updateCountryCodeHandler(input, currentCode) {
        let value = input.value;

        if (!currentCode || currentCode === '+undefined' || ['', '+'].includes(value)) {
            return;
        }

        if (!value.startsWith(currentCode)) {
            value = value.replace(/\+/g, '');
            input.value = currentCode + value;
        }
    }
}