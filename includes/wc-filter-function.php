<?php

use Illuminate\Support\Arr;

class WooCommerce_Filter {

    public function __construct() {
        add_action('wp', array($this, 'wc_filter_remove_woocommerce_ordering'));
        add_filter('product_attributes_type_selector', array($this, 'wc_filter_add_attr_type'));
        add_action('pa_color_edit_form_fields', array($this, 'wc_filter_edit_fields'), 10, 2);
        add_action('admin_footer', array($this, 'wc_filter_enqueue_color_picker'));
        add_action('edited_pa_color', array($this, 'wc_filter_save_color'));
        add_action('woocommerce_product_option_terms', array($this, 'wc_filter_attr_select'), 10, 3);
        add_action('wp_enqueue_scripts', array($this, 'wc_filter_enqueue_scripts'));
        add_action('woocommerce_before_shop_loop', array($this, 'wc_filter_product'));
        add_action('wp_ajax_wc_filter_product_query', array($this, 'wc_filter_product_query'));
        add_action('wp_ajax_nopriv_wc_filter_product_query', array($this, 'wc_filter_product_query'));
        add_action('activated_plugin', array($this, 'wc_filter_activate'));
        add_action('deactivated_plugin', array($this, 'wc_filter_deactivate'));
        add_filter('woocommerce_settings_tabs_array', array($this, 'wc_filter_tab'), 50);
        add_action('woocommerce_settings_tabs_custom_tab', array($this, 'wc_filter_checkbox'));
        add_action('woocommerce_update_options_custom_tab', array($this, 'wc_filter_save_settings'));
        add_action( 'woocommerce_before_shop_loop', array($this, 'wc_filter_product') );

    }

    public function wc_filter_activate() {
        $this->wc_filter_product();
        flush_rewrite_rules();
    }

    public function wc_filter_deactivate() {
        remove_action('woocommerce_before_shop_loop', array($this, 'wc_filter_product'));
        flush_rewrite_rules();
    }

    public function wc_filter_remove_woocommerce_ordering() {
        if (class_exists('WooCommerce') && (is_shop() || is_product_category() || is_product_tag())) {
            remove_action('woocommerce_before_shop_loop', 'woocommerce_catalog_ordering', 30);
            remove_action('woocommerce_before_shop_loop', 'woocommerce_result_count', 20);
        }
    }

    public function wc_filter_add_attr_type($types) {
        $types['color_type'] = 'Color';
        return $types;
    }

