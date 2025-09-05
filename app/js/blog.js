// Search and category filters.
$(".category-filter-dropdown").on("change", function () {
    $("#filter-blog").submit();
});

$("#search-blog").on("keyup search", function () {
    var blog_search_value = $(this).val().toLowerCase();
    $("#response-blog .post").filter(function () {
        $(this).toggle($(this).find("h3").text().toLowerCase().indexOf(blog_search_value) > -1);
        if ($("#response-blog").children(":visible").length == 0) {
            $(".no-results").addClass("visible");
        } else {
            $(".no-results").removeClass("visible");
        }
    });
});

$("#filter-blog").submit(function () {
    var filter = $("#filter-blog");
    $.ajax({
        url: filter.attr("action"),
        data: filter.serialize(),
        type: filter.attr("method"),
        success: function (data) {
            $("#response-blog").html(data);
        }
    });
    var blog_search_value = $("#search-blog").val().toLowerCase();
    $("#response-blog .post").filter(function () {
        $("#response-blog .post").toggle($("#response-blog .post").find("h3").text().toLowerCase().indexOf(blog_search_value) > -1)
    });
    return false;
});

var post_page = 1;
$(document).on('click', '.post-listing-with-pagination .category-drodown-item .btn-category', function() {
    const selectedText = $(this).text();
    const selectedDataValue = $(this).data('value');
    $('.post-listing-with-pagination .category-dropdown').text(selectedText);
    $('.post-listing-with-pagination .category-dropdown').data('value' , selectedDataValue);
    $('.post-listing-with-pagination .category-drodown-item').slideUp(); 
    $(this).parents('ul').find('.btn-category').removeClass('active');
    $(this).addClass('active');
    var selectedCategory = $(this).data('value');
    var searchQuery = $('.post-listing-with-pagination .post-search-input').val();
    updatePaginationControls(post_page);
    console.log('selectedCategory : ' + selectedCategory, 'post_page : ' + post_page, 'searchQuery : ' + searchQuery);
    post_filter( post_page , selectedCategory, searchQuery);
});

$('.post-listing-with-pagination .post-search-input').on('keyup', function () {
    var searchQuery = $(this).val();
    var selectedCategory = $('.post-listing-with-pagination .btn-category.active').data('value');
    console.log('selectedCategory : ' + selectedCategory, 'post_page : ' + post_page, 'searchQuery : ' + searchQuery);
    post_filter(post_page, selectedCategory, searchQuery );
});


$(document).on('click', '.post-listing-wrapper .pagination .dots, .post-listing-wrapper .pagination .icon-btn', function(e) {
    e.preventDefault();
    
    var newPage = $(this).data('page');

    var selectedCategory = $('.post-listing-with-pagination .btn-category.active').data('value');
    var searchQuery = $('.post-listing-with-pagination .post-search-input').val();
    // Update active state for dots
    $('.post-listing-wrapper .pagination .dots').removeClass('active');
    $('.post-listing-wrapper .pagination .dots[data-page="' + newPage + '"]').addClass('active');        
    post_page = newPage;
    updatePaginationControls(post_page);
    $('html, body').animate({
        scrollTop: $('.post-listing-wrapper').offset().top
    }, 'slow');
    // Fetch new posts
    console.log('selectedCategory : ' + selectedCategory, 'post_page : ' + post_page, 'searchQuery : ' + searchQuery);
    post_filter(post_page, selectedCategory , searchQuery);
});


function post_filter(post_page , selectedCategory, searchQuery){
    jQuery.ajax({
        type: "post",
        url: ajax_variable.ajax_url,
        data: {
            'action': "action__post_filters",
            'page': post_page,
            'category': selectedCategory,
            'search': searchQuery,
        },
        dataType: 'JSON',
        cache: false,
        success: function(res) {
            if (res.response == "success") {
                $('#post-listing').html(res.message);
                $('.post-listing-wrapper .pagination-wrapper').html(res.pagination);
                updatePaginationControls(post_page);
                // console.log(" Response page : " + post_page);
            } else {
                $('#post-listing').html('<div style="text-align: center;width: 100%;">No posts found.</div>');
                $('.post-listing-wrapper .pagination-wrapper').empty();
            }            
        }
    });
}
function updatePaginationControls(post_page) {
    var total = $('.post-listing-wrapper .pagination').data('max');
    if (post_page == 1) {
        $('.post-listing-wrapper .prev-page').addClass('disabled').css({ 'pointer-events': 'none', 'opacity': '0.2' });
    } else {
        $('.post-listing-wrapper .prev-page').removeClass('disabled').css({ 'pointer-events': 'auto', 'opacity': '1' });
    }
    if (post_page == total) {
        $('.post-listing-wrapper .next-page').addClass('disabled').css({ 'pointer-events': 'none', 'opacity': '0.2' });
    } else {
        $('.post-listing-wrapper .next-page').removeClass('disabled').css({ 'pointer-events': 'auto', 'opacity': '1' });
    }
}