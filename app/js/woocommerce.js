// WooCommerce Header Cart Update and Sidebar Cart
jQuery(document).ready(function($) {
    
    // Cart Sidebar Functionality
    function openCartSidebar() {
        $('#cart-sidebar').addClass('open');
        $('body').addClass('cart-sidebar-open');
    }
    
    function closeCartSidebar() {
        $('#cart-sidebar').removeClass('open');
        $('body').removeClass('cart-sidebar-open');
    }
    
    // Shop Filters Toggle for Mobile
    function openShopFilters() {
        $('#shop-filters-overlay').addClass('active');
        $('#shop-filters-toggle').addClass('active');
        $('body').addClass('shop-filters-open');
    }
    
    function closeShopFilters() {
        $('#shop-filters-overlay').removeClass('active');
        $('#shop-filters-toggle').removeClass('active');
        $('body').removeClass('shop-filters-open');
    }
    
    // Shop filters toggle event
    $(document).on('click', '#shop-filters-toggle', function(e) {
        e.preventDefault();
        if ($(this).hasClass('active')) {
            closeShopFilters();
        } else {
            openShopFilters();
        }
    });
    
    // Close shop filters when close button or overlay is clicked
    $(document).on('click', '.shop-filters-close, .shop-filters-overlay', function(e) {
        if (e.target === this) {
            closeShopFilters();
        }
    });
    
    // Close shop filters with Escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#shop-filters-overlay').hasClass('active')) {
            closeShopFilters();
        }
    });
    
    // Open cart sidebar when cart icon is clicked
    $(document).on('click', '.woo-cart-icon, .mobile-woo-cart-icon', function(e) {
        e.preventDefault();
        openCartSidebar();
    });
    
    // Close cart sidebar when close button or overlay is clicked
    $(document).on('click', '.cart-sidebar-close, .cart-sidebar-overlay', function() {
        closeCartSidebar();
    });
    
    // Close cart sidebar with Escape key
    $(document).on('keydown', function(e) {
        if (e.keyCode === 27 && $('#cart-sidebar').hasClass('open')) {
            closeCartSidebar();
        }
    });
    
    // Handle remove from cart in sidebar
    $(document).on('click', '.cart-sidebar .remove_from_cart_button', function(e) {
        e.preventDefault();
        var $removeButton = $(this);
        var cart_item_key = $removeButton.data('cart_item_key');
        
        $.ajax({
            url: wc_add_to_cart_params.wc_ajax_url.toString().replace('%%endpoint%%', 'remove_from_cart'),
            type: 'POST',
            data: {
                cart_item_key: cart_item_key
            },
            success: function(response) {
                if (response && response.fragments) {
                    updateCartSidebar();
                    updateHeaderCartCount();
                }
            }
        });
    });
    
    // Update cart sidebar content
    function updateCartSidebar() {
        var ajaxUrl = ajax_variable.ajax_url || (typeof wc_add_to_cart_params !== 'undefined' ? wc_add_to_cart_params.ajax_url : '/wp-admin/admin-ajax.php');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_sidebar_cart_content'
            },
            success: function(response) {
                if (response.success) {
                    $('#cart-sidebar .cart-sidebar-body').html(response.data.content);
                    if (response.data.footer) {
                        $('#cart-sidebar .cart-sidebar-footer').html(response.data.footer).show();
                    } else {
                        $('#cart-sidebar .cart-sidebar-footer').hide();
                    }
                }
            }
        });
    }
    
    // Update cart count in header
    function updateHeaderCartCount() {
        var ajaxUrl = ajax_variable.ajax_url || (typeof wc_add_to_cart_params !== 'undefined' ? wc_add_to_cart_params.ajax_url : '/wp-admin/admin-ajax.php');
        
        $.ajax({
            url: ajaxUrl,
            type: 'POST',
            data: {
                action: 'get_cart_count'
            },
            success: function(response) {
                if (response.success) {
                    var cartCount = response.data.count;
                    var $cartCountElement = $('.cart-count');
                    var $mobileCartElement = $('.mobile-woo-cart-icon span');
                    
                    // Always show cart count, even if 0
                    if ($cartCountElement.length) {
                        $cartCountElement.text(cartCount);
                    } else {
                        $('.woo-cart-icon').append('<span class="cart-count">' + cartCount + '</span>');
                    }
                    
                    // Update mobile cart text - always show count
                    if ($mobileCartElement.length) {
                        $mobileCartElement.text('Shopping Cart (' + cartCount + ')');
                    }
                }
            }
        });
    }
    
    // Update cart on add to cart button click (WooCommerce event)
    $(document.body).on('added_to_cart', function() {
        updateHeaderCartCount();
        updateCartSidebar();
        // Auto-open sidebar when item is added
        openCartSidebar();
    });
    
    // Update cart when cart is updated
    $(document.body).on('updated_wc_div', function() {
        updateHeaderCartCount();
        updateCartSidebar();
    });
    
    // Update cart on page load if fragments are updated
    $(document.body).on('wc_fragments_refreshed', function() {
        updateHeaderCartCount();
        updateCartSidebar();
    });
    
    // Also listen for standard AJAX add to cart forms
    $('form.cart').on('submit', function(e) {
        var form = $(this);
        if (form.find('.single_add_to_cart_button').hasClass('ajax_add_to_cart')) {
            setTimeout(function() {
                updateHeaderCartCount();
                updateCartSidebar();
                openCartSidebar();
            }, 1000);
        }
    });
    
    // Listen for any AJAX add to cart buttons
    $('.ajax_add_to_cart').on('click', function() {
        setTimeout(function() {
            updateHeaderCartCount();
            updateCartSidebar();
            openCartSidebar();
        }, 1000);
    });
    
});