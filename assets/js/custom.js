

(function ($) {
	"use strict";

    // Responsive Menubar Js
  $('.menu').meanmenu();
  // Sticky Menu
  $(window).on('scroll', function () {
    if ($(this).scrollTop() > 100) {
      $('.header_area').addClass('menu-shrink animated slideInDown');
    } else {
      $('.header_area').removeClass('menu-shrink animated slideInUp');
    }
  });
    $('.to-top').toTop({
      //options with default values
      autohide: true,  //boolean 'true' or 'false'
      offset: 2500,     //numeric value (as pixels) for scrolling length from top to hide automatically
      speed: 400,      //numeric value (as mili-seconds) for duration
      position:true,   //boolean 'true' or 'false'. Set this 'false' if you want to add custom position with your own css
      left: 15,       //numeric value (as pixels) for position from right. It will work only if the 'position' is set 'true'
      bottom: 40       //numeric value (as pixels) for position from bottom. It will work only if the 'position' is set 'true'
    });
  $(window).scroll(function () {
    if ($(document).scrollTop() > 2500) {
      $(".indicator_box").addClass("indicator_vn");
    } else {
      $(".indicator_box").removeClass("indicator_vn");
    }
  }); 
  $(window).scroll(function() {
    if ($(document).scrollTop() > 2500) {
      $(".down_indicator").addClass("indicator_vn");
    } else {
      $(".down_indicator").removeClass("indicator_vn");
    }
  }); 
       // Aos Animation js
   AOS.init({
    offset: 100,
    duration:1000,
  });
 
        // TYPED JS
        var typed = new Typed('.type_txt', {
          strings: ['This is a multigenerational proactive community focussed on health, wellness and recreational lifestyle.',],
          typeSpeed: 60,
          backSpeed: 100,
          backDelay:1600,
          loop:false,
          // loopCount: 1,
          showCursor: true,
          smartBackspace: false // Default value
        });

})(jQuery);
