<?php

sikshya_header();

rewind_posts();

while (have_posts()) {

    the_post();


    $info = sikshya_get_course_info(get_the_ID());

    $sections = sikshya()->section->get_all_by_course(get_the_ID());

    $course_description = empty($info['description']) ? '' : $info['description'];

    $course_subject = empty($info['subject']) ? '' : $info['subject'];

    $course_level = empty($info['level']) ? '' : $info['level'];

    $course_duration = empty($info['duration']) ? 0 : (int)$info['duration'];


    $course_thumbnail_url = sikshya_get_image_url(get_the_post_thumbnail_url(null, 'sikshya_block_small'));

    $course_sections_count = count($sections);

    $course_meta = sikshya()->course->get_course_meta(get_the_ID());

    $outcomes = isset($course_meta['sikshya_course_outcomes']) ? $course_meta['sikshya_course_outcomes'] : array();
    $requirements = isset($course_meta['sikshya_course_requirements']) ? $course_meta['sikshya_course_requirements'] : array();

    $course_level = sikshya_course_levels();

    ?>
    <section class="course-header-area">
        <div class="sik-container">
            <div class="row align-items-end">
                <div class="sik-col-lg-8">
                    <div class="course-header-wrap">
                        <h1 class="title"><?php echo get_the_title() ?></h1>
                        <p class="subtitle"><?php echo get_the_excerpt() ?></p>
                        <div class="rating-row">
                            <span class="sikshya-course-level"><?php echo esc_html($course_level[$course_meta['sikshya_course_level']]); ?></span>
                            <span class="enrolled-num"> 1 Students enrolled </span>
                        </div>
                        <div class="created-row">
          <span class="created-by">
            Created by <a href="https://demo.academy-lms.com/default/home/instructor_page/1">John Doe</a>
          </span>
                            <span class="last-updated-date">Last updated Fri, 05-Jul-2019</span>
                        </div>
                    </div>
                </div>
                <div class="sik-col-lg-4">

                </div>
            </div>
        </div>
    </section>
    <section class="course-content-area">
        <div class="sik-container">
            <div class="row">
                <div class="sik-col-lg-8">
                    <?php
                    if (isset($outcomes[0]) && '' !== $outcomes[0]) {
                        ?>
                        <div class="what-you-get-box">
                            <div class="what-you-get-title"><?php echo esc_html__('What will i learn?', 'sikshya') ?></div>
                            <ul class="what-you-get__items">
                                <?php foreach ($outcomes as $outcome) { ?>
                                    <li><?php echo esc_attr($outcome); ?></li>
                                <?php } ?>

                            </ul>
                        </div>
                        <br>
                    <?php } ?>

                    <?php do_action('sikshya_course_tab_content'); ?>


                    <?php

                    if (isset($requirements[0]) && '' !== $requirements[0]) { ?>
                        <div class="requirements-box">
                            <div class="requirements-title">Requirements</div>
                            <div class="requirements-content">
                                <ul class="requirements__list">
                                    <?php foreach ($requirements as $requirement) { ?>
                                        <li><?php echo esc_html($requirement); ?></li>
                                    <?php } ?>

                                </ul>
                            </div>
                        </div>
                    <?php } ?>
                    <div class="description-box view-more-parent">
                        <div class="description-title">Description</div>
                        <div class="description-content-wrap">
                            <div class="description-content">
                                <?php the_content(); ?>
                            </div>
                        </div>
                    </div>

                    <div class="about-instructor-box">
                        <div class="about-instructor-title">
                            About the instructor
                        </div>
                        <div class="row">
                            <div class="sik-col-lg-4">
                                <div class="about-instructor-image">
                                    <img src="https://demo.academy-lms.com/default/uploads/user_image/1.jpg" alt=""
                                         class="img-fluid">
                                    <ul>
                                        <!-- <li><i class="fas fa-star"></i><b>4.4</b> Average Rating</li> -->
                                        <li><i class="fas fa-comment"></i><b>
                                                5 </b> Reviews
                                        </li>
                                        <li><i class="fas fa-user"></i><b>
                                                3 </b> Students
                                        </li>
                                        <li><i class="fas fa-play-circle"></i><b>
                                                11 </b> Courses
                                        </li>
                                    </ul>
                                </div>
                            </div>
                            <div class="sik-col-lg-8">
                                <div class="about-instructor-details view-more-parent">
                                    <div class="view-more" onclick="viewMore(this)">+ View more</div>
                                    <div class="instructor-name">
                                        <a href="https://demo.academy-lms.com/default/home/instructor_page/1">John
                                            Doe</a>
                                    </div>
                                    <div class="instructor-title">
                                        Eat Sleep Code Repeat
                                    </div>
                                    <div class="instructor-bio">
                                        <p>
                                            <img src="https://i.pinimg.com/originals/14/79/3b/14793ba10a4c87eabaa80c731b87dcda.jpg"
                                                 xss="removed"><b><u><span lang="ru" xml:lang="ru"
                                                                           xss="removed"><br></span></u></b></p>
                                        <p><b><u><span lang="ru" xml:lang="ru" xss="removed">Лорем ипсум долор сит амет, пер цлита поссит ех, ат мунере фабулас петентиум сит. Иус цу цибо саперет сцрипсерит, нец виси муциус лабитур ид. Ет хис нонумес нолуиссе дигниссим.</span><span
                                                            xss="removed">&nbsp;</span></u></b></p>
                                        <p><b xss="removed">Chinese Lorem Ipsum</b><span
                                                    xss="removed">:&nbsp;</span><span lang="zh" xml:lang="zh"
                                                                                      xss="removed">側経意責家方家閉討店暖育田庁載社転線宇。得君新術治温抗添代話考振投員殴大闘北裁。品間識部案代学凰処済準世一戸刻法分。悼測済諏計飯利安凶断理資沢同岩面文認革。内警格化再薬方久化体教御決数詭芸得筆代。</span>
                                        </p>
                                        <p><b xss="removed">Indian Lorem Ipsum</b><span
                                                    xss="removed">:&nbsp;</span><span lang="hi" xml:lang="hi"
                                                                                      xss="removed">पढाए हिंदी रहारुप अनुवाद कार्यलय मुख्य संस्था सोफ़तवेर निरपेक्ष उनका आपके बाटते आशाआपस मुख्यतह उशकी करता। शुरुआत संस्था कुशलता मेंभटृ अनुवाद गएआप विशेष सकते परिभाषित लाभान्वित प्रति देकर समजते दिशामे प्राप्त जैसे वर्णन संस्थान निर्माता प्रव्रुति भाति चुनने उपलब्ध बेंगलूर अर्थपुर्ण&nbsp;</span>
                                        </p>
                                        <p><span xss="removed"></span><b xss="removed">Armeninian Lorem Ipsum</b><span
                                                    xss="removed">:&nbsp;</span><span lang="hy" xml:lang="hy"
                                                                                      xss="removed"><b><u>լոռեմ իպսում դոլոռ սիթ ամեթ, լաբոռե մոդեռաթիուս եթ հաս, պեռ ոմնիս լաթինե դիսպութաթիոնի աթ, վիս ֆեուգաիթ ծիվիբուս եխ. վիվենդում լաբոռամուս ելաբոռառեթ նամ ին.</u></b></span>
                                        </p>
                                        <p><span lang="hy" xml:lang="hy" xss="removed"><b><u><br></u></b></span><br></p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="student-feedback-box">
                        <div class="student-feedback-title">
                            Student feedback
                        </div>
                        <div class="row">
                            <div class="col-lg-3">
                                <div class="average-rating">
                                    <div class="num">
                                        2
                                    </div>
                                    <div class="rating">
                                        <i class="fas fa-star filled" style="color: #f5c85b;"></i>
                                        <i class="fas fa-star filled" style="color: #f5c85b;"></i>
                                        <i class="fas fa-star" style="color: #abb0bb;"></i>
                                        <i class="fas fa-star" style="color: #abb0bb;"></i>
                                        <i class="fas fa-star" style="color: #abb0bb;"></i>
                                    </div>
                                    <div class="title">Average rating</div>
                                </div>
                            </div>
                            <div class="col-lg-9">
                                <div class="individual-rating">
                                    <ul>
                                        <li>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <div>
                <span class="rating">
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                                        <i class="fas fa-star filled"></i>

                </span>
                                                <span>0%</span>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 100%"></div>
                                            </div>
                                            <div>
                <span class="rating">
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                                        <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>

                </span>
                                                <span>100%</span>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <div>
                <span class="rating">
                                      <i class="fas fa-star"></i>
                                      <i class="fas fa-star"></i>
                                                        <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>

                </span>
                                                <span>0%</span>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <div>
                <span class="rating">
                                      <i class="fas fa-star"></i>
                                                        <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>

                </span>
                                                <span>0%</span>
                                            </div>
                                        </li>
                                        <li>
                                            <div class="progress">
                                                <div class="progress-bar" style="width: 0%"></div>
                                            </div>
                                            <div>
                <span class="rating">
                                                        <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>
                                      <i class="fas fa-star filled"></i>

                </span>
                                                <span>0%</span>
                                            </div>
                                        </li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                        <div class="reviews">
                            <div class="reviews-title">Reviews</div>
                            <ul>
                                <li>
                                    <div class="row">
                                        <div class="sik-col-lg-4">
                                            <div class="reviewer-details clearfix">
                                                <div class="reviewer-img float-left">
                                                    <img src="https://demo.academy-lms.com/default/uploads/user_image/3.jpg"
                                                         alt="">
                                                </div>
                                                <div class="review-time">
                                                    <div class="time">
                                                        Sun, 04-Aug-2019
                                                    </div>
                                                    <div class="reviewer-name">
                                                        Jane Doe
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-8">
                                            <div class="review-details">
                                                <div class="rating">
                                                    <i class="fas fa-star filled" style="color: #f5c85b;"></i>
                                                    <i class="fas fa-star filled" style="color: #f5c85b;"></i>
                                                    <i class="fas fa-star" style="color: #abb0bb;"></i>
                                                    <i class="fas fa-star" style="color: #abb0bb;"></i>
                                                    <i class="fas fa-star" style="color: #abb0bb;"></i>
                                                </div>
                                                <div class="review-text">
                                                    Nice man &lt;3
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                <div class="sik-col-lg-4">
                    <div class="course-sidebar natural">
                        <div class="preview-video-box">
                            <a data-toggle="modal" data-target="#CoursePreviewModal">
                                <img src="https://demo.academy-lms.com/default/uploads/thumbnails/course_thumbnails/course_thumbnail_default_15.jpg"
                                     alt="" class="img-fluid">
                                <span class="preview-text">Preview this course</span>
                                <span class="play-btn"></span>
                            </a>
                        </div>
                        <div class="course-sidebar-text-box">
                            <div class="price">
                                <span class="current-price"><span class="current-price">$12</span></span>
                                <span class="original-price">$59.99</span>
                                <input type="hidden" id="total_price_of_checking_out" value="$12">
                            </div>

                            <div class="buy-btns">
                                <a href="javascript::" class="btn btn-buy-now" id="course_15"
                                   onclick="handleBuyNow(this)">Buy now</a>
                                <button class="btn btn-add-cart" type="button" id="15" onclick="handleCartItems(this)">
                                    Add to cart
                                </button>
                            </div>


                            <div class="includes">
                                <div class="title"><b>Includes:</b></div>
                                <ul>
                                    <li><i class="far fa-file-video"></i>
                                        01:22:18 Hours On demand videos
                                    </li>
                                    <li><i class="far fa-file"></i>10 Lessons</li>
                                    <li><i class="far fa-compass"></i>Full lifetime access</li>
                                    <li><i class="fas fa-mobile-alt"></i>Access on mobile and tv</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
    <?php

}

sikshya_footer();