    public function wc_filter_edit_fields($term, $taxonomy) {
        global $wpdb;
        $attribute_type = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT attribute_type FROM {$wpdb->prefix}woocommerce_attribute_taxonomies WHERE attribute_name = '%s'",
                substr($taxonomy, 3) 
            )
        );

        if ('color_type' !== $attribute_type) {
            return;
        }

        $color = get_term_meta($term->term_id, 'color_type', true);
        ?>
        <tr class="form-field">
            <th><label for="term-color_type">Color</label></th>
            <td><input type="text" id="term-color_type" name="color_type" value="<?php echo esc_attr($color); ?>" /></td>
        </tr>
        <?php
    }

    public function wc_filter_enqueue_color_picker() {
        ?>
        <script>
        jQuery(document).ready(function($){
            $('#term-color_type').wpColorPicker();
        });
        </script>
        <?php
    }

    public function wc_filter_save_color($term_id) {
        $color_type = !empty($_POST['color_type']) ? $_POST['color_type'] : '';
        update_term_meta($term_id, 'color_type', $color_type);
    }

    public function wc_filter_attr_select($attribute_taxonomy, $i, $attribute) {
        if ('color_type' !== $attribute_taxonomy->attribute_type) {
            return;
        }

        $options = $attribute->get_options();
        $options = !empty($options) ? $options : array();
        ?>
        <select multiple="multiple" data-placeholder="Select color" class="multiselect attribute_values wc-enhanced-select" name="attribute_values[<?php echo $i ?>][]">
            <?php
            $colors = get_terms('pa_color', array('hide_empty' => 0));
            if ($colors) {
                foreach ($colors as $color) {
                    echo '<option value="' . $color->term_id . '"' . wc_selected($color->term_id, $options) . '>' . $color->name . '</option>';
                }
            }
            ?>
        </select>
        <button class="button plus select_all_attributes">Select all</button>
        <button class="button minus select_no_attributes">Select none</button>
        <?php
    }

    public function wc_filter_enqueue_scripts() {
        wp_enqueue_script('jquery-ui-slider');
        wp_enqueue_script('wc-filter-script', plugin_dir_url(__FILE__) . 'js/wc-filter.js', array('jquery', 'jquery-ui-slider'), '', true);
        wp_enqueue_style('wc-filter-style', plugin_dir_url(__FILE__) . 'css/wc-filter.css');

        global $wpdb;
        $price_range = $wpdb->get_results("
            SELECT MIN(meta_value + 0 ) as min_price, MAX(meta_value + 0 ) as max_price
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = '_price'
        ");
        $min_price = isset($price_range[0]->min_price) ? intval($price_range[0]->min_price) : 0;
        $max_price = isset($price_range[0]->max_price) ? intval($price_range[0]->max_price) : 0;

        wp_localize_script('wc-filter-script', 'wc_filter', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'min_price' => $min_price,
            'max_price' => $max_price
        ));
    }

   // Add a custom tab to the WooCommerce settings
    public function wc_filter_tab($settings_tabs) {
        $settings_tabs['custom_tab'] = __('Filter Tab', 'woocommerce-filter');
        // echo print_r($settings_tabs);echo '<pre>';
        return $settings_tabs;
    }
    
    // Display the content of the custom tab
    public function wc_filter_checkbox() {
        echo '<h2>' . __('Choose Below Checkbox To Show Frontend Filtering Option', 'woocommerce-filter') . '</h2>';
        echo '<input type="checkbox" name="category_filter" id="category_filter" value="on" ' . checked('on', get_option('category_filter_checked'), false) . '>';

        echo '<label for="category_filter">' . __('Filter by Category', 'woocommerce-filter') . '</label><br><br>';

        echo '<input type="checkbox" name="variation_filter" id="filter_checkbox" value="on" ' .checked('on', get_option('variation_filter_checked'), false) . '>' ;
        echo '<label for="variation_filter">' . __('Filter by variations', 'woocommerce-filter') . '</label><br><br>';

        echo '<input type="checkbox" name="sale_filter" id="filter_checkbox" value="on" ' .checked('on', get_option('sale_filter_checked'), false) . '>' ;
        echo '<label for="sale_filter">' . __('Filter by on-sale', 'woocommerce-filter') . '</label><br><br>';

        echo '<input type="checkbox" name="search_filter" id="filter_checkbox" value="on" ' .checked('on', get_option('search_filter_checked'), false) . '>' ;
        echo '<label for="search_filter">' . __('Filter by Search', 'woocommerce-filter') . '</label><br><br>';

        echo '<input type="checkbox" name="price_filter" id="filter_checkbox" value="on" ' .checked('on', get_option('price_filter_checked'), false) . '>' ;
        echo '<label for="price_filter">' . __('Filter by price range', 'woocommerce-filter') . '</label><br><br>';

        echo '<input type="checkbox" name="stock_filter" id="filter_checkbox" value="on"' .checked('on', get_option('stock_filter_checked'),false ) . '>';
        echo '<lable for="stock_filter">' . __('Filter by stock availibility', 'woocommerce-filter') . '<lable><br><br>';
    }

    // Save custom settings
    public function wc_filter_save_settings() {
       update_option('category_filter_checked', isset($_POST['category_filter']) ? 'on' : 'off');
       update_option('variation_filter_checked', isset($_POST['variation_filter']) ? 'on' : 'off');   
       update_option('search_filter_checked', isset($_POST['search_filter']) ? 'on' : 'off');
       update_option('sale_filter_checked', isset($_POST['sale_filter']) ? 'on' : 'off');   
       update_option('price_filter_checked', isset($_POST['price_filter']) ? 'on' : 'off');   
       update_option('stock_filter_checked', isset($_POST['stock_filter']) ? 'on' : 'off');
    }

    // Custom product filter
    public function wc_filter_product() {
        $category_filter_checked = get_option('category_filter_checked', false);
        $variation_filter_checked = get_option('variation_filter_checked', false);
        $sale_filter_checked = get_option('sale_filter_checked', false);
        $search_filter_checked = get_option('search_filter_checked', false);
        $price_filter_checked = get_option('price_filter_checked', false);
        $stock_filter_checked = get_option('stock_filter_checked', false);


        $product_cats = get_terms( array(
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ) );

        global $wpdb;
        $price_range = $wpdb->get_results("
            SELECT MIN(meta_value + 0 ) as min_price, MAX(meta_value + 0 ) as max_price
            FROM {$wpdb->prefix}postmeta
            WHERE meta_key = '_price'
        ");
        $min_price = isset($price_range[0]->min_price) ? intval($price_range[0]->min_price) : 0;
        $max_price = isset($price_range[0]->max_price) ? intval($price_range[0]->max_price) : 0;
 
        ?>
        <?php if (($category_filter_checked === 'on') || ($search_filter_checked === 'on') || ($sale_filter_checked === 'on') || ($variation_filter_checked === 'on') || ($price_filter_checked === 'on') || ($stock_filter_checked === 'on'))  { ?>
        <div class="list-pro">
            <div class="woo-form">
                <form method="get" class="wc_shop-filters" id="wc_shop-filters">
                    <?php if ($category_filter_checked === 'on') { ?>
                        <div class="wc_filtered-products">
                            <h5><?php _e('Filter by Category', 'woocommerce-filter'); ?></h5>
                            <?php foreach( $product_cats as $color ) : ?>
                                <input type="checkbox" class="wc_category-filter" name="product_cat[]" value="<?php echo esc_attr( $color->slug ); ?>"><?php echo esc_html( $color->name ); ?><br>
                            <?php endforeach; ?>
                        </div><br>
                    <?php } ?> 

                    <?php if ($variation_filter_checked === 'on') { ?>
                        <div class="wc_filter-variation">
                            <h5><?php _e('Size', 'woocommerce-filter'); ?></h5>
                            <?php
                            $sizes = get_terms( array(
                                'taxonomy' => 'pa_size',
                                'hide_empty' => false,
                            ) );
                            foreach( $sizes as $size ) : ?>
                                <label>
                                    <input type="checkbox" class="wc_size-filter" name="pa_size[]" value="<?php echo esc_attr( $size->slug ); ?>">
                                    <?php echo esc_html( $size->name ); ?>
                                </label>
                            <?php endforeach; ?>
                           
                            
                             <h5><?php _e('Color', 'woocommerce-filter'); ?></h5>
                            <?php
                                $colors = get_terms( array(
                                    'taxonomy' => 'pa_color',
                                    'hide_empty' => false,
                                ) );
                                foreach( $colors as $color ) :
                            ?>
                                <span class="color-swatch" name="pa_color[]" value="<?php echo esc_attr($color->slug);?>" style="background-color: <?php echo esc_attr( get_term_meta( $color->term_id, 'color_type', true ) ); ?>"></span>
                            <?php endforeach; ?>
                        </div>
                    <?php } ?>

                        <?php if ($search_filter_checked === 'on') { ?>
                            <div class="search_product">
                                <h5>Search</h5>
                                <input type="text" name="product_search[]" id="wc_search_product" value="" class="wc_search_product" placeholder="Search by product" />
                            </div><br>
                        <?php } ?>
                      
                    <?php if ($sale_filter_checked === 'on') { ?>
                        <div class="sale_product">
                            <h5><?php _e('Filter By on-sale', 'woocommerce-filter'); ?></h5>
                            <input type="checkbox" name="sale_product[]" id="wc_sale_product" class="wc_sale_product" value="on_sale" /> 
                            <label for="wc_sale_product"><?php _e('Filter by on-sale products', 'woocommerce-filter'); ?></label>
                        </div>
                    <?php } ?>
                   
                     <?php  if ($price_filter_checked === 'on') { ?>
                        <h5><?php _e('Price Range:', 'woocommerce-filter'); ?></h5>
                        <div class="price-input">
                            <div class="field">
                                <span>Min</span>
                                <input type="number" class="input-min" value="<?php echo $min_price; ?>">
                            </div>
                            <!-- <div class="separator">-</div> -->
                            <div class="field">
                                <span>Max</span>
                                <input type="number" class="input-max" value="<?php echo $max_price; ?>">
                            </div>
                        </div>
                        <div class="price_slider_range">
                            <div id="price-slider"></div>
                            <span id="price-range">₹<?php echo $min_price; ?> - ₹<?php echo $max_price; ?></span>
                        </div>
                    <?php  } ?>  

                    <?php if ($stock_filter_checked === 'on') { ?>    
                        <div class="stock-availability">
                           <h5>Stock Availability</h5>
                           <input type="checkbox" name="in-stock[]" id="wc-in-stock" value="in-stock">
                           <label for="wc-in-stock">In Stock</label>
                           <input type="checkbox" name="out-of-stock[]" id="wc-out-of-stock" value="out-of-stock">
                           <label for="wc-out-of-stock">Out Of Stock</label>
                        </div>
                        <?php } ?>
                    <br>
                    <input type="hidden" name="post_type" value="product" />
                    <input type="button" id="wc_reset-button" value="<?php _e('Reset', 'woocommerce-filter'); ?>" />
                </form>
            </div>
            <div class="close-cat"> 
                <div id="selectedValues"></div> 
            </div>
            <div class="wc-default-filter">
                <?php  woocommerce_catalog_ordering(); ?>
            </div>
            <?php ?>
            <div id="wc_filtered-product">
        <?php
    }
}

    // AJAX callback for custom product filter query
    public function wc_filter_product_query() {

        $args = array(
            'post_type' => 'product',
            'posts_per_page' => 6,
            'paged' => isset($_POST['page']) ? $_POST['page'] : 1, 
        );

        if ( isset( $_POST['formData'] ) ) {
            parse_str( $_POST['formData'], $formData );

            if (!empty($formData['product_search'])) {
                $search_term = sanitize_text_field($formData['product_search'][0]);
                $product_id_by_sku = wc_get_product_id_by_sku($search_term);

                $args['meta_query'] = array(
                    'relation' => 'AND', 
                    array(
                        'key'     => '_sku',
                        'value'   => $search_term,
                        'compare' => 'LIKE',

                    ),
                );
            }

            if ( !empty( $formData['product_cat'] ) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'product_cat',
                    'field' => 'slug',
                    'terms' => $formData['product_cat'],
                );
            }

            if ( !empty( $formData['pa_size'] ) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'pa_size',
                    'field' => 'slug',
                    'terms' => $formData['pa_size'],
                );
            }

            if ( !empty( $formData['pa_color'] ) ) {
                $args['tax_query'][] = array(
                    'taxonomy' => 'pa_color',   
                    'field' => 'slug',
                    'terms' => $formData['pa_color'],
                );
            }

            if (!empty($formData['sale_product'])) {
                $args['meta_query'] = WC()->query->get_meta_query();
                $args['post__in'] = array_merge(array(0), wc_get_product_ids_on_sale());
            }
           
            if (!empty($_POST['minPrice']) && !empty($_POST['maxPrice'])) {
                $minPrice = floatval($_POST['minPrice']);
                $maxPrice = floatval($_POST['maxPrice']);

                $args['meta_query'][] = array(
                    'key' => '_price',
                    'value' => array($minPrice, $maxPrice),
                    'type' => 'numeric',
                    'compare' => 'BETWEEN',
                );
            }
            if (!empty($formData['in-stock'])) {
                $args['meta_query'][] = array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '!=',
                );
            } elseif (!empty($formData['out-of-stock'])) {
                $args['meta_query'][] = array(
                    'key' => '_stock_status',
                    'value' => 'outofstock',
                    'compare' => '=',
                );
            }

        }
        
        $query = new WP_Query($args);

          ob_start();
        if ( $query->have_posts() ) {
            woocommerce_product_loop_start();

            while ( $query->have_posts() ) {
                $query->the_post();
                do_action( 'woocommerce_shop_loop' );
                wc_get_template_part( 'content', 'product' ); 
            }

            woocommerce_product_loop_end();

            $total_pages = $query->max_num_pages;

            if ($total_pages > 1) {
                echo '<div class="wc_pagination-links">';
                echo '<ul class="pagination">';

                if ($args['paged'] > 1) {
                    echo '<li class="page-item"><a href="#" class="page-link" data-page="' . ($args['paged'] - 1) . '">« Previous</a></li>';
                }   

                for ($i = 1; $i <= $total_pages; $i++) {
                    $active_class = ($i == $args['paged']) ? 'Active' : '';
                    echo '<li class="' . $active_class . '"><a href="#" class="page-link" data-page="' . $i . '">' . $i . '</a></li>';
                }

                if ($args['paged'] < $total_pages) {
                    echo '<li class="page-item"><a href="#" class="page-link" data-page="' . ($args['paged'] + 1) . '">Next »</a></li>';
                }

                echo '</ul>';
                echo '</div>';
            }
        } else {
            echo __('No products found', 'woocommerce-filter');
        }
        $output = ob_get_clean();
        echo $output;
        die();
    }
}

new WooCommerce_Filter();
