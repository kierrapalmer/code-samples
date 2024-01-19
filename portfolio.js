$(document).ready(function() {
    
	//Carousels 
    var owl = $(".gallery-carousel").owlCarousel({
        loop: true,
        responsive:{
            0:{
                items:2,
            },
            768:{
                items:3,
            },
        },
        stagePadding: isBreakpoint('sm') ? -60 : 0,
        nav:false,
        margin: 20,
    });
    
    var portfolioCarousel = $(".portfolio-carousel");
    var prevArrow = $('.custom-owl-nav .prev');
    var nextArrow = $('.custom-owl-nav .next');
    initPortfolioCarousel();    
    
    function initPortfolioCarousel(){
        //content of each item to carousel
        $(".grid-item .slider-item.active").each(function(i){
            $('.portfolio-carousel').append(this.outerHTML);
        })
        portfolioCarousel = $(".portfolio-carousel").owlCarousel({
            loop: true,
            items: 1,
            // stagePadding: isBreakpoint('sm') ? -60 : 0,
            nav:true,
            navText: [prevArrow, nextArrow],
            margin: 20,
        });
    }
    
    //Portfolio
    $(document).on('click', '.grid-item', function(){
        var item = $(this).data('item');
        var index = $('.grid-item:visible').index($(this));
        portfolioCarousel.trigger('to.owl.carousel', [index, 300]);
        $('#portfolio-modal').modal('show');

    });
    
    var maxGridRows= 6;
    var gridGap=20;
    var masonryGrid = $('.masonry-grid');
    var gridCellWidth= $('.grid-placeholder').width();    
    var currentHeight = masonryGrid.height();
    var itemsPerPage = 8;
    
    if(masonryGrid.length > 0){
        masonryGrid.find('.grid-item:not(.size-wide)').width(gridCellWidth);
        masonryGrid.isotope({
            itemSelector: '.grid-item',
            percentPosition: true,
            initLayout: false,
            masonry: {
                columnWidth: gridCellWidth,
                gutter: gridGap,
                horizontalOrder: true
            }
        })
        $(document).on( 'arrangeComplete', masonryGrid, function( event, filteredItems ) {
            if($('.dropdown-toggle.filtered').length == 0){
                $('.grid-item').addClass('d-none');
                $('.grid-item.d-none').slice(0,itemsPerPage).removeClass('d-none');
                masonryGrid.isotope('layout');
                $('.portfolio .more').show();
            } else{
                $('.portfolio .more').hide();
            }
            Waypoint.refreshAll();

        });
        masonryGrid.isotope();
    }
    
    $(document).on('click', '.portfolio .more', function(){
        $('.grid-item.d-none').slice(0,itemsPerPage).removeClass('d-none');
        masonryGrid.isotope('layout');
        if($('.grid-item.d-none').length < 1){
            $(this).hide();
        }
    });
 
 
 
 
     //filter
     if(masonryGrid.length > 0){
         var filterVar = get_url_var('filter') || get_url_var('category');
         if(filterVar){
             var filterTitle = $('.project-filter .dropdown-item[data-category="' + filterVar + '"]').text();
             filterProjects(filterVar, filterTitle)
         }
     }
     
     $(document).on('click', '.project-filter .dropdown-item', function(){
         $('.grid-item').removeClass('d-none');
         filterProjects($(this).data('category'), $(this).text());        
     });
     
     function filterProjects(categorySlug, categoryName){
         $('.project-filter .dropdown-toggle').text(categoryName);
         if(categorySlug != '*'){
             $('.project-filter .dropdown-toggle').addClass('filtered');
             categorySlug = '.' + categorySlug;
         } else{
             $('.project-filter .dropdown-toggle').removeClass('filtered');

         }
         
     
         masonryGrid.isotope({ filter: categorySlug });
         
         //Show error message
         var elems = masonryGrid.isotope('getFilteredItemElements'); 
         if ( !elems.length ) { 
             $('.portfolio .no-projects').show();
         } else { 
             $('.portfolio .no-projects').hide();
         } 
         
        
        $('.grid-item .slider-item').addClass('active');
        $('.grid-item:not('+categorySlug + ') .slider-item').removeClass('active');
        $(".portfolio-carousel").empty();
         $(".portfolio-carousel").trigger('destroy.owl.carousel').removeClass('owl-loaded');
         $(".portfolio-carousel").find('.owl-stage-outer').children().unwrap();
         initPortfolioCarousel()
         
     }
	 
});