<?php
/*
Plugin Name: MailPoet Jigoshop Add-on
Plugin URI: http://wordpress.org/plugins/mailpoet-jigoshop-add-on/
Description: Subscribe your customers to MailPoet newsletters
Version: 1.0.4
Author: Sebs Studio
Author URI: http://www.sebs-studio.com
Author Email: sebastien@sebs-studio.com
License:

  Copyright 2013 Sebs Studio (sebastien@sebs-studio.com)

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License, version 2, as 
  published by the Free Software Foundation.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
  
*/

if(!defined('ABSPATH')) exit; // Exit if accessed directly

// Check if Jigoshop is installed and activated first before activating this plugin.
if(in_array('jigoshop/jigoshop.php', apply_filters('active_plugins', get_option('active_plugins')))){

class MailPoet_Jigoshop_Add_on {

	/*--------------------------------------------*
	 * Constants
	 *--------------------------------------------*/
	const name = 'MailPoet Jigoshop Add-on';
	const slug = 'mailpoet_jigoshop_add_on';

	/**
	 * Constructor
	 */
	function __construct(){
		// Register an activation hook for the plugin
		register_activation_hook(__FILE__, array(&$this, 'install_mailpoet_jigoshop_add_on'));

		// Settings
		add_action('init', array(&$this, 'install_settings'));

		// Hook up to the init action
		add_action('init', array(&$this, 'init_mailpoet_jigoshop_add_on'));
	}

	/**
	 * Runs when the plugin is activated
	 */
	function install_mailpoet_jigoshop_add_on(){
		add_option('mailpoet_jigoshop_enable_checkout', 0);
		add_option('mailpoet_jigoshop_checkout_label', __('Yes, add me to your mailing list', 'mailpoet_jigoshop'));
	}

	/**
	 * Runs when the plugin is initialized
	 */
	function init_mailpoet_jigoshop_add_on(){
		// Setup localization
		load_plugin_textdomain(self::slug, false, dirname(plugin_basename(__FILE__)).'/languages');

		if(is_admin()){
			// Adds a menu item under Jigoshop.
			add_action('jigoshop_after_admin_menu', array(&$this, 'add_mailpoet_list_menu'), 10);
		}
		else{
			// Hook into checkout page.
			add_action('jigoshop_checkout_shipping', array(&$this, 'on_checkout_page'), 20);
		}
		// Hook into checkout validation.
		add_action('jigoshop_after_checkout_validation', array(&$this, 'on_process_order'));
	}

	/**
	 * Creates a new tab section in Jigoshop settings.
	 */
	public function install_settings(){
		Jigoshop_Base::get_options()->install_external_options_tab('MailPoet', $this->mailpoet_newsletter_admin_settings());
	}

	/**
	 * MailPoet settings page.
	 *
	 * Handles the display of the main jigoshop 
	 * settings page in admin.
	 *
	 * @access public
	 * @return void
	 */
	function mailpoet_newsletter_admin_settings(){
		$setting = array();

		$setting[] = array(
						'name' 			=> __('MailPoet Newsletter', 'mailpoet_jigoshop'),
						'type' 			=> 'title',
						'desc' 			=> __('Now your customers can subscribe to newsletters you have created with MailPoet. Simple setup your settings below and press "Save MailPoet Changes".', 'mailpoet_newsletters'),
						'id' 			=> 'jigoshop_mailpoet_general_options'
					);

		$setting[] = array(
						'name' 			=> __('Enable subscribe on checkout', 'mailpoet_jigoshop'),
						'desc' 			=> __('Add a subscribe checkbox to your checkout page.', 'mailpoet_jigoshop'),
						'id' 			=> 'mailpoet_jigoshop_enable_checkout',
						'type' 			=> 'checkbox',
						'default' 		=> '1',
					);

		$setting[] = array(
						'name' 			=> __('Checkbox label', 'mailpoet_jigoshop'),
						'desc' 			=> __('Enter a message to place next to the checkbox.', 'mailpoet_jigoshop'),
						'desc_tip' 		=> true,
						'id' 			=> 'mailpoet_jigoshop_checkout_label',
						'type' 			=> 'text',
						'default' 		=> __('Yes, add me to your mailing list', 'mailpoet_jigoshop'),
					);

		return apply_filters('jigoshop_mailpoet_settings', $setting);
	}

	// Adds the mailpoet menu link under Jigoshop.
	public function add_mailpoet_list_menu(){
		add_submenu_page('jigoshop', __('MailPoet Lists', 'mailpoet_jigoshop'), __('MailPoet Lists', 'mailpoet_jigoshop'), 'manage_jigoshop', 'mailpoet_lists', array(&$this, 'add_mailpoet_lists_page'));
	}

	// Displays a table listing the newsletters subscriptions.
	public function add_mailpoet_lists_page(){
		if(isset($_POST['action']) && $_POST['action'] == 'save'){
			if(isset($_POST['checkout_lists'])){
				$checkout_lists = $_POST['checkout_lists'];
				$lists = $checkout_lists;
				update_option('mailpoet_jigoshop_subscribe_too', $lists);
			}
			else{
				delete_option('mailpoet_jigoshop_subscribe_too', $lists);
			}
			$location = admin_url('admin.php?page=mailpoet_lists&status=saved');
			wp_safe_redirect($location);
			exit;
		}
		if(isset($_GET['status']) && $_GET['status'] == 'saved'){
			echo '<div id="message" class="updated"><p>'.__('Settings saved', 'mailpoet_jigoshop').'</p></div>';
		}
	?>
	<div class="wrap jigoshop">
		<div class="icon32 icon32-jigoshop-settings" id="icon-jigoshop"><br/></div>

		<h2><?php _e('MailPoet Lists', 'mailpoet_jigoshop'); ?></h2>

		<p><?php _e('Here is the list of newsletters you can assign the customer to when they subscribe. Simply tick the newsletters you want your customers to subscribe to and press "Save Changes".'); ?></p>

		<form name="mailpoet-settings" method="post" id="mailpoet-settings" action="<?php echo admin_url('admin.php?page=mailpoet_lists'); ?>" class="form-valid" autocomplete="off">

		<?php
		include_once(dirname(__FILE__).'/include/settings-newsletters.php');

		$mailpoet_list = mailpoet_lists();

		do_action('jigoshop_mailpoet_list_newsletters', $mailpoet_list);
		?>

		<p class="submit">
			<input type="submit" value="<?php _e('Save Settings', 'mailpoet_jigoshop'); ?>" class="button-primary mailpoet">
			<?php wp_nonce_field('save_jigoya_settings'); ?>
			<input type="hidden" value="save" name="action">
		</p>

		</form>

	</div>
	<?php
	}

	/**
	 * This displays a checkbox field on the checkout 
	 * page to allow the customer to subscribe to newsletters.
	 */
	function on_checkout_page(){
		// Checks if subscribe on checkout is enabled.
		$enable_checkout = get_option('mailpoet_jigoshop_enable_checkout');
		$checkout_label = get_option('mailpoet_jigoshop_checkout_label');

		if($enable_checkout == 'yes'){
			// Display the checkbox.
			echo '<p class="form-row mailpoet-on-checkout" style="clear:left;">'.
			'<input id="mailpoet-box-on-checkout" class="input-checkbox" type="checkbox" value="1" name="mailpoet_checkout_subscribe">'.
			'<label class="checkbox" for="mailpoet-subscription">'.
			htmlspecialchars(stripslashes($checkout_label)).
			'</label></p>';
		}
	}

	/**
	 * This process the customers subscription if any 
	 * to the newsletters along with their order.
	 */
	function on_process_order(){
		$mailpoet_checkout_subscribe = isset($_POST['mailpoet_checkout_subscribe']) ? 1 : 0;

		// If the check box has been ticked then the customer is added to the MailPoet lists enabled.
		if($mailpoet_checkout_subscribe == 1){
			$checkout_lists = get_option('mailpoet_jigoshop_subscribe_too');

			$user_data = array(
				'email' 	=> $_POST['billing-email'],
				'firstname' => $_POST['billing-first_name'],
				'lastname' 	=> $_POST['billing-last_name']
			);

			$data_subscriber = array(
				'user' 		=> $user_data,
				'user_list' => array('list_ids' => $checkout_lists)
			);

			$userHelper = &WYSIJA::get('user','helper');
			$userHelper->addSubscriber($data_subscriber);
		}
	} // on_process_order()

} // end class
new MailPoet_Jigoshop_Add_on();

/**
 * Gets all enabled lists in MailPoet
 */
if( ! function_exists( 'mailpoet_lists' ) ) {
	function mailpoet_lists(){
		// This will return an array of results with the name and list_id of each mailing list
		$model_list = WYSIJA::get('list','model');
		$mailpoet_lists = $model_list->get(array('name','list_id'), array('is_enabled' => 1));

		return $mailpoet_lists;
	}
}

}
else{
	add_action('admin_notices', 'mailpoet_jigoshop_add_on_active_error_notice');
	// Displays an error message if Jigoshop is not installed or activated.
	function mailpoet_jigoshop_add_on_active_error_notice(){
		global $current_screen;

		if($current_screen->parent_base == 'plugins'){
			echo '<div class="error"><p>'.sprintf(__('MailPoet Jigoshop Add-On requires Jigoshop. Please install and activate <a href="%s">Jigoshop</a> first.', 'mailpoet_jigoshop'), admin_url('plugin-install.php?tab=search&type=term&s=Jigoshop')).'</p></div>';
		}
		$plugin = plugin_basename(__FILE__);
		// Deactivate this plugin until MailPoet has been installed and activated first.
		if(is_plugin_active($plugin)){ deactivate_plugins($plugin); }
	}
}

?>