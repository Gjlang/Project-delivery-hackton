<?php

/**
 * Maps every active SC/TS rule to how the Playwright testing module handles
 * it. This is the single source of truth for rule -> handler wiring so rule
 * codes are never scattered as ad-hoc `if` statements across the app.
 *
 * type:
 *   browser_automated    - a real Playwright check runs, no optional context required
 *   context_dependent    - a real Playwright check runs, but only if the
 *                          listed `requires` test-context keys were supplied;
 *                          otherwise applicability_status = insufficient_context
 *   framework_policy     - describes how the testing framework itself must
 *                          behave (TS-027..032, SC-013, SC-014) rather than a
 *                          checkable page property; evaluated by
 *                          RuleApplicabilityEngine from the framework's own
 *                          design, never sent to the Playwright worker
 *   browser_limitation   - explicitly outside what a browser can observe
 *                          (e.g. backend/infrastructure controls); always
 *                          NOT TESTABLE
 *
 * handler: the check name the Node/Playwright worker understands (null for
 * framework_policy/browser_limitation rules, which never reach the worker).
 *
 * requires: test_context keys that must be non-empty for a context_dependent
 * rule to be applicable.
 */
return [

    // ---- Security & Compliance ----

    'SC-001' => ['type' => 'browser_automated', 'handler' => 'checkHttps', 'requires' => [], 'severity' => 'critical'],
    'SC-002' => ['type' => 'browser_automated', 'handler' => 'checkMixedContent', 'requires' => [], 'severity' => 'high'],
    'SC-003' => ['type' => 'context_dependent', 'handler' => 'checkProtectedPage', 'requires' => ['protected_routes'], 'severity' => 'critical'],
    'SC-004' => ['type' => 'context_dependent', 'handler' => 'checkUnauthorizedBehaviour', 'requires' => ['protected_routes'], 'severity' => 'high'],
    'SC-005' => ['type' => 'context_dependent', 'handler' => 'checkLogoutProtection', 'requires' => ['login_url', 'valid_credentials', 'protected_routes'], 'severity' => 'critical'],
    'SC-006' => ['type' => 'browser_automated', 'handler' => 'checkUrlSensitiveInfo', 'requires' => [], 'severity' => 'high'],
    'SC-007' => ['type' => 'browser_automated', 'handler' => 'checkPasswordFieldType', 'requires' => [], 'severity' => 'high'],
    'SC-008' => ['type' => 'browser_automated', 'handler' => 'checkVisibleSecrets', 'requires' => [], 'severity' => 'critical'],
    'SC-009' => ['type' => 'context_dependent', 'handler' => 'checkAdminPageProtection', 'requires' => ['admin_routes'], 'severity' => 'critical'],
    'SC-010' => ['type' => 'context_dependent', 'handler' => 'checkLoginWorkflow', 'requires' => ['login_url', 'valid_credentials'], 'severity' => 'high'],
    'SC-011' => ['type' => 'context_dependent', 'handler' => 'checkRoleBasedAccess', 'requires' => ['role_accounts'], 'severity' => 'medium'],
    'SC-012' => ['type' => 'browser_automated', 'handler' => 'checkErrorInfoExposure', 'requires' => [], 'severity' => 'high'],
    'SC-013' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'SC-014' => ['type' => 'browser_limitation', 'handler' => null, 'requires' => [], 'severity' => null],

    // ---- Technical Standards ----

    'TS-001' => ['type' => 'browser_automated', 'handler' => 'checkAvailability', 'requires' => [], 'severity' => 'critical'],
    'TS-002' => ['type' => 'browser_automated', 'handler' => 'checkPageLoad', 'requires' => [], 'severity' => 'high'],
    'TS-003' => ['type' => 'context_dependent', 'handler' => 'checkRequiredNavigation', 'requires' => ['required_navigation'], 'severity' => 'medium'],
    'TS-004' => ['type' => 'browser_automated', 'handler' => 'checkInternalLinks', 'requires' => [], 'severity' => 'medium'],
    'TS-005' => ['type' => 'context_dependent', 'handler' => 'checkExpectedRouting', 'requires' => ['expected_routes'], 'severity' => 'medium'],
    'TS-006' => ['type' => 'browser_automated', 'handler' => 'checkPageTitle', 'requires' => [], 'severity' => 'low'],
    'TS-007' => ['type' => 'context_dependent', 'handler' => 'checkRequiredVisibility', 'requires' => ['critical_pages'], 'severity' => 'medium'],
    'TS-008' => ['type' => 'browser_automated', 'handler' => 'checkInteractiveElements', 'requires' => [], 'severity' => 'high'],
    'TS-009' => ['type' => 'browser_automated', 'handler' => 'checkFormLabels', 'requires' => [], 'severity' => 'medium'],
    'TS-010' => ['type' => 'browser_automated', 'handler' => 'checkImageAltText', 'requires' => [], 'severity' => 'low'],
    'TS-011' => ['type' => 'context_dependent', 'handler' => 'checkResponsiveLayout', 'requires' => ['viewports'], 'severity' => 'medium'],
    'TS-012' => ['type' => 'browser_automated', 'handler' => 'checkContentOverflow', 'requires' => [], 'severity' => 'medium'],
    'TS-013' => ['type' => 'context_dependent', 'handler' => 'checkRequiredFieldValidation', 'requires' => ['test_forms'], 'severity' => 'high'],
    'TS-014' => ['type' => 'context_dependent', 'handler' => 'checkInvalidInputFeedback', 'requires' => ['test_forms'], 'severity' => 'medium'],
    'TS-015' => ['type' => 'context_dependent', 'handler' => 'checkValidFormSubmission', 'requires' => ['test_forms'], 'severity' => 'high'],
    'TS-016' => ['type' => 'context_dependent', 'handler' => 'checkDuplicateSubmission', 'requires' => ['test_forms', 'allow_duplicate_submission_test'], 'severity' => 'medium'],
    'TS-017' => ['type' => 'context_dependent', 'handler' => 'checkCriticalWorkflow', 'requires' => ['critical_workflows'], 'severity' => 'critical'],
    'TS-018' => ['type' => 'browser_automated', 'handler' => 'checkUnhandledPageError', 'requires' => [], 'severity' => 'high'],
    'TS-019' => ['type' => 'browser_automated', 'handler' => 'checkJavaScriptErrors', 'requires' => [], 'severity' => 'high'],
    'TS-020' => ['type' => 'browser_automated', 'handler' => 'checkResourceLoading', 'requires' => [], 'severity' => 'medium'],
    'TS-021' => ['type' => 'browser_automated', 'handler' => 'checkLoadingTimeout', 'requires' => [], 'severity' => 'medium'],
    'TS-022' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => 'low'],
    'TS-023' => ['type' => 'context_dependent', 'handler' => 'checkKeyboardInteraction', 'requires' => ['critical_workflows'], 'severity' => 'medium'],
    'TS-024' => ['type' => 'browser_automated', 'handler' => 'checkAccessibleButtonName', 'requires' => [], 'severity' => 'medium'],
    'TS-025' => ['type' => 'browser_automated', 'handler' => 'checkAccessibleLinkName', 'requires' => [], 'severity' => 'medium'],
    'TS-026' => ['type' => 'browser_automated', 'handler' => 'checkAccessibleFormControls', 'requires' => [], 'severity' => 'medium'],
    'TS-027' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'TS-028' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'TS-029' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'TS-030' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'TS-031' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],
    'TS-032' => ['type' => 'framework_policy', 'handler' => null, 'requires' => [], 'severity' => null],

    // ---- Testing Standards (company-uploaded, TR-xxx) ----
    // These codes come from whatever the company uploads under "Testing
    // Standards" (testing_result_rules table), not a fixed catalog -- mapped
    // here to the closest existing Playwright check for the current upload.
    // Re-uploading a differently-worded/numbered document later will need
    // this mapping revisited.

    'TR-001' => ['type' => 'browser_automated', 'handler' => 'checkInteractiveElements', 'requires' => [], 'severity' => 'high'],
    'TR-002' => ['type' => 'browser_automated', 'handler' => 'checkInternalLinks', 'requires' => [], 'severity' => 'medium'],
    'TR-003' => ['type' => 'browser_automated', 'handler' => 'checkLoadingTimeout', 'requires' => [], 'severity' => 'medium'],
    'TR-004' => ['type' => 'browser_automated', 'handler' => 'checkResourceLoading', 'requires' => [], 'severity' => 'medium'],
    'TR-005' => ['type' => 'context_dependent', 'handler' => 'checkRequiredFieldValidation', 'requires' => ['test_forms'], 'severity' => 'high'],
    'TR-006' => ['type' => 'context_dependent', 'handler' => 'checkInvalidInputFeedback', 'requires' => ['test_forms'], 'severity' => 'medium'],
    'TR-007' => ['type' => 'context_dependent', 'handler' => 'checkValidFormSubmission', 'requires' => ['test_forms'], 'severity' => 'medium'],
    'TR-008' => ['type' => 'browser_automated', 'handler' => 'checkResponsiveLayout', 'requires' => [], 'severity' => 'medium'],

];
