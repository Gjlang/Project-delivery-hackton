import { chromium, firefox, webkit, Browser } from 'playwright';
import { CheckContext, RuleResult, WorkerInput } from './types.js';
import { baseEvidence } from './utils.js';
import * as security from './checks/security.js';
import * as technical from './checks/technical.js';

type Handler = (browser: Browser, ctx: CheckContext) => Promise<RuleResult>;

const HANDLERS: Record<string, Handler> = {
  checkHttps: security.checkHttps,
  checkMixedContent: security.checkMixedContent,
  checkProtectedPage: security.checkProtectedPage,
  checkUnauthorizedBehaviour: security.checkUnauthorizedBehaviour,
  checkLogoutProtection: security.checkLogoutProtection,
  checkUrlSensitiveInfo: security.checkUrlSensitiveInfo,
  checkPasswordFieldType: security.checkPasswordFieldType,
  checkVisibleSecrets: security.checkVisibleSecrets,
  checkAdminPageProtection: security.checkAdminPageProtection,
  checkLoginWorkflow: security.checkLoginWorkflow,
  checkRoleBasedAccess: security.checkRoleBasedAccess,
  checkErrorInfoExposure: security.checkErrorInfoExposure,

  checkAvailability: technical.checkAvailability,
  checkPageLoad: technical.checkPageLoad,
  checkRequiredNavigation: technical.checkRequiredNavigation,
  checkInternalLinks: technical.checkInternalLinks,
  checkExpectedRouting: technical.checkExpectedRouting,
  checkPageTitle: technical.checkPageTitle,
  checkRequiredVisibility: technical.checkRequiredVisibility,
  checkInteractiveElements: technical.checkInteractiveElements,
  checkFormLabels: technical.checkFormLabels,
  checkImageAltText: technical.checkImageAltText,
  checkResponsiveLayout: technical.checkResponsiveLayout,
  checkContentOverflow: technical.checkContentOverflow,
  checkRequiredFieldValidation: technical.checkRequiredFieldValidation,
  checkInvalidInputFeedback: technical.checkInvalidInputFeedback,
  checkValidFormSubmission: technical.checkValidFormSubmission,
  checkDuplicateSubmission: technical.checkDuplicateSubmission,
  checkCriticalWorkflow: technical.checkCriticalWorkflow,
  checkUnhandledPageError: technical.checkUnhandledPageError,
  checkJavaScriptErrors: technical.checkJavaScriptErrors,
  checkResourceLoading: technical.checkResourceLoading,
  checkLoadingTimeout: technical.checkLoadingTimeout,
  checkKeyboardInteraction: technical.checkKeyboardInteraction,
  checkAccessibleButtonName: technical.checkAccessibleButtonName,
  checkAccessibleLinkName: technical.checkAccessibleLinkName,
  checkAccessibleFormControls: technical.checkAccessibleFormControls,
};

const BROWSER_LAUNCHERS: Record<string, typeof chromium> = { chromium, firefox, webkit };

async function readStdin(): Promise<string> {
  const chunks: Buffer[] = [];
  for await (const chunk of process.stdin) chunks.push(chunk as Buffer);
  return Buffer.concat(chunks).toString('utf-8');
}

async function main() {
  const raw = await readStdin();
  let input: WorkerInput;
  try {
    input = JSON.parse(raw);
  } catch (e: any) {
    process.stdout.write(JSON.stringify({ error: `Invalid input JSON: ${e?.message}` }));
    process.exit(1);
  }

  const browserNames = input.browsers?.length ? input.browsers : ['chromium'];
  const results: RuleResult[] = [];

  for (const browserName of browserNames) {
    const launcher = BROWSER_LAUNCHERS[browserName];
    if (!launcher) {
      for (const task of input.rules) {
        results.push({
          rule_code: task.rule_code,
          status: 'NOT_TESTABLE',
          tested_page: null,
          expected: null,
          observed: `Browser "${browserName}" is not supported by this worker.`,
          evidence: { timestamp: new Date().toISOString(), browser: browserName },
          duration_ms: 0,
        });
      }
      continue;
    }

    let browser: Browser;
    try {
      browser = await launcher.launch();
    } catch (e: any) {
      for (const task of input.rules) {
        results.push({
          rule_code: task.rule_code,
          status: 'NOT_TESTABLE',
          tested_page: null,
          expected: null,
          observed: `Could not launch browser "${browserName}": ${e?.message}`,
          evidence: { timestamp: new Date().toISOString(), browser: browserName },
          duration_ms: 0,
        });
      }
      continue;
    }

    const checkCtx: CheckContext = {
      websiteUrl: input.website_url,
      testContext: input.test_context ?? {},
      timeoutMs: input.timeout_ms ?? 15000,
      screenshotDir: input.screenshot_dir,
      browserName,
    };

    for (const task of input.rules) {
      const handler = HANDLERS[task.handler];
      const start = Date.now();

      if (!handler) {
        results.push({
          rule_code: task.rule_code,
          status: 'NOT_TESTABLE',
          tested_page: null,
          expected: null,
          observed: `No worker handler registered for "${task.handler}".`,
          evidence: baseEvidence(checkCtx),
          duration_ms: 0,
        });
        continue;
      }

      try {
        // Error isolation: one rule crashing must not abort the rest of the queue.
        const result = await handler(browser, checkCtx);
        results.push(result);
      } catch (e: any) {
        results.push({
          rule_code: task.rule_code,
          status: 'NOT_TESTABLE',
          tested_page: null,
          expected: null,
          observed: `Handler crashed: ${e?.message ?? String(e)}`,
          evidence: baseEvidence(checkCtx),
          duration_ms: Date.now() - start,
        });
      }
    }

    await browser.close();
  }

  process.stdout.write(JSON.stringify({ results }));
}

main().catch((e) => {
  process.stdout.write(JSON.stringify({ error: e?.message ?? String(e) }));
  process.exit(1);
});
