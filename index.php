<?php
// Redirect to installer if not yet installed
if (!file_exists(__DIR__ . '/config/installed.lock') && file_exists(__DIR__ . '/install.php')) {
    header('Location: /install.php');
    exit;
}

require_once 'config/db.php';

// Fetch all data from database
try {
    $hero = $pdo->query("SELECT * FROM hero LIMIT 1")->fetch();
    $about = $pdo->query("SELECT * FROM about LIMIT 1")->fetch();
    $values = $pdo->query("SELECT * FROM values_section ORDER BY sort_order ASC")->fetchAll();
    $stats = $pdo->query("SELECT * FROM stats ORDER BY sort_order ASC")->fetchAll();
    $feature = $pdo->query("SELECT * FROM features LIMIT 1")->fetch();
    $feature_items = $pdo->query("SELECT * FROM feature_items ORDER BY sort_order ASC")->fetchAll();
    $services = $pdo->query("SELECT * FROM services ORDER BY sort_order ASC")->fetchAll();
    $pricing_plans = $pdo->query("SELECT * FROM pricing ORDER BY sort_order ASC")->fetchAll();
    $faqs = $pdo->query("SELECT * FROM faq ORDER BY sort_order ASC")->fetchAll();
    $portfolio_items = $pdo->query("SELECT * FROM portfolio ORDER BY sort_order ASC")->fetchAll();
    $testimonials = $pdo->query("SELECT * FROM testimonials ORDER BY sort_order ASC")->fetchAll();
    $team_members = $pdo->query("SELECT * FROM team ORDER BY sort_order ASC")->fetchAll();
    $clients = $pdo->query("SELECT * FROM clients ORDER BY sort_order ASC")->fetchAll();
    
    // Site settings
    $settings_rows = $pdo->query("SELECT setting_key, setting_value FROM site_settings")->fetchAll();
    $settings = [];
    foreach ($settings_rows as $row) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    
    // Pricing items grouped by pricing_id
    $pricing_items_rows = $pdo->query("SELECT * FROM pricing_items ORDER BY pricing_id, sort_order ASC")->fetchAll();
    $pricing_items = [];
    foreach ($pricing_items_rows as $item) {
        $pricing_items[$item['pricing_id']][] = $item;
    }
} catch (PDOException $e) {
    die("Error loading page data.");
}

function h($str) {
    return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}

