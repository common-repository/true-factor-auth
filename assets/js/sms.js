/* globals tfa_main, tfa_sms, jQuery */

(function ($) {

    if (!window.replaceMobileInput) {
        window.replaceMobileInput = function (/** {HTMLInputElement} */ input) {
            var options = {};
            options.nationalMode = false;
            options.autoHideDialCode = false;
            options.autoPlaceholder = 'aggressive';
            options.formatOnDisplay = true;
            options.preferredCountries = [];

            if (tfa_main.preferred_country) {
                options.preferredCountries = [tfa_main.preferred_country];
            }

            if (tfa_main.countries) {
                options.onlyCountries = tfa_main.countries;
            }

            return intlTelInput(input, options);
        };
    }

    if (!window.tfaInitSmsConfirmationForm) {
        /**
         * Initialise the SMS OTP form.
         *
         * @param $container
         */
        window.tfaInitSmsConfirmationForm = function ($container) {
            var $form = $container.find('.tfa-confirm-sms');
            if (!$form.length) {
                return;
            }

            var wait = $form.data('timeout');

            var $telInput = $form.find('[name=tel]:visible');
            if ($telInput.length) {
                replaceMobileInput($telInput[0]);
            }

            var $codeInput = $form.find('[name=code]');
            if (!$codeInput.length) {
                throw Error('No code input.');
            }

            var $codeSendButton = $form.find('.tfa-send-sms-button');
            if (!$codeSendButton.length) {
                $codeSendButton = $('<button type="button" class="button tfa-send-sms-button"></button>');
                $codeSendButton.text(tfa_sms.t_send_sms || 'Send SMS');
                var $submit = $form.find('[type=submit]').first();
                $codeSendButton.insertBefore($submit);
            }

            var timeout = parseInt(tfa_sms.resend_timer, 10) || 120;
            var waitLeft;
            var resendTimer;

            var showTimer = function () {
                waitLeft--;
                if (waitLeft > 0) {
                    $codeSendButton.text(tfa_sms.t_resend_sms + ' (' + waitLeft + ')');
                } else {
                    $telInput.removeAttr('readonly');
                    $codeSendButton.text(tfa_sms.t_resend_sms);
                    $codeSendButton.removeProp('disabled');
                    clearInterval(resendTimer);
                }
            };

            var runTimer = function () {
                resendTimer = setInterval(showTimer, 1000);
            };

            if (wait) {
                $telInput.attr('readonly', 'readonly');
                $codeSendButton.prop('disabled', true);
                waitLeft = parseInt(wait, 10);
                showTimer();
                runTimer();
            }

            // Attach an AJAX call to send the OTP.
            $codeSendButton.on('click', function () {
                $.ajax({
                    url: tfa_main.ajax_url + '?action=tfa_send_sms',
                    method: 'POST',
                    dataType: "json",
                    data: $form.serialize(),
                    success: function (data) {
                        if (data.timeout) {
                            waitLeft = data.timeout;
                        }

                        if (data.error || data.error_message) {
                            tfaShowError(data.error || data.error_message, 'error');
                            $codeSendButton.removeProp('disabled');
                            $codeSendButton.text(tfa_sms.t_resend_sms);
                            $telInput.removeAttr('readonly');
                        }

                        if (data.message) {
                            tfaShowMessage(data.message);
                        }

                        if (data.success) {
                            waitLeft = timeout + 1;
                            showTimer();
                            runTimer();
                        }

                        $codeInput.focus();
                    },
                    fail: function () {
                        $codeSendButton.removeProp('disabled');
                        $codeSendButton.text(tfa_sms.t_resend_sms);
                        $telInput.removeAttr('readonly');
                    }
                });

                $telInput.attr('readonly', 'readonly');
                $codeSendButton.text(tfa_sms.t_sending);
                $codeSendButton.prop('disabled', true);
            });
        }
    }

    if (!window.tfaInitCallbacks) {
        window.tfaInitCallbacks = [];
    }

    window.tfaInitCallbacks.push(tfaInitSmsConfirmationForm);
})(jQuery);