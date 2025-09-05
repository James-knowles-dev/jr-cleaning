
$(document).ready(function(){
  $('.owl-carousel').slick({
    slidesToShow: 1,           
    vertical: false,              
    arrows: true,               
    dots: false,        
    autoplay: true,    
    adaptiveHeight: true,  
    prevArrow: '<button class="slick-prev"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="12" viewBox="0 0 20 12" fill="none"><path d="M20 10.1117L18.225 11.8867L10 3.66172L1.775 11.8867L0 10.1117L10 0.111718L20 10.1117Z" fill="#151820"/></svg></button>', // Custom previous button
    nextArrow: '<button class="slick-next"><svg xmlns="http://www.w3.org/2000/svg" width="20" height="12" viewBox="0 0 20 12" fill="none"><path d="M20 1.88828L18.225 0.113281L10 8.33828L1.775 0.113281L0 1.88828L10 11.8883L20 1.88828Z" fill="#151820"/></svg></button>', // Custom next button     
  });
});