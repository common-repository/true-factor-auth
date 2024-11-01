<?php

/**
 * Plugin Name: True Factor Auth
 * Description: Restrict access to any content or feature on your site; Add two-factor authorisation to your login forms and other pages; Allow users to login using one-time SMS pass-code. Verification methods include SMS one-time password and Google Authenticator.
 * Version: 1.0.5
 * Requires at least: 5.4
 * Tested up to: 5.6
 *
 * Text Domain: true-factor
 * Domain Path: /languages/
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace TrueFactor;

require_once 'init.php';

register_activation_hook( TRUE_FACTOR_PLUGIN_FILE, [ Installer::class, 'install' ] );

View::loadNotices();

// Init modules

// General
Module\VerificationModule::instance();
Module\SmsModule::instance();
Module\TwoFactorLoginModule::instance();
Module\TelConfirmationModule::instance();
Module\AdminSettingsModule::instance();
Module\AccessRulesModule::instance();

// Verification
Module\NoneVerificationHandlerModule::instance();
Module\PasswordVerificationHandlerModule::instance();
Module\SmsVerificationHandlerModule::instance();
Module\GauVerificationHandlerModule::instance();

// SMS Gateways
Module\TwilioModule::instance();