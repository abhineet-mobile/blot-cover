/* 1️⃣ Add Boltcover Checkbox on the Product Page */
function add_boltcover_checkbox() {
    echo '<p><label><input type="checkbox" id="boltcover_checkbox" name="boltcover_checkbox" value="yes" checked> 
    Complimentary 5-year Premium Product Protection. 
    <a href="/warranty-care/" target="_blank"> Learn more.</a></label></p>';
}
add_action('woocommerce_before_add_to_cart_button', 'add_boltcover_checkbox');

/* 2️⃣ Store Checkbox Value When Adding to Cart */
function save_boltcover_checkbox_data($cart_item_data, $product_id) {
    if (isset($_POST['boltcover_checkbox'])) {
        $cart_item_data['boltcover'] = true;
    }
    return $cart_item_data;
}
add_filter('woocommerce_add_cart_item_data', 'save_boltcover_checkbox_data', 10, 2);

/* 3️⃣ Show Insurance Selection in Cart */
function display_boltcover_option_in_cart($item_data, $cart_item) {
    if (isset($cart_item['boltcover'])) {
        $item_data[] = array(
            'name'  => 'Premium Product Protection Insurance',
            'value' => 'Yes'
        );
    }
    return $item_data;
}
add_filter('woocommerce_get_item_data', 'display_boltcover_option_in_cart', 10, 2);

/* 4️⃣ Save Insurance Data (Category & Price) in the Order */
function save_boltcover_meta_to_order($item, $cart_item_key, $values, $order) {
    if (isset($values['boltcover'])) {
        $product_id = $item->get_product_id();
        $categories = wp_get_post_terms($product_id, 'product_cat', array('fields' => 'names'));

        // Determine Insurance Category
        $insurance_category = "Ottomans"; // Default category
        if (in_array('Sofas', $categories)) {
            $insurance_category = "Sofas";
        } elseif (in_array('Chairs', $categories)) {
            $insurance_category = "Chairs";
        }

        // Add metadata to order item
        $item->add_meta_data('boltcover', 'yes', true);
        $item->add_meta_data('insurance-category', $insurance_category, true);
        $item->add_meta_data('insurance-price', '0.0', true);
    }
}
add_action('woocommerce_checkout_create_order_line_item', 'save_boltcover_meta_to_order', 10, 4);

/* 5️⃣ Send Order Data to Boltcover API */
function send_boltcover_order_to_api($order_id) {
    $order = wc_get_order($order_id);
    $api_url = 'https://api.boltcover.com/test/orders';
    $api_key = defined('BOLTCOVER_API_KEY') ? BOLTCOVER_API_KEY : '';

    $transactions = array();

    foreach ($order->get_items() as $item_id => $item) {
        // ✅ Check if Boltcover was selected
        if ($item->get_meta('boltcover', true) !== 'yes') {
            continue;
        }

        $product_id = $item->get_product_id();
        $product = wc_get_product($product_id);
        $insurance_category = $item->get_meta('insurance-category', true);
        $insurance_price = floatval($item->get_meta('insurance-price', true));

        $transactions[] = array(
            "product-id"          => strval($product_id),
            "product-name"        => $product->get_name(),
            "product-price"       => floatval($product->get_price()),
            "insurance-price"     => $insurance_price, // Always 0.0
            "insurance-category"  => $insurance_category,
            "quantity"            => (int) $item->get_quantity(),
            "term"                => "5",
            "inclusive_insurance" => true
        );
    }

    // ✅ Only send request if products with insurance exist
    if (empty($transactions)) {
        return;
    }

    // Prepare order data
    $order_data = array(
        "order" => array(
            "order-id"    => strval($order_id),
            "order-date"  => gmdate("c", strtotime($order->get_date_created())),
            "transactions" => $transactions,
            "customer"    => array(
                "first-name"    => $order->get_billing_first_name(),
                "last-name"     => $order->get_billing_last_name(),
                "email"         => $order->get_billing_email(),
                "address-line-1"=> $order->get_billing_address_1(),
                "address-line-2"=> $order->get_billing_address_2(),
                "city"          => $order->get_billing_city(),
                "postcode"      => $order->get_billing_postcode(),
            )
        )
    );

    // ✅ Send API request
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'x-api-key'    => $api_key, 
            'Content-Type' => 'application/json',
        ),
        'body'    => json_encode($order_data),
        'method'  => 'POST',
    ));

    // Handle API response
    if (is_wp_error($response)) {
        error_log('Boltcover API error: ' . $response->get_error_message());
    } else {
        $response_body = json_decode(wp_remote_retrieve_body($response), true);

        if (isset($response_body['status']) && $response_body['status'] === 'success') {
            error_log('Boltcover Insurance Created: ' . print_r($response_body, true));
            $order->add_order_note('Boltcover Insurance ID: ' . $response_body['insurance_id']);
        } else {
            error_log('Boltcover API failed: ' . print_r($response_body, true));
            $order->add_order_note('Boltcover Insurance Failed. Response: ' . print_r($response_body, true));
        }
    }
}
add_action('woocommerce_thankyou', 'send_boltcover_order_to_api');