$site_title = $settings['site_title'] ?? 'Softandpix';
$meta_description = $settings['meta_description'] ?? '';
$meta_keywords = $settings['meta_keywords'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8">
  <meta content="width=device-width, initial-scale=1.0" name="viewport">
  <title><?php echo h($site_title); ?></title>
  <meta content="<?php echo h($meta_description); ?>" name="description">
  <meta content="<?php echo h($meta_keywords); ?>" name="keywords">

  <!-- Favicons -->
  <link href="assets/img/favicon.png" rel="icon">
  <link href="assets/img/apple-touch-icon.png" rel="apple-touch-icon">

  <!-- Google Fonts -->
  <link href="https://fonts.googleapis.com/css?family=Open+Sans:300,300i,400,400i,600,600i,700,700i|Nunito:300,300i,400,400i,600,600i,700,700i|Poppins:300,300i,400,400i,500,500i,600,600i,700,700i" rel="stylesheet">

  <!-- Vendor CSS Files -->
  <link href="assets/vendor/bootstrap/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/vendor/bootstrap-icons/bootstrap-icons.css" rel="stylesheet">
  <link href="assets/vendor/aos/aos.css" rel="stylesheet">
  <link href="assets/vendor/remixicon/remixicon.css" rel="stylesheet">
  <link href="assets/vendor/swiper/swiper-bundle.min.css" rel="stylesheet">
  <link href="assets/vendor/glightbox/css/glightbox.min.css" rel="stylesheet">

  <!-- Template Main CSS File -->
  <link href="assets/css/style.css" rel="stylesheet">

</head>

<body data-bs-spy="scroll" data-bs-target="#navbar" data-bs-offset="100">

  <!-- ======= Header ======= -->
  <header id="header" class="header fixed-top">
    <div class="container-fluid container-xl d-flex align-items-center justify-content-between">

      <a href="index.php" class="logo d-flex align-items-center">
        <img src="assets/img/SoftandPix -LOGO.png" alt="">
      </a>

      <nav id="navbar" class="navbar">
        <ul>
          <li><a class="nav-link active" href="#">Home</a></li>
          <li><a class="nav-link scrollto" href="#about">About</a></li>
          <li><a class="nav-link scrollto" href="#services">Services</a></li>
          <li><a class="nav-link scrollto" href="#portfolio">Portfolio</a></li>
          <li><a class="nav-link scrollto" href="#team">Team</a></li>
          <li><a class="nav-link scrollto" href="#contact">Contact</a></li>
          <li><a class="getstarted scrollto" href="#about">Get Started</a></li>
        </ul>
        <i class="bi bi-list mobile-nav-toggle"></i>
      </nav><!-- .navbar -->

    </div>
  </header><!-- End Header -->

  <!-- ======= Hero Section ======= -->
  <section id="hero" class="hero d-flex align-items-center">
    <div class="container">
      <div class="row">
        <div class="col-lg-7 d-flex flex-column justify-content-center">
          <h1 data-aos="fade-up"><?php echo h($hero['title'] ?? ''); ?></h1>
          <h2 data-aos="fade-up" data-aos-delay="400"><?php echo h($hero['subtitle'] ?? ''); ?></h2>
          <div data-aos="fade-up" data-aos-delay="600">
            <div class="text-center text-lg-start">
              <a href="<?php echo h($hero['btn_link'] ?? '#contact'); ?>" class="btn-get-started scrollto d-inline-flex align-items-center justify-content-center align-self-center">
                <span><?php echo h($hero['btn_text'] ?? 'Request a Quote'); ?></span>
                <i class="bi bi-arrow-right"></i>
              </a>
            </div>
          </div>
        </div>
        <div class="col-lg-5 hero-img" data-aos="zoom-out" data-aos-delay="200">
          <img src="<?php echo h($hero['hero_image'] ?? 'assets/img/hero-img.png'); ?>" class="img-fluid" alt="">
        </div>
      </div>
    </div>
  </section><!-- End Hero -->

  <main id="main">

    <!-- ======= About Section ======= -->
    <section id="about" class="about">
      <div class="container" data-aos="fade-up">
        <div class="row gx-0">
          <div class="col-lg-6 d-flex flex-column justify-content-center" data-aos="fade-up" data-aos-delay="200">
            <div class="content">
              <h3><?php echo h($about['tag'] ?? ''); ?></h3>
              <h2><?php echo h($about['title'] ?? ''); ?></h2>
              <p><?php echo h($about['desc1'] ?? ''); ?></p>
              <p><?php echo h($about['desc2'] ?? ''); ?></p>
              <div class="text-center text-lg-start">
                <a href="#contact" class="btn-read-more d-inline-flex align-items-center justify-content-center align-self-center">
                  <span><?php echo h($about['btn_text'] ?? 'Read More'); ?></span>
                  <i class="bi bi-arrow-right"></i>
                </a>
              </div>
            </div>
          </div>
          <div class="col-lg-6 d-flex align-items-center" data-aos="zoom-out" data-aos-delay="200">
            <img src="<?php echo h($about['about_image'] ?? 'assets/img/about.jpg'); ?>" class="img-fluid" alt="">
          </div>
        </div>
      </div>
    </section><!-- End About Section -->

    <!-- ======= Values Section ======= -->
    <section id="values" class="values">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Our Strength</h2>
          <p>Why Hire Our Resources</p>
        </header>
        <div class="row">
          <?php foreach ($values as $index => $val): ?>
          <div class="col-lg-4 <?php echo $index > 0 ? 'mt-4 mt-lg-0' : ''; ?>">
            <div class="box" data-aos="fade-up" data-aos-delay="<?php echo (($index + 1) * 200); ?>">
              <img src="<?php echo h($val['image']); ?>" class="img-fluid" alt="">
              <h3><?php echo h($val['title']); ?></h3>
              <p><?php echo h($val['description']); ?></p>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section><!-- End Values Section -->

    <!-- ======= Counts Section ======= -->
    <section id="counts" class="counts">
      <div class="container" data-aos="fade-up">
        <div class="row gy-4">
          <?php foreach ($stats as $stat): ?>
          <div class="col-lg-3 col-md-6">
            <div class="count-box">
              <i class="<?php echo h($stat['icon']); ?>"<?php if (!empty($stat['icon_color'])): ?> style="color: <?php echo h($stat['icon_color']); ?>;"<?php endif; ?>></i>
              <div>
                <span data-purecounter-start="0" data-purecounter-end="<?php echo (int)$stat['count_end']; ?>" data-purecounter-duration="1" class="purecounter"></span>
                <p><?php echo h($stat['label']); ?></p>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section><!-- End Counts Section -->

    <!-- ======= Features Section ======= -->
    <section id="features" class="features">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Features</h2>
          <p>Our Core Values</p>
        </header>
        <div class="row">
          <div class="col-lg-6">
            <img src="<?php echo h($feature['features_image'] ?? 'assets/img/features.png'); ?>" class="img-fluid" alt="">
          </div>
          <div class="col-lg-6 mt-5 mt-lg-0 d-flex">
            <div class="row align-self-center gy-4">
              <?php $delay = 200; foreach ($feature_items as $fi): ?>
              <div class="col-md-6" data-aos="zoom-out" data-aos-delay="<?php echo $delay; ?>">
                <div class="feature-box d-flex align-items-center">
                  <i class="bi bi-check"></i>
                  <h3><?php echo h($fi['title']); ?></h3>
                </div>
              </div>
              <?php $delay += 100; endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section><!-- End Features Section -->

    <!-- ======= Services Section ======= -->
    <section id="services" class="services">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Services</h2>
          <p>Hire Professionals For Your Requirement</p>
        </header>
        <div class="row gy-4">
          <?php $delay = 200; foreach ($services as $svc): ?>
          <div class="col-lg-3 col-md-6" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
            <div class="service-box <?php echo h($svc['color_class']); ?>">
              <i class="<?php echo h($svc['icon']); ?> icon"></i>
              <h3><?php echo h($svc['title']); ?></h3>
              <p><?php echo h($svc['description']); ?></p>
              <a href="#contact" class="read-more"><span>Get Hired</span> <i class="bi bi-arrow-right"></i></a>
            </div>
          </div>
          <?php $delay = ($delay >= 700) ? 200 : $delay + 100; endforeach; ?>
        </div>
      </div>
    </section><!-- End Services Section -->

    <!-- ======= Pricing Section ======= -->
    <section id="pricing" class="pricing">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Pricing</h2>
          <p>Choose From A Variety Of Hiring Models</p>
        </header>
        <br>
        <div class="row gy-4" data-aos="fade-left">
          <?php $delay = 100; foreach ($pricing_plans as $plan): ?>
          <div class="col-lg-3 col-md-6" data-aos="zoom-in" data-aos-delay="<?php echo $delay; ?>">
            <div class="box">
              <?php if ($plan['is_featured']): ?><span class="featured">Featured</span><?php endif; ?>
              <h3 style="color: <?php echo h($plan['title_color']); ?>;"><?php echo h($plan['title']); ?></h3>
              <img src="<?php echo h($plan['image']); ?>" class="img-fluid" alt="">
              <ul>
                <?php if (!empty($pricing_items[$plan['id']])): ?>
                  <?php foreach ($pricing_items[$plan['id']] as $pi): ?>
                  <li><?php echo h($pi['item_text']); ?></li>
                  <?php endforeach; ?>
                <?php endif; ?>
              </ul>
              <a href="#contact" class="btn-buy">Buy Now</a>
            </div>
          </div>
          <?php $delay += 100; endforeach; ?>
        </div>
      </div>
    </section><!-- End Pricing Section -->

    <!-- ======= F.A.Q Section ======= -->
    <section id="faq" class="faq">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Frequently Asked Questions</h2>
          <p>FAQS on Hiring Resources</p>
        </header>
        <div class="row">
          <div class="col-lg-6">
            <div class="accordion accordion-flush" id="faqlist1">
              <?php $faq_left = array_slice($faqs, 0, ceil(count($faqs)/2)); ?>
              <?php foreach ($faq_left as $i => $faq): ?>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq-content-<?php echo $faq['id']; ?>">
                    <?php echo h($faq['question']); ?>
                  </button>
                </h2>
                <div id="faq-content-<?php echo $faq['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#faqlist1">
                  <div class="accordion-body">
                    <?php echo h($faq['answer']); ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="accordion accordion-flush" id="faqlist2">
              <?php $faq_right = array_slice($faqs, ceil(count($faqs)/2)); ?>
              <?php foreach ($faq_right as $i => $faq): ?>
              <div class="accordion-item">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#faq2-content-<?php echo $faq['id']; ?>">
                    <?php echo h($faq['question']); ?>
                  </button>
                </h2>
                <div id="faq2-content-<?php echo $faq['id']; ?>" class="accordion-collapse collapse" data-bs-parent="#faqlist2">
                  <div class="accordion-body">
                    <?php echo h($faq['answer']); ?>
                  </div>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>
      </div>
    </section><!-- End F.A.Q Section -->

    <!-- ======= Portfolio Section ======= -->
    <section id="portfolio" class="portfolio">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Portfolio</h2>
          <p>Check Our Latest Work</p>
        </header>
        <div class="row" data-aos="fade-up" data-aos-delay="100">
          <div class="col-lg-12 d-flex justify-content-center">
            <ul id="portfolio-flters">
              <li data-filter="*" class="filter-active">All</li>
              <li data-filter=".filter-app">App</li>
              <li data-filter=".filter-card">Card</li>
              <li data-filter=".filter-web">Web</li>
            </ul>
          </div>
        </div>
        <div class="row gy-4 portfolio-container" data-aos="fade-up" data-aos-delay="200">
          <?php foreach ($portfolio_items as $item): ?>
          <div class="col-lg-4 col-md-6 portfolio-item filter-<?php echo strtolower(h($item['category'])); ?>">
            <div class="portfolio-wrap">
              <img src="<?php echo h($item['image']); ?>" class="img-fluid" alt="">
              <div class="portfolio-info">
                <h4><?php echo h($item['title']); ?></h4>
                <p><?php echo h($item['category']); ?></p>
                <div class="portfolio-links">
                  <a href="<?php echo h($item['image']); ?>" data-gallery="portfolioGallery" class="portfokio-lightbox" title="<?php echo h($item['title']); ?>"><i class="bi bi-plus"></i></a>
                  <a href="<?php echo h($item['link'] ?? '#'); ?>" title="More Details"><i class="bi bi-link"></i></a>
                </div>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </section><!-- End Portfolio Section -->

    <!-- ======= Testimonials Section ======= -->
    <section id="testimonials" class="testimonials">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Testimonials</h2>
          <p>What they are saying about us</p>
        </header>
        <div class="testimonials-slider swiper-container" data-aos="fade-up" data-aos-delay="200">
          <div class="swiper-wrapper">
            <?php foreach ($testimonials as $t): ?>
            <div class="swiper-slide">
              <div class="testimonial-item">
                <div class="stars">
                  <i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i>
                </div>
                <p><?php echo h($t['message']); ?></p>
                <div class="profile mt-auto">
                  <img src="<?php echo h($t['image']); ?>" class="testimonial-img" alt="">
                  <h3><?php echo h($t['name']); ?></h3>
                  <h4><?php echo h($t['role']); ?></h4>
                </div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination"></div>
        </div>
      </div>
    </section><!-- End Testimonials Section -->

    <!-- ======= Team Section ======= -->
    <section id="team" class="team">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Team</h2>
          <p>Our hard working team</p>
        </header>
        <div class="row gy-4">
          <?php $delay = 100; foreach ($team_members as $member): ?>
          <div class="col-lg-3 col-md-6 d-flex align-items-stretch" data-aos="fade-up" data-aos-delay="<?php echo $delay; ?>">
            <div class="member">
              <div class="member-img">
                <img src="<?php echo h($member['image']); ?>" class="img-fluid" alt="">
                <div class="social">
                  <a href="<?php echo h($member['twitter'] ?? ''); ?>"><i class="bi bi-twitter"></i></a>
                  <a href="<?php echo h($member['facebook'] ?? ''); ?>"><i class="bi bi-facebook"></i></a>
                  <a href="<?php echo h($member['instagram'] ?? ''); ?>"><i class="bi bi-instagram"></i></a>
                  <a href="<?php echo h($member['linkedin'] ?? ''); ?>"><i class="bi bi-linkedin"></i></a>
                </div>
              </div>
              <div class="member-info">
                <h4><?php echo h($member['name']); ?></h4>
                <span><?php echo h($member['role']); ?></span>
                <p><?php echo h($member['bio']); ?></p>
              </div>
            </div>
          </div>
          <?php $delay += 100; endforeach; ?>
        </div>
      </div>
    </section><!-- End Team Section -->

    <!-- ======= Clients Section ======= -->
    <section id="clients" class="clients">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>Our Clients</h2>
          <p>Temporibus omnis officia</p>
        </header>
        <div class="clients-slider swiper-container">
          <div class="swiper-wrapper align-items-center">
            <?php foreach ($clients as $client): ?>
            <div class="swiper-slide"><img src="<?php echo h($client['logo']); ?>" class="img-fluid" alt="<?php echo h($client['name']); ?>"></div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination"></div>
        </div>
      </div>
    </section><!-- End Clients Section -->

    <!-- ======= Contact Section ======= -->
    <section id="contact" class="contact">
      <div class="container" data-aos="fade-up">
        <header class="section-header">
          <h2>To Get Hired</h2>
          <p>Contact Us</p>
        </header>
        <div class="row gy-4">
          <div class="col-lg-6">
            <div class="row gy-4">
              <div class="col-md-6">
                <div class="info-box">
                  <i class="bi bi-geo-alt"></i>
                  <h3>Address</h3>
                  <?php echo nl2br(h($settings['address'] ?? 'Canada Office: 3770 Westwinds Drive NE, Calgary, AB, T3J 5H3')); ?>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-box">
                  <i class="bi bi-telephone"></i>
                  <h3>Call Us</h3>
                  <p><?php echo h($settings['phone'] ?? '#403 805 6999'); ?></p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-box">
                  <i class="bi bi-envelope"></i>
                  <h3>Email Us</h3>
                  <p><?php echo h($settings['email'] ?? 'info@softandpix.com'); ?></p>
                </div>
              </div>
              <div class="col-md-6">
                <div class="info-box">
                  <i class="bi bi-clock"></i>
                  <h3>Open Hours</h3>
                  <p><?php echo nl2br(h($settings['open_hours'] ?? 'Monday - Friday\nOpen 24 hours')); ?></p>
                </div>
              </div>
            </div>
          </div>
          <div class="col-lg-6">
            <form id="contact-form" class="php-email-form">
              <div class="row gy-4">
                <div class="col-md-6">
                  <input type="text" name="name" class="form-control" placeholder="Your Name" required>
                </div>
                <div class="col-md-6">
                  <input type="email" class="form-control" name="email" placeholder="Your Email" required>
                </div>
                <div class="col-md-12">
                  <input type="text" class="form-control" name="subject" placeholder="Subject" required>
                </div>
                <div class="col-md-12">
                  <textarea class="form-control" name="message" rows="6" placeholder="Message" required></textarea>
                </div>
                <div class="col-md-12 text-center">
                  <div class="loading d-none">Loading</div>
                  <div class="error-message d-none"></div>
                  <div class="sent-message d-none">Your message has been sent. Thank you!</div>
                  <button type="submit">Send Message</button>
                </div>
              </div>
            </form>
          </div>
        </div>
      </div>
    </section><!-- End Contact Section -->

  </main><!-- End #main -->

  <!-- ======= Footer ======= -->
  <footer id="footer" class="footer">

    <div class="footer-newsletter">
      <div class="container">
        <div class="row justify-content-center">
          <div class="col-lg-12 text-center">
            <h4><?php echo h($settings['newsletter_title'] ?? 'Our Newsletter'); ?></h4>
            <p><?php echo h($settings['newsletter_description'] ?? 'Subscribe to our newsletter for the latest updates'); ?></p>
          </div>
          <div class="col-lg-6">
            <form id="newsletter-form">
              <input type="email" name="email" placeholder="Your email address" required>
              <input type="submit" value="Subscribe">
            </form>
          </div>
        </div>
      </div>
    </div>

    <div class="footer-top">
      <div class="container">
        <div class="row gy-4">
          <div class="col-lg-5 col-md-12 footer-info">
            <a href="index.php" class="logo d-flex align-items-center">
              <img src="assets/img/SoftandPix -LOGO.png" alt="">
            </a>
            <p>We employ resources and our focus on just to ensure highest quality assurance and maintain quality of product developement and other services.</p>
            <div class="social-links mt-3">
              <a href="<?php echo h($settings['twitter_url'] ?? '#'); ?>" class="twitter"><i class="bi bi-twitter"></i></a>
              <a href="<?php echo h($settings['facebook_url'] ?? '#'); ?>" class="facebook"><i class="bi bi-facebook"></i></a>
              <a href="<?php echo h($settings['instagram_url'] ?? '#'); ?>" class="instagram"><i class="bi bi-instagram bx bxl-instagram"></i></a>
              <a href="<?php echo h($settings['linkedin_url'] ?? '#'); ?>" class="linkedin"><i class="bi bi-linkedin bx bxl-linkedin"></i></a>
            </div>
          </div>
          <div class="col-lg-2 col-6 footer-links">
            <h4>Useful Links</h4>
            <ul>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Home</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">About us</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Services</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Terms of service</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Privacy policy</a></li>
            </ul>
          </div>
          <div class="col-lg-2 col-6 footer-links">
            <h4>Our Services</h4>
            <ul>
              <li><i class="bi bi-chevron-right"></i> <a href="#">IT Professionals</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Frontend Developers</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Backend Developers</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Creative Designers</a></li>
              <li><i class="bi bi-chevron-right"></i> <a href="#">Business Analysts</a></li>
            </ul>
          </div>
          <div class="col-lg-3 col-md-12 footer-contact text-center text-md-start">
            <h4>Contact Us</h4>
            <p>
              <?php echo nl2br(h($settings['address'] ?? 'Canada Office: 3770 Westwinds Drive NE, Calgary, AB, T3J 5H3')); ?><br>
              <strong>Phone:</strong> <?php echo h($settings['phone'] ?? '#403 805 6999'); ?><br>
              <strong>Email:</strong> <?php echo h($settings['email'] ?? 'info@softandpix.com'); ?><br>
            </p>
          </div>
        </div>
      </div>
    </div>

    <div class="container">
      <div class="copyright">
        <?php echo h($settings['footer_copyright'] ?? '&copy; Copyright Softandpix. All Rights Reserved'); ?>
      </div>
      <div class="credits">
        Powered by <a href="#"><?php echo h($settings['footer_powered_by'] ?? 'Softandpix'); ?></a>
      </div>
    </div>
  </footer><!-- End Footer -->

  <a href="#" class="back-to-top d-flex align-items-center justify-content-center"><i class="bi bi-arrow-up-short"></i></a>

  <!-- Vendor JS Files -->
  <script src="assets/vendor/bootstrap/js/bootstrap.bundle.js"></script>
  <script src="assets/vendor/aos/aos.js"></script>
  <script src="assets/vendor/swiper/swiper-bundle.min.js"></script>
  <script src="assets/vendor/purecounter/purecounter.js"></script>
  <script src="assets/vendor/isotope-layout/isotope.pkgd.min.js"></script>
  <script src="assets/vendor/glightbox/js/glightbox.min.js"></script>

  <!-- Template Main JS File -->
  <script src="assets/js/main.js"></script>

  <script>
  // Contact form AJAX
  document.getElementById('contact-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var loading = form.querySelector('.loading');
    var errorMsg = form.querySelector('.error-message');
    var sentMsg = form.querySelector('.sent-message');
    
    loading.classList.remove('d-none');
    errorMsg.classList.add('d-none');
    sentMsg.classList.add('d-none');
    
    var formData = new FormData(form);
    
    fetch('api/contact.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      loading.classList.add('d-none');
      if (data.success) {
        sentMsg.classList.remove('d-none');
        form.reset();
      } else {
        errorMsg.textContent = data.message;
        errorMsg.classList.remove('d-none');
      }
    })
    .catch(error => {
      loading.classList.add('d-none');
      errorMsg.textContent = 'An error occurred. Please try again.';
      errorMsg.classList.remove('d-none');
    });
  });

  // Newsletter form AJAX
  document.getElementById('newsletter-form').addEventListener('submit', function(e) {
    e.preventDefault();
    var form = this;
    var formData = new FormData(form);
    
    fetch('api/newsletter.php', {
      method: 'POST',
      body: formData
    })
    .then(response => response.json())
    .then(data => {
      if (data.success) {
        alert(data.message);
        form.reset();
      } else {
        alert(data.message);
      }
    })
    .catch(error => {
      alert('An error occurred. Please try again.');
    });
  });
  </script>

</body>
</html>
