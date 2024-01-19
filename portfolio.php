<?php 
/*
Template Name: Portfolio
*/ 
get_header();
$args = array(
   'taxonomy' => 'selah_projects_tax',
   'orderby' => 'name',
   'order'   => 'ASC'
);

$project_categories = get_categories($args);
$post_args = array(
    'post_type' => 'selah_projects',
    'post_status' => 'publish',
    'orderby'   => 'date',
    'order' => 'desc',
    'posts_per_page' => -1,
);
$param_project_slug = '';
$param_filter = '';

if(isset($_GET['project'])){
    $post_args['post_name__in'] = array($_GET['project']);
    $param_project_slug = $_GET['project'];
}

if(isset($_GET['filter']) || isset($_GET['category'])){
   $param_filter = $_GET['filter'] ?? $_GET['category'];
}
$projects_query = new WP_Query( $post_args );


?>

<main class="portfolio mt-250 mb-120 mb-lg-200">
    <div class="container mb-50 mb-lg-120 animate-up">
        <div class="row">
            <div class="col-12 col-lg-8 offset-lg-1">
                <h1><?php the_field('page_header') ?></h1>
            </div>
        </div>
    </div>
    <div class="container animate-up">
        <div class="row">
            <div class="col-12 col-lg-4 text-right text-md-left">
                <h2 class="portfolio-section-header text-body-color mb-20 mb-md-60"><?php the_field('portfolio_section_header') ?></h2>
                <?php if(empty($param_project_slug)): ?>
                <div class="dropdown project-filter  mb-50">
                    <a class="dropdown-toggle text-left text-capitalize mb-10 <?php echo !empty($param_filter) ? 'filtered' : '' ?>" type="button" id="filter" data-toggle="dropdown" data-display="static" aria-haspopup="true" aria-expanded="false">
                        <?php echo !empty($param_filter) ? $param_filter : get_field('filter_dropdown_label');  ?>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right text-right" aria-labelledby="filter">

                        <a class="dropdown-item unlink" href="" data-category="*">All Styles</a>
                        <?php foreach($project_categories as $cat): ?>
                        <a class="dropdown-item unlink" href="" data-category="<?php echo $cat->slug ?>"><?php echo $cat->name ?></a>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </div>

            <div class="col-12 col-lg-8">
                <div class="grid-placeholder"></div>
                <div class="no-projects" style="display:none">No matching projects. Please select another style.</div>
                <div class="masonry-grid">
                    <!-- https://codepen.io/iamsaief/pen/jObaoKo -->
                    <?php
                    $count = 1;
                    if ($projects_query->have_posts()): while ($projects_query->have_posts()): $projects_query->the_post();
                           $preview_image = get_field('preview_image');
                           $categories = get_the_terms(get_the_ID(), 'selah_projects_tax');
                           $categories_string = '';
                           if(!empty($categories)){
                              foreach($categories as $k=>$cat){
                                 $categories_string .= $cat->slug . ' ';
                              }
                           }
                           $active_class = 'active';
                           
                            ?>
                    <div class="grid-item size-<?php echo $preview_image['image_size'] . ' ' . $categories_string ?>" data-item="<?php echo $count ?>">
                        <?php if(!empty($preview_image['image'])): ?>
                        <img src="<?php echo $preview_image['image']['url'] ?>" alt="<?php echo $preview_image['image']['alt'] ?? 'project image' ?>">
                        <?php endif; ?>
                        <div class="title d-none"><?php the_title() ?></div>
                        <div class="slider-item item-<?php echo $count . ' ' . $preview_image['image_size']. ' '  . $active_class ?>">
                            <img class="mb-20" src="<?php echo $preview_image['image']['url'] ?>" alt="<?php echo $preview_image['image']['alt'] ?? 'project image' ?>">
                            <div class="row item-row no-gutters">
                                <div class="col-8 col-md-10">
                                    <h2 class="title mb-20"><?php the_title() ?></h2>
                                    <div class="description"><?php the_field('description') ?></div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php $count++ ?>

                    <?php endwhile;endif; 
                    wp_reset_postdata();
                    ?>
                </div>
                <?php if($projects_query->post_count < 1): ?>
                <?php $link = explode('?',$_SERVER['REQUEST_URI'])[0] ?? '' ?>
                <div> No Projects Found. <a href="<?php echo $link ?>">View All Projects </a></div>
                <?php endif; ?>

                <?php if($projects_query->post_count > 8): ?>
                <div class="more" data-page="1"><a class="unlink" href="#"><?php the_field('show_more_label')  ?></a></div>
                <?php endif; ?>

            </div>
        </div>

    </div>


    <div class="modal fade" id="portfolio-modal" tabindex="-1" role="dialog" aria-labelledby="portfolio-modalLabel" aria-hidden="true">
        <div class="modal-dialog" role="document">
            <div class="modal-content">
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>

                <div class="modal-body">
                    <div class="owl-carousel portfolio-carousel">
                        <?php $count = 0; ?>
                        <?php if ($projects_query->have_posts()): while ($projects_query->have_posts()): $projects_query->the_post(); ?>

                        <?php $count++ ?>
                        <?php endwhile;endif; 
                  wp_reset_postdata();?>


                    </div>

                    <div class="custom-owl-nav">
                        <div class="prev"><?php get_template_part( 'template-parts/arrow' ); ?></div>
                        <div class="next"><?php get_template_part( 'template-parts/arrow' ); ?></div>
                    </div>
                </div>
            </div>
        </div>
    </div>


    <script src="<?php echo get_template_directory_uri() . '/js/isotope.pkgd.min.js' ?>"></script>
    <script src="<?php echo get_template_directory_uri() . '/js/owlcarousel.min.js' ?>"></script>

</main>

<?php get_footer();?>
