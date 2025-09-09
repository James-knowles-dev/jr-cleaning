$(document).ready(function () {
	// Add class to body when page is scrolled
	$(window).on('scroll', function() {
		if ($(window).scrollTop() > 0) {
			$('body').addClass('scrolled');
		} else {
			$('body').removeClass('scrolled');
		}
	});

	var hamburger_open = false;
	$('.hamburger').click(function (event) {
		$('.mobile-navigation').toggleClass('is-open');
		$('body').toggleClass('menu-open');
		if (hamburger_open == false) {
			hamburger_open = true;
			disableScroll();
		} else {
			hamburger_open = false;
			enableScroll();
		}
	});

	$('.mobile-navigation .mobile-navigation__backdrop').click(function () {
		$('.mobile-navigation').removeClass('is-open');
		$('body').removeClass('menu-open');
		hamburger_open = false;
		enableScroll();
	});

	var clickable = $('.mobile-navigation__state').attr('data-clickable');
	$('.mobile-navigation li:has(ul)').addClass('has-sub');
	$('.mobile-navigation .has-sub>a').after('<em class="mobile-navigation__caret">');
	if (clickable == 'true') {
		$('.mobile-navigation .has-sub>.mobile-navigation__caret').addClass('trigger-caret');
	} else {
		$('.mobile-navigation .has-sub>a').addClass('trigger-caret').attr('href', 'javascript:;');
	}

	/* menu open and close on single click */
	$('.mobile-navigation .has-sub>.trigger-caret').click(function () {
		var element = $(this).parent('li');
		if (element.hasClass('is-open')) {
			element.removeClass('is-open');
			element.find('li').removeClass('is-open');
			element.find('ul').slideUp(200);
		}
		else {
			element.addClass('is-open');
			element.children('ul').slideDown(200);
			element.siblings('li').children('ul').slideUp(200);
			element.siblings('li').removeClass('is-open');
			element.siblings('li').find('li').removeClass('is-open');
			element.siblings('li').find('ul').slideUp(200);
		}
	});

	/* function for disable scroll when menu is opne */
	function disableScroll() {
		// Get the current page scroll position
		scrollTop = window.pageYOffset || document.documentElement.scrollTop;
		scrollLeft = window.pageXOffset || document.documentElement.scrollLeft,

			// if any scroll is attempted, set this to the previous value
			window.onscroll = function () {
				window.scrollTo(scrollLeft, scrollTop);
			};
	}
	/* function for enable scroll when menu is close */
	function enableScroll() {
		window.onscroll = function () { };
	}

});