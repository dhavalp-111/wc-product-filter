jQuery(document).ready(function($) {

    var filterApplied = false;  

    $(window).scroll(function(){
        if ($(this).scrollTop() > 50) {
           $('.header_section').addClass('active-bar');
        } else {
           $('.header_section').removeClass('active-bar');
        }
    });

    $('#price-slider').slider({
        range: true,
        min: parseInt(wc_filter.min_price),
        max: parseInt(wc_filter.max_price),
        values: [parseInt(wc_filter.min_price), parseInt(wc_filter.max_price)],
        slide: function(event, ui) {
            var minPrice = ui.values[0];
            var maxPrice = ui.values[1];
            $('#price-range').text('₹' + minPrice + ' - ₹' + maxPrice);
            // Update input values
            $('.input-min').val(minPrice);
            $('.input-max').val(maxPrice);
            // Trigger filter function
            if (filterApplied) {
                filterProduct();
                updateSelectedValues(); // Update selected values display
            }
        },
    });

    // Function to update input values based on slider
    $('#price-slider').on('slidechange', function(event, ui) { 
        var minPrice = ui.values[0];
        var maxPrice = ui.values[1];
        $('.input-min').val(minPrice);
        $('.input-max').val(maxPrice);
        updateSelectedValues(); // Update selected values display
    });

    // Function to update slider values based on input fields
    $('.input-min, .input-max').on('input', function() {
        var minPrice = parseInt($('.input-min').val());
        var maxPrice = parseInt($('.input-max').val());

        // Update slider values
        $('#price-slider').slider('values', 0, minPrice);
        $('#price-slider').slider('values', 1, maxPrice);
        $('#price-range').text('₹' + minPrice + ' - ₹' + maxPrice);
        // Trigger filter function
        if (filterApplied) {
            filterProduct();
            updateSelectedValues();
        }
         
    });

    $(document).on('click', '.color-swatch', function(e) {
        // Check if the color swatch is already selected
        var isSelected = $(this).hasClass('selected');
        $('.color-swatch').removeClass('selected');
        
        if (!isSelected) {
            $(this).addClass('selected');
        }

        var selectedColors = $('.color-swatch.selected').map(function() {
            return $(this).attr('value');
        }).get();

        if (filterApplied) {
           filterProduct(selectedColors);
           updateSelectedValues(); 
        }
    });

    $(document).on('click', '.wc_pagination-links a', function(e) {
        e.preventDefault();
        var page = $(this).data('page');    
        if (filterApplied) {
            filterProduct(null, page);
        }        
    });
    $(document).on('change', '.stock-availability input[type="checkbox"]', function() {
        if (filterApplied) {
            filterProduct(); // Trigger filter function on checkbox change
            updateSelectedValues();
        }
    });
 
    // Function to filter products
    function filterProduct(selectedColors, page) {
        var minPrice = parseInt($('.input-min').val());
        var maxPrice = parseInt($('.input-max').val());

        var formData = $('#wc_shop-filters').serialize();

       // Include selected colors in the data
        if (Array.isArray(selectedColors) && selectedColors.length > 0) {
            formData += '&pa_color=' + selectedColors.join(',');
        }

       $.ajax({
            type: 'POST',
            url: wc_filter.ajaxurl,
            data: {
                action: 'wc_filter_product_query',
                formData: formData,
                page: page,
                minPrice: minPrice,
                maxPrice: maxPrice
            },
            success: function(response) {
                $('#wc_filtered-product').html(response);
            },
            error: function(jqXHR, textStatus, errorThrown) {
                console.error("AJAX Error: " + textStatus, errorThrown);
            }
        });
    }

    // Search input change
    $('#wc_search_product').on('input', function() {
        if (filterApplied) {
            updateSelectedValues();
            filterProduct();
        }
    });

    $('.wc_category-filter, .wc_size-filter, #wc_sale_product').change(function() {
        if (filterApplied) {
            filterProduct();
            // updateSelectedValues();
        }        
    });

    // Click event handler for <p> tags inside #selectedValues
    $('#selectedValues').on('click', 'p', function() {
        var valueToRemove = $(this).text().trim(); // Get the text of the <p> tag and remove leading/trailing whitespace
        $('input[type="checkbox"][value="' + valueToRemove + '"]').prop('checked', false); // Uncheck the corresponding checkbox
        $('.color-swatch.selected[value="' + valueToRemove + '"]').removeClass('selected');
        $('#wc_search_product').val('');
        
        // Check if the clicked value is a price range
        var isPriceRange = valueToRemove.match(/^₹\d+\s-\s₹\d+$/); 
        if (isPriceRange) {
            // Clear input fields for price range
            $('.input-min').val('');
            $('.input-max').val('');
            
            // Remove the price range from selectedValues div
            $(this).remove(); 
            
            // Reset the price slider and range display
            resetPriceSlider();
        } else {
            // Remove min and max price values from selectedValues
            var minPrice = valueToRemove.match(/^₹(\d+)/);
            if (minPrice) {
                $('.input-min').val('');
            }
            
            var maxPrice = valueToRemove.match(/- ₹(\d+)$/); 
            if (maxPrice) {
                $('.input-max').val(''); 
            }
        }
        
       if (filterApplied) {
            filterProduct();
            updateSelectedValues();
        }
    });

    // Function to update selected values in the div
    function updateSelectedValues() {
        var selectedValues = []; // Array to store selected values

        // Loop through each checkbox
        $('input[type="checkbox"]:checked').each(function() {
            selectedValues.push($(this).val()); 
        });

        $('.color-swatch.selected').each(function(){
            selectedValues.push($(this).attr('value'));
        });

        // Push the search input value to the array
        var searchInputValue = $('#wc_search_product').val().trim();
        if (searchInputValue !== '') {
            selectedValues.push(searchInputValue);
        }

        /// Check if price range is selected
        var minPrice = parseInt($('.input-min').val());
        var maxPrice = parseInt($('.input-max').val());
        if (minPrice !== parseInt(wc_filter.min_price) || maxPrice !== parseInt(wc_filter.max_price)) {
            selectedValues.push('₹' + minPrice + ' - ₹' + maxPrice);
        }

        // Update the content of the div with selected values wrapped in <p> tags
        var selectedValuesHtml = selectedValues.map(value => '<p>' + value + '</p>').join(''); 
        if (selectedValues.length > 0) {
            $('#selectedValues').html(selectedValuesHtml);
            $('#selectedValues').show();
        } else {
            $('#selectedValues').hide(); 
        }
    }

    function resetPriceSlider() {
        var minPrice = parseInt(wc_filter.min_price);
        var maxPrice = parseInt(wc_filter.max_price);
        $('#price-slider').slider('values', [minPrice, maxPrice]);
        $('#price-range').text('₹' + minPrice + ' - ₹' + maxPrice);
    }

    // Selected Check Box Reset button click
    $('#wc_reset-button').click(function() {
        $('.list-pro #wc_shop-filters input[type="checkbox"]').prop('checked', false);
        $('.color-swatch').removeClass('selected');
        $('#wc_search_product').val('');
        
       if (filterApplied) {
            resetPriceSlider();
            updateSelectedValues();
            filterProduct();
        }
    });

   if (filterApplied) {
        filterProduct();
    }

    // Initial load
    filterApplied = true; // Update filter status
}); 
