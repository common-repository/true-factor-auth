=== True Factor Auth ===

Tags: security, access control, authorisation, authentication, 2fa, two-factor authentication, sms, otp, google authenticator
Requires at least: 5.4
Tested up to: 5.6
Stable tag: 1.0.4
Requires PHP: 7.2
Contributors: truewp
Donate link: https://true-wp.com/donate/

== Description ==

Secure any action or page on your site with one-time password (SMS or Authenticator App), add phone number confirmation during registration, enable two-factor authorisation.

*	Protect any pages and forms with password, SMS or Authenticator App
*	Two-factor login with SMS and Authenticator App
*	Phone number confirmation during registration and on different forms (e.g profile pages)

== Screenshots ==

1. Example verification popup
2. Google Authenticator Activation popup
3. User Settings interface

== Frequently Asked Questions ==

= How do I activate two-factor login? =

1. Enable the **Two-Factor Login** module in *True Factor Auth / Modules* section in admin panel.
2. Adjust settings in *True Factor Auth / Two-Factor Login* section in admin panel.
3. Two-factor login popup will be automatically added to the default login form (/wp-login.php). If you have any custom login forms, you'll need to add corresponding selectors. See hints under form fields.

= How do I activate mobile number confirmation on registration? =

1. Enable the **Phone Number Confirmation** module in *True Factor Auth / Modules* section in admin panel.
2. Optional. If you want user phone number to be stored with certain meta key, navigate to  *True Factor Auth / SMS Settings* section in admin panel and enter desired meta key in **Phone Number Meta Key** field. By default, phone number is stored with `_tfa_tel` meta key in the usermeta table.
3. Navigate to  *True Factor Auth / Phone Number Confirmation* section in admin panel.
3. Check the **Require Phone Number Verification on Registration** checkbox
4. Phone number verification will be added automatically on the default registration form (/wp-login.php?action=register). If you have a custom registration form, add corresponding selectors in the **Registration Forms Selectors** field.

= How do I publish user settings interface? =

True Factor Auth offers three separate user settings widgets:

1. Verification methods settings. Allows user to enable/disable verification methods in their account. Add short-code `[true-factor-auth-methods]` on target page to display this widget.
2. Login verification method. Displayed by `[true-factor-login-settings]` shortcode.
3. Custom Actions settings. Allow user to choose verifications methods separately for actions defined in Access Rules. Add `[true-factor-action-settings]` shortcode on target page to display this widget.

= How do I add password/sms/authenticator protection to a custom form on my site? =

1. Open True Factor Auth / Access Rules page in the admin panel.
2. Click "Add new" button next to page title.
3. Fill required parameters for back-end. This is necessary to ensure that request is captured and filtered by True Factor on server side. Use Developer Tools in your browser to find out the request parameters of the form.
4. Fill required parameters for front-end. They are needed to display the verification popup in browser. The Trigger selector is a CSS selector of a form which needs to be protected. Make sure to provide a selector that matches only target form. Do not use vague selectors like `body form`.
5. Fill the rest of parameters according to your needs and save the rule.
6. Test your rule.

For more detailed instructions please see this [article](https://true-wp.com/how-to-protect-custom-form-with-true-factor-auth-plugin/)

= Does True Factor Auth support integration with other plugins? =

We designed our plugin in the way that, generally, it can be used with other plugins without special measures. However, since there are thousands of different plugins, we did not test for compatibility with all of them. If you faced a compatibility issue (or any other issue), please let us know and we'll do our best to fix it.

= What if I messed something with configuration or lost my phone and can not login any more? =

If you defined wrong rules and got blocked from your site, you can temporarily disable True Factor Auth security check. You will need access to wp-config.php.

1.	Connect to your server via SSH or FTP
2.	Open wp-config.php in your site root directory
3.	Add the following code after the first line:

    define('TRUE_FACTOR_DISABLE', 1);

4.	Save the file
5.	Login and fix plugin settings via admin panel
6.	Don't forget to remove previously added line from wp-config.php to re-enable True Factor

= How can I customise popup dialogs? =

First, you can customise the popup templates and button captions via admin panel.

See 2FA Verification / Settings page. The Custom Templates module must be activated.

Plugin uses [Mustache engine](https://mustache.github.io/) for templates.

Also, you can override some plugin templates by copying files under `./templates` folder to `/<your-theme-folder>/templates/true-factor-auth/` folder and editing them.

== Changelog ==

Initial release.

== Upgrade Notice ==

No notices yet.

