(function ($) {
    "use strict";

    window.tfaAdmin = {
        toggleSection: function (id, state) {
            var $section = $('.tfa-admin-section[data-section-id=' + id + ']');

            if (!$section.length) {
                return;
            }

            if (typeof state != 'boolean') {
                state = $section.is('.hidden');
            }

            $section.toggleClass('hidden', !state);
        }
    };

    var $gateway = $('.js-tfa-admin-sms-gateway');

    if ($gateway.length) {
        var options = [];
        $gateway.find('option').each(function (i, el) {
            options.push(el.value);
        });

        var select_gateway = function () {
            var selected = $gateway.val();
            options.forEach(function (id) {
                tfaAdmin.toggleSection(id, selected == id);
            });
        };

        $gateway.change(select_gateway);
        select_gateway();
    }

})(jQuery);
