<?php
/*
Plugin Name: BOXTONS WooCommerce Fulfillment Integration
Plugin URI: http://www.boxtons.com/plugins
Description: Allows for advanced integration with your BOXTONS Fulfillment Control Panel.
Author: Boxtons LTD
Version: 1.0.0
Author URI: http://www.boxtons.com
*/   

/**
* Check if bfs class exists and define
*/
if (!class_exists('woocommerce_bfs')) {
    class woocommerce_bfs {
        /**
        * @var string The options string name for this plugin
        */
        var $optionsName = 'woocommerce_bfs_options';
        /**
        * @var string $localizationDomain Domain used for localization
        */
        var $localizationDomain = "woocommerce_bfs";
        /**
        * @var string $pluginurl The path to this plugin
        */ 
        var $thispluginurl = '';
        /**
        * @var string $pluginurlpath The path to this plugin
        */
        var $thispluginpath = '';
        /**
        * @var array $options Stores the options for this plugin
        */
        var $options = array();
        //Class Functions
        /**
        * PHP 4 Compatible Constructor
        */
        //function woocommerce_bfs(){$this->__construct();}
        /**
        * PHP 5 Constructor
        */        
        function __construct(){
            //Language Setup
            $locale = get_locale();
            $mo = dirname(__FILE__) . "/languages/" . $this->localizationDomain . "-".$locale.".mo";
            load_textdomain($this->localizationDomain, $mo);
            //"Constants" setup
            $this->thispluginurl = WP_PLUGIN_URL . '/' . dirname(plugin_basename(__FILE__)).'/';
            $this->thispluginpath = WP_PLUGIN_DIR . '/' . dirname(plugin_basename(__FILE__)).'/';
            //Initialize the options
            //This is REQUIRED to initialize the options when the plugin is loaded!
            $this->getOptions();
            //Actions        
            add_action("admin_menu", array(&$this,"admin_menu_link"));
			/**
			* Submit order and details to BOXTONS
			*/
			add_action( 'woocommerce_order_status_processing', 'submit_to_boxtons');
			function login_to_boxtons() {
				//Login to the API
				$apiInfo = get_option('woocommerce_bfs_options');
				$APIKey = $apiInfo['bfs_APIKey'];
				$APISecret = $apiInfo['bfs_APISecret'];
				$data = array(
					'APIKey' => $APIKey,
					'APISecret' => $APISecret
				);
				$jsonData = json_encode($data);
				$apiUrl = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/auth/v1/login/';				
				$ch = curl_init($apiUrl);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST"); //GET, POST, PUT, DELETE
				curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
				curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt'); 
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($jsonData)
				));
				$response = curl_exec($ch);
				curl_close($ch);
			}login_to_boxtons();
			function submit_to_boxtons($order_id){
				//Get Order and details
				$order = new WC_Order( $order_id );
				$myuser_id = (int)$order->user_id;
				$user_info = get_userdata($myuser_id);
				$customerName = $order->shipping_first_name.' '.$order->shipping_last_name;
				$customerAddress = $order->shipping_address_1.' '.$order->shipping_address_2;				
				$customerCity = $order->shipping_city;
				$customerState = $order->shipping_state;
				$customerPostcode = $order->shipping_postcode;
				$customerCountry = $order->shipping_country;
				$orderItems = array();
				$items = $order->get_items();
				foreach ($items as $item) {
					$itemId = $item['product_id'];
					$BPID = get_post_meta( $itemId, '_BOX_PID', true );					
					$orderItems[] = array('productId' => $BPID, 'quantity' =>$item['qty']);
				}
				login_to_boxtons();				
				// Put order data into JSON array 
				$data = array(
					'customerName' => $customerName,
					'customerAddress' => $customerAddress,
					'customerCity' => $customerCity,
					'customerState' => $customerState,
					'customerPostcode' => $customerPostcode,
					'customerCountry' => $customerCountry,
					'APIOrderId' => $order_id,
					'orderItems' => $orderItems
				);
				$jsonData = json_encode($data);
				$apiUrl1 = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/orders/V1/order/';
				$ch = curl_init($apiUrl1);
				curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT"); //GET, POST, PUT, DELETE
				curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
				curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
				curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
				curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
				curl_setopt($ch, CURLOPT_HTTPHEADER, array(
					'Content-Type: application/json',
					'Content-Length: ' . strlen($jsonData)
				));
				$response = curl_exec($ch);
				curl_close($ch);
				//print_r($response);				
			}
			/**
			* Show boxtons PID om product
			*/
			add_action( 'woocommerce_product_options_general_product_data', 'wc_custom_add_custom_fields' );
			function wc_custom_add_custom_fields() {
				// Print a custom text field
				woocommerce_wp_text_input( array(
					'id' => '_BOX_PID',
					'label' => 'BOXTONS Product ID',
					'description' => 'Please Enter The BOXTONS Product ID Relative to this product in your Boxtons Fulfilment Center Account.',
					'desc_tip' => 'true',
					'placeholder' => ''
				) );
			}        
				/**
				* Save boxtons PID for product
				*/
				add_action( 'woocommerce_process_product_meta', 'wc_custom_save_custom_fields' );
				function wc_custom_save_custom_fields( $post_id ) {
					if ( ! empty( $_POST['_BOX_PID'] ) ) {
						update_post_meta( $post_id, '_BOX_PID', sanitize_text_field( $_POST['_BOX_PID'] ) );
					}
				}
				/**
				* Gets details and submits to boxtons
				*/
        }
        /**
        * Retrieves the plugin options from the database.
        * @return array
        */
        function getOptions() {
            //Don't forget to set up the default options
            if (!$theOptions = get_option($this->optionsName)) {
                $theOptions = array('default'=>'options');
                update_option($this->optionsName, $theOptions);
            }
            $this->options = $theOptions;
            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
            //There is no return here, because you should use the $this->options variable!!!
            //!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!
        }
        /**
        * Saves the admin options to the database.
        */
        function saveAdminOptions(){
            return update_option($this->optionsName, $this->options);
        }
        /**
        * @desc Adds the options subpanel
        */
        function admin_menu_link() {
			add_submenu_page('woocommerce', 'BOXTONS Integration', 'BOXTONS Integration','administrator', __FILE__,array(&$this,'admin_options_page'));
			add_filter( 'plugin_action_links_' . plugin_basename(__FILE__), array(&$this, 'filter_plugin_actions'), 10, 2 );
        }
        /**
        * @desc Adds the Settings link to the plugin activate/deactivate page
        */
        function filter_plugin_actions($links, $file) {
           $settings_link = '<a href="admin.php?page=boxtons-woo-fulfillment-integration/' . basename(__FILE__) . '">' . __('Settings') . '</a>';

           array_unshift( $links, $settings_link ); // before other links
           return $links;
        }
        /**
        * Adds settings/options page
        */
        function admin_options_page() { 
            if(isset($_POST['woocommerce_bfs_save'])){
                if (! wp_verify_nonce($_POST['_wpnonce'], 'woocommerce_bfs-update-options') ) die('<div class="error"><p>Whoops! There was a problem with the data you posted. Please go back and try again.</p></div>');
				
				// Ensure that all data is validated 
				if (strlen($_POST['bfs_APIKey']) != 45 or strlen($_POST['bfs_APISecret']) != 45) {
					echo '<div class="error"><p>ERROR! Your API details were incorrect, please ensure that the inputed details match those inside your myBoxtons Account!</p></div>';}
				else {
					// Ensure that all data is sanitized 
					$bfs_APIKey = sanitize_text_field($_POST['bfs_APIKey']);
					$bfs_APISecret = sanitize_text_field($_POST['bfs_APISecret']);
					// Save data for later use
					$this->options['bfs_APIKey'] = $bfs_APIKey;                   
					$this->options['bfs_APISecret'] = $bfs_APISecret;                                        
					$this->saveAdminOptions();
					echo '<div class="updated"><p>Success! Your API details were sucessfully saved!</p></div>';
				}
            }
            $statuses = (array) get_terms('shop_order_status', array('hide_empty' => 0, 'orderby' => 'id'));
            $yesnos = array('NO', 'YES');
?>                                   
                <div class="wrap">
                <img src="<?php echo plugin_dir_url( __FILE__ ); ?>images/boxtons-logo-on-white-570x90.png">
                <h1>Welcome to your BOXTONS WooCommerce Plugin, Full integration into your Boxtons fulfillment Center Account!</h1>
                <p>This plugin links your Woocommerce store into your BOXTONS Fulfilment center account. This link allows your Woocommerce store to pass orders to BOXTONS and allows BOXTONS to update your stock and order statuses. More features will be coming in the near future so ensure to keep this product up to date. Thanks for using BOXTONS, if you have any questions or queries please do not hesitate to contact <a href="mailto:support@boxtons.com">support</a>. </p>
                <div class="notice notice-warning is-dismissible">
                    <p><?php _e( 'NOTE: DONT FORGET TO LINK YOUR PRODUCTS! YOU MUST SET THE BOXTONS PRODUCT ID FOR EACH PRODUCT THAT YOU WANT FULFILLED.', 'NOTE: DONT FORGET TO LINK YOUR PRODUCTS! YOU MUST SET THE BOXTONS <strong>PRODUCT ID</strong> AND <strong>IDENTICAL SKU</strong> FOR EACH PRODUCT THAT YOU WANT FULFILLED.' ); ?></p>
                </div>
                <h3 style="color: red; background-color: #ffc4c4;">
                    </h3>
                <?php 
				// If Admin Details Are Saved, Login.
				if(isset($this->options['bfs_APIKey']) && $this->options['bfs_APIKey'] != '') {
					function getInventoryTotal($pid) {
					$apiUrl1 = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/inventory/V1/inventory/'.$pid.'.json';
					$ch = curl_init($apiUrl1);
					curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
					curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
					curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
					curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
					$json = curl_exec($ch);
					curl_close($ch);
					$data = json_decode($json, true);
					return $data['inventoryTotal'];
				}
					// Get Products and total inventory
				function getProductsTable() {
						// Call the login function			
						$apiUrl1 = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/products/V1/product/.json';
						$ch = curl_init($apiUrl1);
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
						curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
						$json = curl_exec($ch);
						curl_close($ch);
						$data = json_decode($json, true);					
					?>
                    	<h2>BOXTONS Products</h2><span> This table shows you all products that you have setup in your BOXTONS account.<br /><br />
<strong>NOTE:</strong> To Ensure product is linked correctly with BOXTONS ensure the <strong>SKU</strong> and <strong>BOXTONS Product ID#</strong> match correctly in your WooCommerce <strong>product setup</strong>. Failure to do this correctly can mean stock not getting updated and wrong items getting sent with orders.</span><br />
						<table class="wp-list-table widefat" cols="4">
						<thead>
						  <tr>
							<th>BOXTONS PID#</th>
							<th>Product SKU / Name</th>
							<th>Product Description</th>
                            <th>Total Inventory</th>
						  </tr>
						</thead>
						<tbody>
						  <?php for($i=0;$i<count($data);$i++) {
							  $pid = esc_html($data[$i]['productId']);
							  echo('<tr>');
							  echo('<td>' . esc_html($data[$i]['productId']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['productName']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['productDescription']) . '</td>');
							  echo('<td>' . getInventoryTotal($pid) . '</td>');
							  echo('</tr>');
							} ?>
						</tbody>
					  </table> 
				<?php } 
				echo getProductsTable();
					function getOrdersTable() {
						// Call the login function
						$apiUrl1 = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/orders/V1/order/.json';
						$ch = curl_init($apiUrl1);
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
						curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
						$json = curl_exec($ch);
						curl_close($ch);
						$data = json_decode($json, true);
					?>
                    	<h2>Your Latest Orders</h2><span> This table shows the most recent <strong>45 orders</strong> from your BOXTONS account. </span><br />
						<table class="wp-list-table widefat" cols="6">
						<thead>
						  <tr>
							<th>Order Id</th>
							<th>Customer Name</th>
							<th>Customer Address</th>
							<th>Warehouse Status</th>
							<th>Recieved Date</th>
                            <th>WooCommerce OrderID</th>
						  </tr>
						</thead>
						<tbody>
						  <?php for($i=0;$i<count($data);$i++) {
							  echo('<tr>');
							  echo('<td><a class="button" target="_blank" href="http://boxtons.com/myDev/myBoxtons/editOrder.php?orderId='.esc_html($data[$i]['orderId']).'"> View Order#' . esc_html($data[$i]['orderId']) . ' on BOXTONS</a></td>');
							  echo('<td>' . esc_html($data[$i]['customerName']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['customerAddress']).', '.esc_html($data[$i]['customerCity']).', '. esc_html($data[$i]['customerState']).', '.esc_html($data[$i]['customerPostcode']).', '.esc_html($data[$i]['customerCountry']).', ' . '</td>');
							  echo('<td>' . esc_html($data[$i]['warehouseStatus']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['dateCreated']) . '</td>');
							  if($data[$i]['APIOrderId'] == 0) {echo('<td>Not Via WooCommerce</td>');  } else { echo('<td><a class="button button-primary" href="http://boxtons.com/word4woo/wp-admin/post.php?post='.esc_html($data[$i]['APIOrderId']).'&action=edit">View Order # ' . esc_html($data[$i]['APIOrderId']) . '</a></td>'); }
							  echo('</tr>');
							} ?>
						</tbody>
					  </table> 
				<?php } 
				// Echo the orders table
				echo getOrdersTable();
				// Get Products and total inventory
				function getInventoryTable() {
						// Call the login function
						$apiUrl1 = 'http://www.boxtons.com/API/boxAPI/api-services/index.php/inventory/V1/inventory/.json';
						$ch = curl_init($apiUrl1);
						curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "GET");
						curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
						curl_setopt($ch, CURLOPT_COOKIEFILE, dirname(__FILE__).'/cookies.txt');
						curl_setopt($ch, CURLOPT_COOKIEJAR, dirname(__FILE__).'/cookies.txt');
						$json = curl_exec($ch);
						curl_close($ch);
						$data = json_decode($json, true);
					?>
                    	<h2>BOXTONS Inventory</h2>
						<table class="wp-list-table widefat" cols="3">
						<thead>
						  <tr>
							<th>Product Name</th>
							<th>Inventory Total</th>
							<th>Inventory Status</th>
						  </tr>
						</thead>
						<tbody>
						  <?php for($i=0;$i<count($data);$i++) {
							  echo('<tr>');
							  echo('<td>' . esc_html($data[$i]['productName']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['inventoryTotal']) . '</td>');
							  echo('<td>' . esc_html($data[$i]['inventoryStatus']) . '</td>');
							  echo('</tr>');
							} ?>
						</tbody>
					  </table> 
				<?php } 
		}?>
        		<h2>Your <strong>BOXTONS</strong> API Credentials</h2>
                <p>To Connect visit your BOXTONS Profile page and generate your API Key & secret, then copy and paste into the fields below and finally click the save button. To disconect the BOXTONS API link simply remove your API Key & Secret and click save.</p>
                <form method="post" id="woocommerce_bfs_options">
                <?php wp_nonce_field('woocommerce_bfs-update-options'); ?>
                    <table width="100%" cellspacing="2" cellpadding="5" class="form-table"> 
                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('BOXTONS API Key:', $this->localizationDomain); ?></th> 
                            <td><input name="bfs_APIKey" type="text" id="bfs_APIKey" size="45" maxlength="45" value="<?php echo $this->options['bfs_APIKey'] ;?>"/>
                            <span style="color: silver; font-style: italic;">This can be generated and found in your BOXTONS profile page</span>
                        </td> 
                        </tr>
                        <tr valign="top"> 
                            <th width="33%" scope="row"><?php _e('BOXTONS API Secret:', $this->localizationDomain); ?></th> 
                            <td><input name="bfs_APISecret" type="text" id="bfs_APISecret" size="45" maxlength="45" value="<?php echo $this->options['bfs_APISecret'] ;?>"/>
                            <span style="color: silver; font-style: italic;">This can be generated and found in your BOXTONS profile page</span>
                            </td> 
                        </tr>
                        <tr>
                            <th colspan=2><input class="button button-primary" type="submit" name="woocommerce_bfs_save" value="Save" /></th>
                        </tr>
                    </table>
                </form>
                </div>
                <?php
                $pluginUrl = $this->thispluginurl;
                ?>
                <div class="wrap">
                	<h2>A word from BOXTONS</h2>
                    <p><strong>PLEASE NOTE:</strong> This plugin is brand new, we are making many updates so please ensure to keep it updated. If you encounter any issues please contact support.</p>
                </div>
                <?php
        }
  } //End Class
} //End if class exists statement
//instantiate the class
if (class_exists('woocommerce_bfs')) {
    $woocommerce_bfs_var = new woocommerce_bfs();
}
?>