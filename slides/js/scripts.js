var Slides = {
   totalSlides : '',

   slideWidth : '',

   translateAmount : 0,

   currentSlide : 0,

   container : $('#slides'),
   
   init : function(totalSlides) {
      if ( !totalSlides ) throw new Error('Please pass the total number of slides');
      Slides.totalSlides = ~~totalSlides;

      Slides.loadContent();
      Slides.setSlideWidth(); 
      Slides.keyPress();

      if ( /#slide-\d{1,3}/i.test( location.hash ) ) { 
         Slides.currentSlide = ~~location.hash.split('-')[1];
         Slides.goto();
      }

   },

   loadContent : function() {
      Slides.container.hide();
      for ( var i = 0; i < Slides.totalSlides; i++ ) {
         $('<div id="#slide-"' + i + '"></div>')
            .load('slides/' + i + '.html')
            .appendTo( Slides.container );
      }              
      Slides.container.show();
   },

   setSlideWidth : function() {
      var each = Slides.container.children('div');
      Slides.slideWidth = each.width() + ( parseInt( each.css('margin-right'), 10 ) );
   },

   keyPress : function() {
      $( document.body ).keydown(function(e) {
         if ( e.keyCode === 37 || e.keyCode === 39 ) {
            e.preventDefault();
            ( e.keyCode === 39 ) ? Slides.next() : Slides.prev();
         }
      });
   },

   next : function() {
      if ( Slides.currentSlide >= Slides.totalSlides - 1 ) return;
      Slides.translateAmount -= Slides.slideWidth;
      ++Slides.currentSlide;
      Slides.updateHash();
      Slides.animate();
   },

   prev : function() {
      if ( Slides.currentSlide <= 0 ) return;

      Slides.translateAmount += Slides.slideWidth;
      --Slides.currentSlide;
      Slides.updateHash();
      Slides.animate();
   }, 

   goto : function(  ) {
      Slides.translateAmount = -Slides.slideWidth * Slides.currentSlide;  
      Slides.animate();
   },

   animate : function() {
      Slides 
         .container
         .children()
            .css('-webkit-transform', 'translateX(' + Slides.translateAmount + 'px)');
   },

   updateHash : function() {
      location.hash = '#slide-' + Slides.currentSlide;
   }
};

// Let's do this!
// Pass the total number of slides as an argument.
Slides.init(7);