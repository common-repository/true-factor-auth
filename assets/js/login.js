/* global tfa_main, tfa_login, jQuery */

"use strict";

(function ($) {
    window.tfaInitLogin2FA = function (form_selector, login_selector, password_selector) {
        var $form = $(form_selector);
        if (!$form.length) {
            return;
        }
        $form.on('submit', function (evt) {
            if ($form.data('done-2fa')) {
                return;
            }
            evt.preventDefault();
            evt.stopPropagation();
            evt.stopImmediatePropagation();

            var ts = new Date().getTime();
            if ($form.data('submitted') > ts - 300) {
                return;
            }
            $form.data('submitted', ts);

            tfaShow2FAPopup({
                    action: 'tfa_login_popup',
                    log: $form.find(login_selector).val(),
                    pwd: $form.find(password_selector).val(),
                }, function (val) {
                    tfaAddHidden($form, tfa_login.token_key, val);
                    $form.data('done-2fa', 1);
                    $form.trigger('submit');
                }, {
                    method: 'post',
                    complete: function () {
                        // Ultimate Member login from submit button gets disabled after click.
                        // We must re-enable it in case if submission didn't take place.
                        $form.find('[type=submit]').removeAttr('disabled');
                    }
                }
            );
        });
    };

    var selector_groups = tfa_login.login_2fa_form_selector.split(/\n\s*\n/);
    window.addEventListener('load', function () {
        selector_groups.forEach(function (selector_group) {
            tfaInitLogin2FA.apply(null, selector_group.split('\n'));
        });
    });
})(jQuery);
