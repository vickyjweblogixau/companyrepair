<?php
/**
 * CRS Theme — front-page.php
 * Template: Homepage  →  index.html prototype
 * URL: /
 */

get_header();
?>

<!-- ===================== HERO ===================== -->
<section class="crs-hero">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-lg-12">
        <div class="crs-hero-content">

          <h1>
            <?php esc_html_e( 'Find Trusted', 'crs' ); ?><br>
            <?php esc_html_e( 'Computer Repair & IT', 'crs' ); ?><br>
            <?php esc_html_e( 'Support Services', 'crs' ); ?><br>
            <?php esc_html_e( 'Australia Wide', 'crs' ); ?>
          </h1>

          <p class="crs-hero-desc">
            <?php esc_html_e( 'Connect with local experts for repairs, support and IT solutions for home and business.', 'crs' ); ?>
          </p>

          <!-- Search form -->
          <form class="crs-search-form" method="GET"
                action="<?php echo esc_url( home_url( '/services/' ) ); ?>">

            <div class="crs-search-field">
              <i class="fa-solid fa-magnifying-glass"></i>
              <input type="text" name="service"
                     placeholder="<?php esc_attr_e( 'What do you need help with?', 'crs' ); ?>">
            </div>

            <div class="crs-search-field">
              <i class="fa-solid fa-location-dot"></i>
              <input type="text" name="location"
                     placeholder="<?php esc_attr_e( 'Suburb or Postcode', 'crs' ); ?>">
            </div>

            <button type="submit" class="crs-search-btn">
              <?php esc_html_e( 'Search', 'crs' ); ?>
            </button>

          </form>

          <!-- Popular searches -->
          <div class="crs-popular-searches">
            <span><?php esc_html_e( 'Popular Searches:', 'crs' ); ?></span>
            <?php
            $popular = get_terms( [ 'taxonomy' => 'repair-service', 'hide_empty' => true, 'number' => 4 ] );
            if ( $popular && ! is_wp_error( $popular ) ) :
                foreach ( $popular as $term ) : ?>
                  <a href="<?php echo esc_url( get_term_link( $term ) ); ?>">
                    <?php echo esc_html( $term->name . ' Australia' ); ?>
                  </a>
                <?php endforeach;
            else : ?>
              <a href="#"><?php esc_html_e( 'Computer Repairs Melbourne', 'crs' ); ?></a>
              <a href="#"><?php esc_html_e( 'Laptop Repairs Sydney', 'crs' ); ?></a>
              <a href="#"><?php esc_html_e( 'Microsoft 365 Support', 'crs' ); ?></a>
              <a href="#"><?php esc_html_e( 'Virus Removal', 'crs' ); ?></a>
            <?php endif; ?>
          </div>

        </div>
      </div>
    </div>
  </div>
</section>

