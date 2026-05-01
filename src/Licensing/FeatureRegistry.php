<?php

/**
 * Product feature catalog — tiers: free, starter, growth, scale (paid store plans).
 *
 * @package Sikshya\Licensing
 */

namespace Sikshya\Licensing;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Registry of licensable features (slugs stable for API + React).
 */
final class FeatureRegistry
{
    /**
     * @return array<string, array{label: string, tier: 'free'|'starter'|'pro'|'scale', group: string, description: string, detail_description?: string}>
     */
    public static function definitions(): array
    {
        return [
            // —— FREE (always available when core is active) ——
            'core_course_builder' => [
                'label' => __('Course builder (curriculum, chapters)', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Build structured courses with modules/chapters, drag-and-drop curriculum ordering, and reusable content—similar to Masteriyo/Tutor course authoring.', 'sikshya'),
            ],
            'lesson_video_text' => [
                'label' => __('Video & text lessons', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Deliver lessons as video embeds, articles, or mixed formats with duration and materials—core lesson types competitors expect.', 'sikshya'),
            ],
            'lesson_attachments' => [
                'label' => __('Lesson attachments', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Attach PDFs, slides, or downloads per lesson so learners get resources alongside the teaching content.', 'sikshya'),
            ],
            'course_preview_faq' => [
                'label' => __('Course preview & FAQ', 'sikshya'),
                'tier' => 'free',
                'group' => 'course',
                'description' => __('Let visitors preview curriculum before purchase and answer common questions with an FAQ block on the course page.', 'sikshya'),
            ],
            'announcements' => [
                'label' => __('Announcements', 'sikshya'),
                'tier' => 'free',
                'group' => 'communication',
                'description' => __('Course and site announcements.', 'sikshya'),
            ],
            'student_dashboard' => [
                'label' => __('Student dashboard & progress', 'sikshya'),
                'tier' => 'free',
                'group' => 'learner',
                'description' => __('My courses, progress, completion.', 'sikshya'),
            ],
            'wishlist' => [
                'label' => __('Wishlist', 'sikshya'),
                'tier' => 'free',
                'group' => 'learner',
                'description' => __('Save courses for later.', 'sikshya'),
            ],
            'quiz_basic' => [
                'label' => __('Basic quizzes (MCQ, T/F, short answer)', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Passing score, attempts, timer (basic).', 'sikshya'),
            ],
            'certificates_basic' => [
                'label' => __('Basic certificates', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Issue certificates on completion.', 'sikshya'),
            ],
            'assignments_basic' => [
                'label' => __('Basic assignments', 'sikshya'),
                'tier' => 'free',
                'group' => 'assessment',
                'description' => __('Simple assignment flow.', 'sikshya'),
            ],
            'checkout_native' => [
                'label' => __('Native checkout (Stripe / PayPal)', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Sell courses with core gateways.', 'sikshya'),
            ],
            'manual_enrollment' => [
                'label' => __('Manual enrollment', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Enroll learners without payment.', 'sikshya'),
            ],
            'coupons_basic' => [
                'label' => __('Basic coupons', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Simple discount codes.', 'sikshya'),
            ],
            'free_courses' => [
                'label' => __('Free courses', 'sikshya'),
                'tier' => 'free',
                'group' => 'commerce',
                'description' => __('Offer courses at no cost.', 'sikshya'),
            ],
            'basic_reports' => [
                'label' => __('Basic reports', 'sikshya'),
                'tier' => 'free',
                'group' => 'analytics',
                'description' => __('Dashboard and simple metrics.', 'sikshya'),
            ],
            'single_instructor' => [
                'label' => __('Single instructor (per site default)', 'sikshya'),
                'tier' => 'free',
                'group' => 'people',
                'description' => __('One primary instructor model; roles still available.', 'sikshya'),
            ],
            'email_notifications_basic' => [
                'label' => __('Basic email notifications', 'sikshya'),
                'tier' => 'free',
                'group' => 'communication',
                'description' => __('Core transactional emails.', 'sikshya'),
            ],
            'email_advanced_customization' => [
                'label' => __('Professional email delivery & branded templates', 'sikshya'),
                'tier' => 'pro',
                'group' => 'communication',
                'description' => __(
                    "Sends Sikshya emails through a real email provider (the same kind of “send mail” setup businesses use) and lets you wrap messages in your logo and layout.\n\nTurn on when students miss your emails in spam, or you want notices to look like your brand—not generic system mail.\n\nTurn off if you already send all site email through another plugin and do not want Sikshya to change delivery.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Settings to connect Sikshya to your email provider (for example SendGrid, Mailgun, Amazon SES, or your host’s outgoing mail). You can also add a simple header and footer so every enrollment or completion message matches your brand. Use Email → Delivery for setup and a test send; transactional wording lives under Email → Templates.\n\nTurn it on when: Mail from your site often lands in junk folders, or you want parents, students, or buyers to see a professional message with your name and colors.\n\nTurn it off when: Another tool already sends every email for your site, or you have not set up an email provider yet and want to keep things simple until you do.\n\nPlan: Growth tier or higher (paid plans). After enabling, open Sikshya → Email in your WordPress dashboard to finish setup.",
                    'sikshya'
                ),
            ],
            'page_builder_widgets_basic' => [
                'label' => __('Page builder widgets (basic)', 'sikshya'),
                'tier' => 'free',
                'group' => 'integrations',
                'description' => __('Elementor / block widgets where implemented.', 'sikshya'),
            ],
            // —— STARTER (EDD “Starter” yearly / lifetime) ——
            'content_drip' => [
                'label' => __('Content drip & scheduled unlock', 'sikshya'),
                'tier' => 'starter',
                'group' => 'course',
                'description' => __(
                    "Releases lessons a little at a time—such as “day 3 after signup” or “next Monday”—instead of showing the whole course on day one.\n\nTurn on for cohorts, term-based programs, or any course where you do not want people skipping to the end.\n\nTurn off for fully self-paced courses where every lesson should open as soon as someone enrolls.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: You choose when each lesson or quiz unlocks. Until then, learners see it as locked. Common choices are “X days after they join” or “on this calendar date.”\n\nTurn it on when: You teach in order, run groups that move together, or want to space content so people are not overwhelmed.\n\nTurn it off when: Everyone should browse every lesson right away, like a simple video library with no schedule.\n\nPlan: Starter tier or higher (paid plans). Pairs well with the Drip notifications add-on and the drip templates on Email templates if you want optional unlock emails.",
                    'sikshya'
                ),
            ],
            'course_reviews' => [
                'label' => __('Course reviews & ratings', 'sikshya'),
                'tier' => 'starter',
                'group' => 'course',
                'description' => __(
                    "Lets enrolled students leave star ratings and short written reviews on your course pages, with optional moderation before they go live.\n\nTurn on to build social proof on catalog pages and give future buyers real feedback from past students.\n\nTurn off if you collect testimonials outside Sikshya or prefer no public rating system on your site.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A ratings + review block on each course's landing page (average star, breakdown, and approved reviews). Enrolled learners can submit a 1–5 star rating with an optional written review, edit their own, or delete it. You moderate everything from a dedicated Reviews page in the admin — approve, unpublish, or delete — and decide whether reviews auto-publish or wait for your OK.\n\nTurn it on when: You want learners to help sell your courses with honest feedback, and you want the data (averages, counts) reflected in your catalog cards.\n\nTurn it off when: Your teaching model doesn't fit public reviews (e.g. private coaching, compliance training) or you gather feedback through a separate tool.\n\nPlan: Starter tier or higher (paid plans).",
                    'sikshya'
                ),
            ],
            'community_discussions' => [
                'label' => __('Course discussions & Q&A', 'sikshya'),
                'tier' => 'pro',
                'group' => 'communication',
                'description' => __(
                    "Adds learner discussion threads and Q&A areas for courses, including instructor-author replies and moderation workflows.\n\nTurn on when your teaching model needs in-course conversation.\n\nTurn off when you prefer comments disabled or only external community tools.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Discussion/Q&A tabs inside learning pages, posting and threaded replies, and instructor response controls. This is intended for cohort and instructor-led experiences where questions are answered in context.\n\nPlan: Growth tier or higher (paid plans).",
                    'sikshya'
                ),
            ],
            'prerequisites' => [
                'label' => __('Prerequisites (lessons & courses)', 'sikshya'),
                'tier' => 'starter',
                'group' => 'course',
                'description' => __(
                    "Blocks the next lesson or course until the learner finishes what you marked as required—like “complete Lesson 2 before Lesson 3” or “finish Course A before Course B.”\n\nTurn on when order matters: safety steps, beginner then advanced topics, or packaged programs.\n\nTurn off when every course stands alone and you do not need to enforce order.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: In the course builder you can say which lessons or whole courses must be completed first. Sikshya then keeps the next steps closed until those are done.\n\nTurn it on when: You need a clear path—compliance training, leveled skills, or “must finish the basics before the exam.”\n\nTurn it off when: Students pick their own path and nothing must come first.\n\nPlan: Starter tier or higher (paid plans). You set prerequisites inside each course or lesson in the builder—no code required.",
                    'sikshya'
                ),
            ],
            // —— GROWTH (EDD Growth yearly / lifetime; growth-tier catalog) ——
            'multi_instructor' => [
                'label' => __('Multi-instructor & co-authors', 'sikshya'),
                'tier' => 'pro',
                'group' => 'people',
                'description' => __(
                    "Lets several teachers appear on one course and, if you sell it, split the money the way you agree—handy for teams or guest experts.\n\nTurn on when more than one person teaches or earns from the same course.\n\nTurn off when you alone create and sell every course and no shared payouts are needed.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Pick teachers in the course builder, tune optional revenue weights, and use Course staff in the dashboard for fine control. When orders complete, Sikshya writes ledger rows using normalized splits so totals never exceed the line item. Global switches control where the team appears (single course, cards, learn screen); each course can hide the public team or turn off revenue recording for that course.\n\nTurn it on when: You run a training business with co-teachers, guest experts, or revenue partners.\n\nTurn it off when: You alone author and sell every course.\n\nPlan: Growth tier or higher (paid plans). Configure global behavior under Add-ons → Multi-instructor; staff and percentages under Course staff.",
                    'sikshya'
                ),
            ],
            'instructor_dashboard' => [
                'label' => __('Instructor dashboard', 'sikshya'),
                'tier' => 'starter',
                'group' => 'people',
                'description' => __(
                    "Gives each teacher a simple snapshot of how many people joined the courses they created—without handing them the full admin area.\n\nTurn on when teachers should see their own numbers on a custom page or app your team builds.\n\nTurn off when only you (the site owner) ever look at stats in the main Sikshya screens.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A safe summary your site can show to logged-in teachers—typically how many enrollments sit on courses they authored. Your designer or developer connects this to a “My teaching” page if you want one.\n\nTurn it on when: You want coaches or staff to see their reach without seeing everyone else’s data or full site reports.\n\nTurn it off when: Teachers never log in to numbers and you handle reporting yourself.\n\nPlan: Starter tier or higher (paid plans). This does not replace Sikshya’s main Reports area in the dashboard—it only feeds simple teacher-facing totals.",
                    'sikshya'
                ),
            ],
            'reports_advanced' => [
                'label' => __('Advanced analytics & exports', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __(
                    "Pulls enrollment and progress-style numbers into spreadsheet-friendly files so you can open them in Excel or Google Sheets.\n\nTurn on when your boss, accountant, or partner asks for files—not just on-screen charts.\n\nTurn off when the built-in Sikshya dashboard is enough and you never export.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Beyond the usual on-screen charts, you can download structured data about who joined what, progress, and related totals for planning or sharing offline.\n\nTurn it on when: Someone needs to filter, pivot, or archive numbers outside the website—common for finance, grants, or yearly reviews.\n\nTurn it off when: You never leave the admin dashboard for data and want to keep exports turned off until you need them.\n\nPlan: Growth tier or higher (paid plans). Works together with the visual reports page; it adds “take this away as a file” power.",
                    'sikshya'
                ),
            ],
            'gradebook' => [
                'label' => __('Gradebook', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __(
                    "Rolls quiz marks and assignment scores into one view per learner and course—like a school gradebook—and lets you export it.\n\nTurn on when you grade work and need “who earned what” in one place.\n\nTurn off for simple memberships where you never score or track letter-style grades.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Combines quiz results and graded assignments into clear rows per student and course, so you see overall performance instead of clicking each activity.\n\nTurn it on when: You run classes with real grading, report cards, or pass/fail lists.\n\nTurn it off when: You only offer videos and quizzes for fun with no formal grades.\n\nPlan: Growth tier or higher (paid plans). You need quizzes and/or assignments in use for meaningful rows to appear.",
                    'sikshya'
                ),
            ],
            'activity_log' => [
                'label' => __('Student activity log', 'sikshya'),
                'tier' => 'pro',
                'group' => 'analytics',
                'description' => __(
                    "Keeps a dated list of big moments—joined a course, finished a lesson, passed a quiz, handed in work, bought something—so you can answer “what happened?” for one student.\n\nTurn on for support, disputes, or any time you must show who did what and when.\n\nTurn off if you want the lightest possible history and privacy rules allow it.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A timeline-style record inside Sikshya when learners enroll, complete steps, submit work, finish quizzes, or check out. Staff can look up one person and see the story in order.\n\nTurn it on when: Parents call, auditors ask, or you settle “I never got access” questions with facts.\n\nTurn it off when: You are tiny, rarely need proof, and prefer storing as little activity data as your policy allows.\n\nPlan: Growth tier or higher (paid plans). Follow your privacy rules for how long to keep learner data.",
                    'sikshya'
                ),
            ],
            'certificates_advanced' => [
                'label' => __('Advanced certificates (builder, QR, verification)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __(
                    "Adds a shareable link and a simple “check it’s real” page for certificates, plus richer layouts than the basic certificate.\n\nTurn on when employers or schools need to verify completion online or you want a scannable code on the PDF.\n\nTurn off when a plain certificate from the free tier is enough.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Learners can share a link that proves the certificate is genuine. You can style the design more deeply, and optional QR codes can point to that proof page. Names and dates fill in automatically from Sikshya.\n\nTurn it on when: HR teams, regulators, or students need trustworthy proof—not just a screenshot.\n\nTurn it off when: You only email a simple PDF and nobody checks authenticity online.\n\nPlan: Growth tier or higher (paid plans). Builds on basic certificates; configure looks under Sikshya’s certificate settings.",
                    'sikshya'
                ),
            ],
            'subscriptions' => [
                'label' => __('Subscriptions & memberships', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __(
                    "Sells ongoing access—like a monthly membership or coaching plan—instead of only one-time course purchases.\n\nTurn on for libraries people pay every month for, or any model with repeating billing.\n\nTurn off if you only ever charge once per course and do not need renewals.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "Not the same as Professional email delivery: SMTP and branded HTML wrappers are the separate “Professional email delivery & branded templates” add-on under Communication.\n\nWhat you get: Sikshya can track subscription-style plans and who is currently paid up, then open or close course access based on that status. Your payment provider still handles charging cards—you connect the pieces in settings.\n\nTurn it on when: You want predictable monthly or yearly income, “all you can learn” passes, or coaching billed on a schedule.\n\nTurn it off when: Every sale is a single payment and you never want renewal logic.\n\nPlan: Growth tier or higher (paid plans). Set up payment methods under Sikshya’s payment or commerce settings first.",
                    'sikshya'
                ),
            ],
            'course_bundles' => [
                'label' => __('Course bundles', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __(
                    "Packages several courses together and sells them for one price—your “three-course bundle” or bootcamp pack.\n\nTurn on when you want one checkout for multiple courses.\n\nTurn off when every course is always bought on its own.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: You define bundle names, prices, and which courses belong in each pack. Your storefront or theme uses that information so shoppers can add the whole pack at once.\n\nTurn it on when: You promote value packs, diplomas made of several courses, or holiday specials.\n\nTurn it off when: Bundles would confuse your catalog or you never discount groups of courses.\n\nPlan: Growth tier or higher (paid plans). You may still need your theme to display bundle tiles the way you want on the public site.",
                    'sikshya'
                ),
            ],
            'coupons_advanced' => [
                'label' => __('Advanced coupons & upsells', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __(
                    "Lets you say “this code only works over $50” or “only for these courses”—tighter than a flat percent off everything.\n\nTurn on when simple site-wide codes are too easy to misuse.\n\nTurn off when basic coupons cover every sale you run.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Extra coupon rules such as a minimum order size and which courses a code may apply to. Checkout can refuse a code that does not match your rules.\n\nTurn it on when: You need to stop tiny orders from using big discounts or limit a code to one product line.\n\nTurn it off when: Straightforward “10% off” style codes are all you use.\n\nPlan: Growth tier or higher (paid plans). Advanced rules may be edited from developer tools or future admin screens—ask your host or partner if you need help.",
                    'sikshya'
                ),
            ],
            'dynamic_checkout_fields' => [
                'label' => __('Dynamic checkout fields', 'sikshya'),
                'tier' => 'pro',
                'group' => 'commerce',
                'description' => __(
                    "Add custom fields to checkout (text, select, checkbox, etc.) and show/hide them based on answers.\n\nTurn on when you need extra buyer info like company, VAT, referral source, or consent checkboxes.\n\nTurn off when the built-in checkout fields are enough.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A drag-and-drop style builder for extra checkout questions (text, dropdowns, checkboxes) with simple rules like “show this when Plan = Business”. Answers save to each order and can optionally be stored on the user for faster repeat checkout.\n\nTurn it on when: You sell to businesses, need compliance questions, or want structured lead info at purchase time.\n\nTurn it off when: You only need email + billing address.\n\nPlan: Growth tier or higher (paid plans).",
                    'sikshya'
                ),
            ],
            'assignments_advanced' => [
                'label' => __('Advanced assignments', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __(
                    "Adds a clear grading checklist (rubric) and lets you limit uploads to certain file types—only PDF, only images, and so on.\n\nTurn on for serious classes where instructions and file rules matter.\n\nTurn off when open-ended “upload anything” assignments are fine.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Space to describe how work will be scored, plus controls for which file endings learners may submit.\n\nTurn it on when: You teach academically or in companies that require written criteria and strict hand-in formats.\n\nTurn it off when: Casual homework without formal rubrics is enough.\n\nPlan: Growth tier or higher (paid plans). Make sure your course theme shows the rubric to students if they should read it before submitting.",
                    'sikshya'
                ),
            ],
            'quiz_advanced' => [
                'label' => __('Advanced quiz types', 'sikshya'),
                'tier' => 'pro',
                'group' => 'assessment',
                'description' => __(
                    "Sorts questions into reusable groups so you can mix and match them in many quizzes without retyping.\n\nTurn on when you build lots of questions or reuse the same pool across courses.\n\nTurn off when each quiz is tiny and one-off.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: You can label and group questions (for example “Week 2 — hard”) and pull from those groups when you build a new test—similar to organizing files in folders.\n\nTurn it on when: Your question list is large or many courses share the same bank.\n\nTurn it off when: You only ever write a handful of unique questions per quiz.\n\nPlan: Growth tier or higher (paid plans). You manage groups inside the same place you edit questions today.",
                    'sikshya'
                ),
            ],
            'live_classes' => [
                'label' => __('Live classes (Zoom / Meet / Classroom)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'integrations',
                'description' => __(
                    "Saves the video-call link and a plain label (Zoom, Meet, etc.) right on the lesson so students always know where to click.\n\nTurn on for blended programs that meet live online.\n\nTurn off for courses that are only videos and text—no live sessions.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Fields on a lesson for the meeting URL and which tool you use. Your theme can show a “Join live class” button next to that lesson.\n\nTurn it on when: Learners should never hunt through email for the right link.\n\nTurn it off when: You never schedule live calls.\n\nPlan: Growth tier or higher (paid plans). You still create the actual meeting in Zoom or Google Meet—Sikshya only stores the link students should use.",
                    'sikshya'
                ),
            ],
            'social_login' => [
                'label' => __('Social login', 'sikshya'),
                'tier' => 'pro',
                'group' => 'learner',
                'description' => __(
                    "Lets people sign in with Google or similar accounts instead of making a new password on your site.\n\nTurn on when you want faster signup for students who already use those services.\n\nTurn off when your policy says everyone must use email and password only.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A place to paste the keys Google or Facebook gives you after you register your site with them. Sikshya stores those safely and can show “Continue with Google” style choices on your login page.\n\nTurn it on when: You want fewer abandoned signups and your organization allows social sign-in.\n\nTurn it off when: Privacy rules require traditional accounts only.\n\nPlan: Growth tier or higher (paid plans). Completing the sign-in flow sometimes needs help from your theme or a small extra plugin—your developer can wire the buttons if needed.",
                    'sikshya'
                ),
            ],
            'drip_notifications' => [
                'label' => __('Drip & automation emails', 'sikshya'),
                'tier' => 'starter',
                'group' => 'communication',
                'description' => __(
                    "Unlocks transactional emails when Content drip opens a lesson or a course-wide schedule—configured on Email templates (“Drip: lesson unlocked” / “Drip: course schedule unlocked”), not under delivery settings.\n\nEnable this add-on together with Content drip so the events exist; enable or disable each template’s send separately.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: When scheduled content becomes available, Sikshya can email the learner using the templates in Email templates. Turn each template on or off there—disabling a template does not turn off Content drip, only that message.\n\nTurn the add-on on when: You use timed releases and want optional unlock notices.\n\nTurn the add-on off when: You never want drip-related mail at all.\n\nPlan: Starter tier or higher (paid plans). Works best when Content drip is already enabled.",
                    'sikshya'
                ),
            ],
            'calendar' => [
                'label' => __('Calendar', 'sikshya'),
                'tier' => 'starter',
                'group' => 'learner',
                'description' => __(
                    "Shows each learner a dated schedule—enrollments, upcoming lesson unlocks (when you use content drip), and assignment due dates—on their account dashboard, and exposes the same data over the REST API for custom pages.\n\nTurn on when you want deadlines and releases visible in one place.\n\nTurn off if you do not need a learner-facing schedule or API feed.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Logged-in students see a “Your schedule” section on My account → Overview with their items sorted by date. Developers can read the same list from the Sikshya REST API (learner calendar endpoint) to build a full calendar widget if you want.\n\nTurn it on when: Learners should see when lessons open or work is due without opening every course.\n\nTurn it off when: You have no drip rules, no assignment due dates, and no need for a schedule list.\n\nPlan: Starter tier or higher (paid plans). Lesson unlock dates require the Content drip add-on and drip rules saved for those lessons.",
                    'sikshya'
                ),
            ],
            'scorm_h5p_pro' => [
                'label' => __('SCORM / H5P (Growth tier)', 'sikshya'),
                'tier' => 'pro',
                'group' => 'integrations',
                'description' => __(
                    "Lets you attach packaged e-learning lessons (SCORM) or rich interactive slides (H5P) inside a Sikshya lesson.\n\nTurn on when you already bought interactive content or use the free H5P tools.\n\nTurn off for simple video-and-text courses with no packaged interactions.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Fields to paste a launch link or embed code so learners open vendor-built training inside your course path.\n\nTurn it on when: You invested in ready-made interactive modules and want them inside Sikshya navigation.\n\nTurn it off when: You only host your own videos and never upload outside packages.\n\nPlan: Growth tier or higher (paid plans). Large SCORM files sometimes need your host to allow bigger uploads or special players—your IT person can confirm.",
                    'sikshya'
                ),
            ],
            // —— SCALE (top commercial band) ——
            'marketplace_multivendor' => [
                'label' => __('Multi-vendor marketplace', 'sikshya'),
                'tier' => 'scale',
                'group' => 'commerce',
                'description' => __(
                    "Remembers which seller owns each course and how much the platform keeps when different teachers sell on the same site.\n\nTurn on for a true marketplace with many independent sellers.\n\nTurn off for a single brand with one bank account.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Behind the scenes, Sikshya tracks who should be paid and how much the site earns on each order when multiple vendors list courses.\n\nTurn it on when: You run something like a mini Udemy with many instructors and split payouts.\n\nTurn it off when: You alone sell courses—extra tracking adds no value.\n\nPlan: Scale tier (paid plans). You still decide how vendors join and how you pay them outside the software.",
                    'sikshya'
                ),
            ],
            'white_label' => [
                'label' => __('White label & branding', 'sikshya'),
                'tier' => 'scale',
                'group' => 'platform',
                'description' => __(
                    "Replaces Sikshya’s default names in the WordPress dashboard and can tune login colors so clients see your agency or brand first.\n\nTurn on when you resell the LMS under your own name.\n\nTurn off to keep standard labels and the quickest setup.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Options to change footer text, accent colors on the login screen, and other small touches so “Sikshya” can fade behind your logo in the admin area.\n\nTurn it on when: Agencies deliver sites to customers who should not see third-party product names.\n\nTurn it off when: Your internal team prefers seeing the original product name for support chats.\n\nPlan: Scale tier (paid plans). This does not rename WordPress itself—only Sikshya-specific spots.",
                    'sikshya'
                ),
            ],
            'webhooks' => [
                'label' => __('Webhooks', 'sikshya'),
                'tier' => 'scale',
                'group' => 'integrations',
                'description' => __(
                    "Send signed JSON events to your own endpoint whenever key LMS events happen—enrollment, order, completion, certificates, and more.\n\nTurn on when you have developers or internal systems that should react automatically.\n\nTurn off when you do not integrate Sikshya with anything else.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A webhook registry and a reliable dispatcher. Sikshya POSTs event payloads to your configured URLs.\n\nTurn it on when: You connect to your own backend, data warehouse, or internal tools.\n\nTurn it off when: You don’t have a receiver endpoint—unused hooks add no value.\n\nPlan: Scale tier (paid plans).",
                    'sikshya'
                ),
            ],
            'zapier' => [
                'label' => __('Zapier', 'sikshya'),
                'tier' => 'scale',
                'group' => 'integrations',
                'description' => __(
                    "Connect Sikshya to Zapier using “Catch Hook” URLs so events can create rows, send Slack messages, trigger emails, and more—no code required.\n\nTurn on when your team uses Zapier.\n\nTurn off when you don’t use Zapier.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: A Zapier-friendly setup on top of Sikshya’s webhook events. Paste Zapier hook URLs, choose events, and Sikshya will deliver signed JSON payloads.\n\nPlan: Scale tier (paid plans).",
                    'sikshya'
                ),
            ],
            'email_marketing' => [
                'label' => __('Email marketing (Mailchimp / MailerLite)', 'sikshya'),
                'tier' => 'scale',
                'group' => 'integrations',
                'description' => __(
                    "Sync learners into your email marketing lists when they enroll or complete a course.\n\nTurn on when you want Mailchimp/MailerLite automations driven by Sikshya learning events.\n\nTurn off if you don’t run email marketing campaigns.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Native list sync for Mailchimp and MailerLite based on enrollment and completion events.\n\nPlan: Scale tier (paid plans).",
                    'sikshya'
                ),
            ],
            'public_api_keys' => [
                'label' => __('Public API & API keys', 'sikshya'),
                'tier' => 'scale',
                'group' => 'platform',
                'description' => __(
                    "Creates secret keys so a mobile app or custom website can talk to Sikshya safely—without sharing your admin password.\n\nTurn on when you build an app or hire developers who need programmatic access.\n\nTurn off when the normal WordPress site is the only place people use Sikshya.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: You generate named keys; Sikshya stores them safely. Apps send the key along with requests so only your software can read course data you allow.\n\nTurn it on when: You ship a learner app, headless storefront, or partner integration.\n\nTurn it off when: Nobody should programmatically reach your data—keys are as sensitive as passwords.\n\nPlan: Scale tier (paid plans). Revoke any key that might be exposed.",
                    'sikshya'
                ),
            ],
            'multisite_scale' => [
                'label' => __('Multisite & network license tools', 'sikshya'),
                'tier' => 'scale',
                'group' => 'platform',
                'description' => __(
                    "Adds a network-wide help screen explaining how commercial licensing applies to each subsite.\n\nTurn on only for WordPress “multisite” networks with many campuses under one install.\n\nTurn off on normal single sites—it does nothing there.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: Super-admins see guidance for which plan covers which site in a multisite network.\n\nTurn it on when: IT runs many sites from one WordPress dashboard.\n\nTurn it off when: You have a standard single website—there is no network menu to attach to.\n\nPlan: Scale tier (paid plans) and a WordPress multisite installation.",
                    'sikshya'
                ),
            ],
            'enterprise_reports' => [
                'label' => __('Enterprise reporting', 'sikshya'),
                'tier' => 'scale',
                'group' => 'analytics',
                'description' => __(
                    "Emails a short weekly recap of key numbers—enrollments, completions, and similar—to the main admin email.\n\nTurn on so leaders get a snapshot without opening the dashboard.\n\nTurn off if you already get reports elsewhere or dislike scheduled mail.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: On a weekly rhythm, Sikshya sends a plain-language summary to your chosen inbox so directors can skim health at a glance.\n\nTurn it on when: Busy executives want email updates instead of logging in.\n\nTurn it off when: Automated mail is blocked by your host or you rely on another reporting tool.\n\nPlan: Scale tier (paid plans). Pair with Advanced analytics if you need downloadable detail too.",
                    'sikshya'
                ),
            ],
            'multilingual_enterprise' => [
                'label' => __('Multilingual (WPML / Weglot)', 'sikshya'),
                'tier' => 'scale',
                'group' => 'platform',
                'description' => __(
                    "Works with translation plugins so Sikshya’s buttons and messages can appear in more than one language site-wide.\n\nTurn on when students choose English, Spanish, etc., on the same installation.\n\nTurn off for a single-language site to avoid extra setup.",
                    'sikshya'
                ),
                'detail_description' => __(
                    "What you get: When a translation plugin such as WPML is active, Sikshya registers its user-facing text so translators can localize it like the rest of your theme.\n\nTurn it on when: Corporate or government training must meet language requirements.\n\nTurn it off when: Everyone reads one language and you do not use a translation suite.\n\nPlan: Scale tier (paid plans), plus your chosen translation plugin—Sikshya does not replace that plugin.",
                    'sikshya'
                ),
            ],
        ];
    }

    /**
     * @return array<int, array{id: string, label: string, tier: string, group: string, description: string, detailDescription: string}>
     */
    public static function catalogForClient(): array
    {
        $out = [];
        foreach (self::definitions() as $id => $row) {
            $detail = isset($row['detail_description']) && is_string($row['detail_description']) ? $row['detail_description'] : (string) ($row['description'] ?? '');
            $out[] = [
                'id' => $id,
                'label' => $row['label'],
                'tier' => $row['tier'],
                'group' => $row['group'],
                'description' => $row['description'],
                'detailDescription' => $detail,
            ];
        }

        return $out;
    }

    /**
     * @return array<string, array{label: string, tier: string, group: string, description: string}|null>
     */
    public static function get(string $featureId): ?array
    {
        $all = self::definitions();

        return $all[$featureId] ?? null;
    }
}
