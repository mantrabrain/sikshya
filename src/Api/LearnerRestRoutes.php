<?php

namespace Sikshya\Api;

use Sikshya\Api\Learner\AbstractLearnerRestController;
use Sikshya\Api\Learner\AssignmentRoutes;
use Sikshya\Api\Learner\ContentNoteRoutes;
use Sikshya\Api\Learner\EnrollmentRoutes;
use Sikshya\Api\Learner\ProgressRoutes;
use Sikshya\Api\Learner\QuizRoutes;

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Learner REST coordinator.
 *
 * Used to be a 1,370-line god-class owning every learner route directly. The 2026-05 hardening
 * sprint split it into five domain-bounded subclasses of {@see AbstractLearnerRestController}.
 * This file is now a thin dispatcher that instantiates each subclass and re-fires the addon
 * extension hook so existing add-ons keep working unchanged.
 *
 * Route paths and response shapes are preserved exactly; clients should see no difference.
 *
 * @package Sikshya\Api
 */
class LearnerRestRoutes extends AbstractLearnerRestController
{
    public function register(): void
    {
        // Domain controllers extracted during the 2026-05 split. Each owns its own route paths,
        // callbacks, args schemas, and (where applicable) private helpers.
        (new AssignmentRoutes($this->plugin))->register();
        (new ContentNoteRoutes($this->plugin))->register();
        (new EnrollmentRoutes($this->plugin))->register();
        (new ProgressRoutes($this->plugin))->register();
        (new QuizRoutes($this->plugin))->register();

        /**
         * Allow enabled add-ons to register learner REST routes.
         *
         * Hook contract is unchanged from pre-split versions: addons receive the REST namespace
         * and a `LearnerRestRoutes` instance. `requireLoginOrJwt()` is inherited from the base
         * controller, so any addon using `[$routes, 'requireLoginOrJwt']` as a permission_callback
         * still works.
         *
         * @param string            $namespace REST namespace, currently `sikshya/v1`.
         * @param LearnerRestRoutes $routes    Coordinator instance (extends AbstractLearnerRestController).
         */
        do_action('sikshya_register_addon_learner_rest_routes', 'sikshya/v1', $this);
    }
}
