<?php

use App\Sso\FormsProvisioner;

/*
 * Dot.Forms' own choices for the shared sign-in package (packages/dot-sso-client). Everything else, including
 * DOTSSO_ENABLED (default false), comes from the package's defaults and the host .env. The session guard is the
 * package default (DOTSSO_GUARD, web): nothing is set here.
 */
return [
    'provisioner' => FormsProvisioner::class,

    // Forms has always created an account for a person InfoDot vouches for (INFODOT_PROVISION_USERS), so that stays.
    'auto_provision' => (bool) env('DOTSSO_AUTO_PROVISION', env('INFODOT_PROVISION_USERS', true)),

    'landing' => '/dashboard',

    // A deep link may open any signed-in page of the desk, and nothing else. The PUBLIC form (/forms/{slug}) is not a
    // sign-in target: a respondent never signs in.
    'deep_link' => [
        'allow' => ['#^/(dashboard|teams|user/profile|team-invitations)(/|\?|$)#'],
        'deny' => ['#^/(sso|login|register|logout|forgot-password|reset-password|auth|oauth|two-factor-challenge|user/confirm)(/|\?|$)#i'],
    ],

    // Never intercepted. Outside the pages the package takes over: the public form and its embedding (/forms/{slug}),
    // /guardian/health (bearer token), /up and /up/scheduler, /csp-report, and the Sanctum /api/*. Anonymous respondents
    // stay OUTSIDE the central sign-in by design. Nothing extra to list.
    'allow_paths' => [],
];