<!-- ===================== MAIN CONTENT ===================== -->
<main class="container px-3">

  <!-- ── TRUST BAR ───────────────────────────────────────────────── -->
  <div class="crs-trust p-3 p-md-4 my-4">
    <div class="row align-items-center g-3">

      <div class="col-lg-4 d-flex align-items-center gap-3">
        <span class="ico"><i class="fa-solid fa-shield-halved"></i></span>
        <strong style="font-size:15px;line-height:1.2;">
          <?php esc_html_e( "Australia's Trusted Directory for Computer Repair & IT Support Services", 'crs' ); ?>
        </strong>
      </div>

      <div class="col-12 col-lg d-flex align-items-center gap-2">
        <span class="ico"><i class="fa-solid fa-circle-check"></i></span>
        <span>
          <strong style="display:block;font-size:13.5px;"><?php esc_html_e( 'Verified Businesses', 'crs' ); ?></strong>
          <small class="text-muted-2"><?php esc_html_e( 'Trusted & reviewed', 'crs' ); ?></small>
        </span>
      </div>

      <div class="col-12 col-lg d-flex align-items-center gap-2">
        <span class="ico"><i class="fa-solid fa-layer-group"></i></span>
        <span>
          <strong style="display:block;font-size:13.5px;"><?php esc_html_e( 'Wide Range of Services', 'crs' ); ?></strong>
          <small class="text-muted-2"><?php esc_html_e( 'For home & business', 'crs' ); ?></small>
        </span>
      </div>

      <div class="col-12 col-lg d-flex align-items-center gap-2">
        <span class="ico"><i class="fa-solid fa-location-crosshairs"></i></span>
        <span>
          <strong style="display:block;font-size:13.5px;"><?php esc_html_e( 'Australia Wide Coverage', 'crs' ); ?></strong>
          <small class="text-muted-2"><?php esc_html_e( 'Local experts near you', 'crs' ); ?></small>
        </span>
      </div>

    </div>
  </div>

  <!-- ── SERVICES (Repair + Business IT) ─────────────────────────── -->
  <div class="row g-4 mb-4">

    <!-- Repair Services panel -->
    <div class="col-lg-6">
      <div class="crs-panel">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h2 class="crs-sec-title"><?php esc_html_e( 'Repair Services', 'crs' ); ?></h2>
            <p class="crs-sec-sub"><?php esc_html_e( 'Find help for your device', 'crs' ); ?></p>
          </div>
          <a href="<?php echo esc_url( home_url( '/services/' ) ); ?>" class="crs-viewall">
            <?php esc_html_e( 'View all', 'crs' ); ?> <i class="fa-solid fa-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row">
          <?php
          // Admin can control which services appear via ACF Options; fallback = taxonomy terms
          $repair_services = get_terms( [
              'taxonomy'   => 'repair-service',
              'hide_empty' => false,
              'number'     => 6,
          ] );
          if ( $repair_services && ! is_wp_error( $repair_services ) ) :
              foreach ( $repair_services as $term ) :
                  $icon_url = get_term_meta( $term->term_id, 'service_icon', true )
                      ?: CRS_URI . '/assets/icon/computer.png';
                  ?>
                  <div class="col-lg-4 col-6 mb-3">
                    <a href="<?php echo esc_url( get_term_link( $term ) ); ?>" class="svc-card">
                      <img src="<?php echo esc_url( $icon_url ); ?>"
                           alt="<?php echo esc_attr( $term->name ); ?>"
                           loading="lazy">
                      <h4 class="svc-name"><?php echo esc_html( $term->name ); ?></h4>
                      <p class="svc-desc"><?php echo esc_html( $term->description ?: '' ); ?></p>
                    </a>
                  </div>
                  <?php
              endforeach;
          endif;
          ?>
        </div>
      </div>
    </div>

    <!-- Business IT Services panel -->
    <div class="col-lg-6">
      <div class="crs-panel">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h2 class="crs-sec-title"><?php esc_html_e( 'Business IT Services', 'crs' ); ?></h2>
            <p class="crs-sec-sub"><?php esc_html_e( 'IT support for your business', 'crs' ); ?></p>
          </div>
          <a href="#" class="crs-viewall">
            <?php esc_html_e( 'View all', 'crs' ); ?> <i class="fa-solid fa-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row">
          <?php
          // Business IT services — admin-configured via ACF Options Page
          $biz_services = [
              [ 'icon' => 'group.png',          'name' => 'Business IT Support',   'desc' => 'All Business Needs' ],
              [ 'icon' => 'microsoft.png',       'name' => 'Microsoft 365 Support', 'desc' => 'Email & Productivity' ],
              [ 'icon' => 'wifi.png',            'name' => 'Network Support',        'desc' => 'WiFi & Connectivity' ],
              [ 'icon' => 'server.png',          'name' => 'Server Support',         'desc' => 'Servers & Infrastructure' ],
              [ 'icon' => 'manage-it-service.png','name' => 'Managed IT Services',   'desc' => 'Ongoing IT Management' ],
              [ 'icon' => 'remote-access.png',   'name' => 'Remote IT Support',      'desc' => 'Remote Assistance' ],
          ];
          foreach ( $biz_services as $svc ) : ?>
            <div class="col-lg-4 col-6 mb-3">
              <a href="#" class="svc-card">
                <img src="<?php echo esc_url( CRS_URI . '/assets/icon/' . $svc['icon'] ); ?>"
                     alt="<?php echo esc_attr( $svc['name'] ); ?>"
                     loading="lazy">
                <h4 class="svc-name"><?php echo esc_html( $svc['name'] ); ?></h4>
                <p class="svc-desc"><?php echo esc_html( $svc['desc'] ); ?></p>
              </a>
            </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

  </div><!-- /services row -->

  <!-- ── CTA BANNER ─────────────────────────────────────────────── -->
  <?php get_template_part( 'template-parts/cta-banner' ); ?>

  <!-- ── BROWSE BY BRAND + OS ───────────────────────────────────── -->
  <div class="row g-4 mb-4">

    <!-- Browse by Brand -->
    <div class="col-lg-6">
      <div class="crs-panel">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h2 class="crs-sec-title"><?php esc_html_e( 'Browse by Brand', 'crs' ); ?></h2>
            <p class="crs-sec-sub"><?php esc_html_e( 'Find specialists for your device brand', 'crs' ); ?></p>
          </div>
          <a href="#" class="crs-viewall">
            <?php esc_html_e( 'View all brands', 'crs' ); ?> <i class="fa-solid fa-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row">
          <?php
          $brands = get_terms( [ 'taxonomy' => 'device-brand', 'hide_empty' => false, 'number' => 5 ] );
          if ( $brands && ! is_wp_error( $brands ) ) :
              foreach ( $brands as $brand ) :
                  $logo_url = get_term_meta( $brand->term_id, 'brand_logo', true )
                      ?: CRS_URI . '/assets/icon/view-more.png';
                  ?>
                  <div class="col-lg-4 col-6 mb-3">
                    <a href="<?php echo esc_url( get_term_link( $brand ) ); ?>" class="chip">
                      <img src="<?php echo esc_url( $logo_url ); ?>"
                           alt="<?php echo esc_attr( $brand->name ); ?>"
                           loading="lazy">
                      <h4 class="chip-name"><?php echo esc_html( $brand->name ); ?></h4>
                    </a>
                  </div>
                  <?php
              endforeach;
          endif;
          ?>
          <div class="col-lg-4 col-6 mb-3">
            <a href="#" class="chip">
              <img src="<?php echo esc_url( CRS_URI . '/assets/icon/view-more.png' ); ?>" alt="">
              <h4 class="chip-name"><?php esc_html_e( 'View All Brands', 'crs' ); ?></h4>
            </a>
          </div>
        </div>
      </div>
    </div>

    <!-- Browse by OS -->
    <div class="col-lg-6">
      <div class="crs-panel">
        <div class="d-flex justify-content-between align-items-start mb-3">
          <div>
            <h2 class="crs-sec-title"><?php esc_html_e( 'Browse by Operating System', 'crs' ); ?></h2>
            <p class="crs-sec-sub"><?php esc_html_e( 'Find experts for your operating system', 'crs' ); ?></p>
          </div>
          <a href="#" class="crs-viewall">
            <?php esc_html_e( 'View all OS', 'crs' ); ?> <i class="fa-solid fa-arrow-right ms-1"></i>
          </a>
        </div>
        <div class="row">
          <?php
          $os_list = get_terms( [ 'taxonomy' => 'operating-system', 'hide_empty' => false ] );
          if ( $os_list && ! is_wp_error( $os_list ) ) :
              foreach ( $os_list as $os ) :
                  $icon_url = get_term_meta( $os->term_id, 'os_icon', true )
                      ?: CRS_URI . '/assets/icon/windows.png';
                  ?>
                  <div class="col-lg-4 col-6 mb-3">
                    <a href="<?php echo esc_url( get_term_link( $os ) ); ?>" class="chip">
                      <img src="<?php echo esc_url( $icon_url ); ?>"
                           alt="<?php echo esc_attr( $os->name ); ?>"
                           loading="lazy">
                      <h4 class="chip-name"><?php echo esc_html( $os->name ); ?></h4>
                    </a>
                  </div>
                  <?php
              endforeach;
          endif;
          ?>
          <div class="col-lg-4 col-6 mb-3">
            <a href="#" class="chip">
              <img src="<?php echo esc_url( CRS_URI . '/assets/icon/view-more.png' ); ?>" alt="">
              <h4 class="chip-name"><?php esc_html_e( 'View All OS', 'crs' ); ?></h4>
            </a>
          </div>
        </div>
      </div>
    </div>

  </div><!-- /brand + OS row -->

  <!-- ── SPONSORED / PREMIUM LISTINGS ──────────────────────────── -->
  <div class="mb-5">

    <div class="d-flex justify-content-between align-items-start mb-3">
      <div>
        <h2 class="crs-sec-title"><?php esc_html_e( 'Sponsored Listings', 'crs' ); ?></h2>
        <p class="crs-sec-sub"><?php esc_html_e( 'Featured computer repair and IT support businesses', 'crs' ); ?></p>
      </div>
      <a href="#" class="crs-viewall">
        <?php esc_html_e( 'View all sponsored listings', 'crs' ); ?> <i class="fa-solid fa-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="row g-3">
      <?php
      // Premium businesses — cached 1 hr
      $sponsored = new WP_Query( [
          'post_type'      => 'business',
          'post_status'    => 'publish',
          'posts_per_page' => 4,
          'orderby'        => 'rand',
          'meta_query'     => [
              [ 'key' => '_subscription_status', 'value' => 'active' ],
              [ 'key' => '_subscription_tier',   'value' => 'premium' ],
          ],
      ] );

      if ( $sponsored->have_posts() ) :
          while ( $sponsored->have_posts() ) : $sponsored->the_post();
              $post_id  = get_the_ID();
              $logo_id  = crs_get_meta( 'business_logo', $post_id );
              $avg      = crs_get_meta( 'review_avg',    $post_id );
              $count    = crs_get_meta( 'review_count',  $post_id );
              $services = get_the_terms( $post_id, 'repair-service' );
              $logo_url = $logo_id
                  ? wp_get_attachment_image_url( $logo_id, 'thumbnail' )
                  : false;
              ?>
              <div class="col-md-6 col-xl-6">
                <div class="sponsor-card">

                  <div class="sponsor-head mb-2">
                    <div class="sponsor-logo">
                      <span class="sponsor-badge"><?php esc_html_e( 'SPONSORED', 'crs' ); ?></span>
                      <?php if ( $logo_url ) : ?>
                        <img src="<?php echo esc_url( $logo_url ); ?>"
                             alt="<?php the_title_attribute(); ?>"
                             style="height:30px;object-fit:contain;">
                      <?php else : ?>
                        <?php echo esc_html( mb_strtoupper( mb_substr( get_the_title(), 0, 3 ) ) ); ?>
                      <?php endif; ?>
                    </div>
                    <div>
                      <div class="sponsor-name"><?php the_title(); ?></div>
                      <?php if ( $avg ) : ?>
                        <div class="sponsor-stars">
                          <?php crs_render_stars( $avg ); ?>
                          <span><?php echo esc_html( number_format( $avg, 1 ) ); ?>
                            (<?php echo esc_html( $count ); ?> reviews)
                          </span>
                        </div>
                      <?php endif; ?>
                    </div>
                  </div>

                  <?php if ( $services && ! is_wp_error( $services ) ) : ?>
                    <div class="row">
                      <ul class="col-6 list-unstyled sponsor-feats mb-2">
                        <?php foreach ( array_slice( $services, 0, 3 ) as $svc ) : ?>
                          <li><i class="fa-solid fa-check"></i><?php echo esc_html( $svc->name ); ?></li>
                        <?php endforeach; ?>
                      </ul>
                      <ul class="col-6 list-unstyled sponsor-feats mb-2">
                        <?php foreach ( array_slice( $services, 3, 3 ) as $svc ) : ?>
                          <li><i class="fa-solid fa-check"></i><?php echo esc_html( $svc->name ); ?></li>
                        <?php endforeach; ?>
                      </ul>
                    </div>
                  <?php endif; ?>

                  <a href="<?php the_permalink(); ?>" class="btn-visit">
                    <?php esc_html_e( 'View Profile', 'crs' ); ?>
                    <i class="fa-solid fa-arrow-up-right-from-square"></i>
                  </a>

                </div>
              </div>
              <?php
          endwhile;
          wp_reset_postdata();
      endif;
      ?>
    </div><!-- /sponsored row -->

  </div>

  <!-- ── LATEST ARTICLES (Swiper carousel) ─────────────────────── -->
  <div class="mb-5">

    <div class="d-flex justify-content-between align-items-center mb-3">
      <h2 class="crs-sec-title" style="font-size:24px;">
        <?php esc_html_e( 'Latest Articles & Guides', 'crs' ); ?>
      </h2>
      <a href="<?php echo esc_url( home_url( '/blog/' ) ); ?>" class="crs-viewall d-none d-sm-inline">
        <?php esc_html_e( 'View all articles', 'crs' ); ?>
        <i class="fa-solid fa-arrow-right ms-1"></i>
      </a>
    </div>

    <div class="position-relative">

      <button class="blog-nav-btn" id="blogPrev" aria-label="<?php esc_attr_e( 'Previous', 'crs' ); ?>">
        <i class="fa-solid fa-arrow-left"></i>
      </button>

      <div class="swiper blog-swiper">
        <div class="swiper-wrapper">
          <?php
          $blog_posts = new WP_Query( [
              'post_type'      => 'post',
              'post_status'    => 'publish',
              'posts_per_page' => 6,
              'orderby'        => 'date',
              'order'          => 'DESC',
          ] );

          if ( $blog_posts->have_posts() ) :
              while ( $blog_posts->have_posts() ) : $blog_posts->the_post(); ?>
                <div class="swiper-slide h-auto">
                  <a href="<?php the_permalink(); ?>" class="article-card">
                    <div class="ph article-thumb">
                      <?php if ( has_post_thumbnail() ) : ?>
                        <?php the_post_thumbnail( 'crs-blog-card', [ 'class' => 'img-fluid', 'loading' => 'lazy' ] ); ?>
                      <?php endif; ?>
                    </div>
                    <div class="article-body">
                      <div class="article-title"><?php the_title(); ?></div>
                      <div class="article-ex"><?php the_excerpt(); ?></div>
                      <div class="article-date"><?php echo get_the_date(); ?></div>
                    </div>
                  </a>
                </div>
              <?php endwhile;
              wp_reset_postdata();
          endif;
          ?>
        </div>
      </div>

      <button class="blog-nav-btn" id="blogNext" aria-label="<?php esc_attr_e( 'Next', 'crs' ); ?>">
        <i class="fa-solid fa-arrow-right"></i>
      </button>

    </div>

  </div>

  <!-- ── SECOND CTA BANNER ──────────────────────────────────────── -->
  <?php get_template_part( 'template-parts/cta-banner' ); ?>

</main><!-- /container -->

<?php get_footer(); ?>
