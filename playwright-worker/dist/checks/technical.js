import { baseEvidence, collectConsoleAndNetworkErrors, isSameOrigin, makeResult, resolveUrl, safeGoto } from '../utils.js';
export async function checkAvailability(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const { response, error } = await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl, http_status: response?.status() ?? null });
    const reachable = !error && !!response && response.status() < 500;
    return makeResult('TS-001', reachable ? 'PASS' : 'FAIL', ctx.websiteUrl, 'The submitted website must be reachable.', error ? `Navigation failed: ${error}` : `Received HTTP ${response?.status()}.`, evidence, start);
}
export async function checkPageLoad(browser, ctx) {
    const start = Date.now();
    const pages = ctx.testContext.critical_pages?.length ? ctx.testContext.critical_pages : ['/'];
    const context = await browser.newContext();
    const page = await context.newPage();
    const errors = [];
    for (const p of pages) {
        const url = resolveUrl(ctx.websiteUrl, p);
        const { response, error } = await safeGoto(page, url, ctx.timeoutMs);
        if (error || (response && response.status() >= 500)) {
            errors.push(`${p}: ${error ?? `HTTP ${response?.status()}`}`);
        }
    }
    await context.close();
    const evidence = baseEvidence(ctx);
    return makeResult('TS-002', errors.length === 0 ? 'PASS' : 'FAIL', pages.join(', '), 'Required pages must load without unexpected server errors.', errors.length === 0 ? 'All tested pages loaded without a server error.' : `Server errors on: ${errors.join('; ')}`, evidence, start);
}
export async function checkRequiredNavigation(browser, ctx) {
    const start = Date.now();
    const required = ctx.testContext.required_navigation ?? [];
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const missing = [];
    for (const label of required) {
        const visible = await page.locator(`a:has-text("${label}"), button:has-text("${label}")`).first().isVisible().catch(() => false);
        if (!visible)
            missing.push(label);
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-003', missing.length === 0 ? 'PASS' : 'FAIL', ctx.websiteUrl, 'Required navigation elements must exist, be visible, and usable.', missing.length === 0 ? 'All required navigation elements were found and visible.' : `Missing/invisible navigation: ${missing.join(', ')}`, evidence, start);
}
export async function checkInternalLinks(browser, ctx) {
    const start = Date.now();
    const MAX_LINKS = 25;
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const hrefs = await page.locator('a[href]').evaluateAll((els) => els.map((e) => e.href));
    const uniqueSameOrigin = [...new Set(hrefs)].filter((h) => isSameOrigin(ctx.websiteUrl, h)).slice(0, MAX_LINKS);
    const broken = [];
    for (const link of uniqueSameOrigin) {
        const { response, error } = await safeGoto(page, link, Math.min(ctx.timeoutMs, 10000));
        if (error || (response && response.status() >= 400)) {
            broken.push(`${link} (${error ?? response?.status()})`);
        }
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-004', broken.length === 0 ? 'PASS' : 'FAIL', ctx.websiteUrl, 'Required workflow links must not lead to invalid/unavailable pages.', broken.length === 0
        ? `Checked ${uniqueSameOrigin.length} same-origin link(s), none broken.`
        : `${broken.length}/${uniqueSameOrigin.length} link(s) broken: ${broken.slice(0, 5).join('; ')}`, evidence, start);
}
export async function checkExpectedRouting(browser, ctx) {
    const start = Date.now();
    const routes = ctx.testContext.expected_routes ?? [];
    const context = await browser.newContext();
    const page = await context.newPage();
    const mismatches = [];
    for (const r of routes) {
        await safeGoto(page, resolveUrl(ctx.websiteUrl, r.from), ctx.timeoutMs);
        try {
            await page.click(r.action_selector, { timeout: 5000 });
            await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
            const finalPath = new URL(page.url()).pathname;
            if (!finalPath.startsWith(r.expected_path)) {
                mismatches.push(`${r.from} -> expected ${r.expected_path}, got ${finalPath}`);
            }
        }
        catch (e) {
            mismatches.push(`${r.from}: action failed (${e?.message})`);
        }
    }
    await context.close();
    const evidence = baseEvidence(ctx);
    return makeResult('TS-005', mismatches.length === 0 ? 'PASS' : 'FAIL', routes.map((r) => r.from).join(', '), 'Navigation actions must lead to their expected destination.', mismatches.length === 0 ? 'All configured navigation actions reached their expected destination.' : mismatches.join('; '), evidence, start);
}
export async function checkPageTitle(browser, ctx) {
    const start = Date.now();
    const pages = ctx.testContext.critical_pages?.length ? ctx.testContext.critical_pages : ['/'];
    const context = await browser.newContext();
    const page = await context.newPage();
    const missing = [];
    for (const p of pages) {
        await safeGoto(page, resolveUrl(ctx.websiteUrl, p), ctx.timeoutMs);
        const title = await page.title();
        if (!title || title.trim().length < 3)
            missing.push(p);
    }
    await context.close();
    const evidence = baseEvidence(ctx);
    return makeResult('TS-006', missing.length === 0 ? 'PASS' : 'WARNING', pages.join(', '), 'Important pages should have an appropriate browser title.', missing.length === 0 ? 'All tested pages had a non-trivial title.' : `Missing/trivial title on: ${missing.join(', ')}`, evidence, start);
}
export async function checkRequiredVisibility(browser, ctx) {
    const start = Date.now();
    const pages = ctx.testContext.critical_pages ?? [];
    const context = await browser.newContext();
    const page = await context.newPage();
    const problems = [];
    for (const p of pages) {
        const { error } = await safeGoto(page, resolveUrl(ctx.websiteUrl, p), ctx.timeoutMs);
        if (error)
            problems.push(`${p}: failed to load (${error})`);
    }
    await context.close();
    const evidence = baseEvidence(ctx);
    return makeResult('TS-007', problems.length === 0 ? 'PASS' : 'FAIL', pages.join(', '), 'Required interface elements must be visible when users need them.', problems.length === 0 ? 'All configured critical pages loaded successfully.' : problems.join('; '), evidence, start);
}
export async function checkInteractiveElements(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const buttons = page.locator('button, a[href], input[type="submit"]');
    const count = await buttons.count();
    let usable = 0;
    const sample = Math.min(count, 15);
    for (let i = 0; i < sample; i++) {
        const el = buttons.nth(i);
        if (await el.isVisible().catch(() => false))
            usable++;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    const status = count === 0 ? 'NOT_TESTABLE' : usable === sample ? 'PASS' : 'WARNING';
    return makeResult('TS-008', status, ctx.websiteUrl, 'Buttons, links, and controls must be usable.', count === 0 ? 'No interactive controls were found.' : `${usable}/${sample} sampled interactive elements were visible/usable.`, evidence, start);
}
export async function checkFormLabels(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const inputs = page.locator('input:not([type="hidden"]):not([type="submit"]):not([type="button"]), textarea, select');
    const count = await inputs.count();
    let unlabeled = 0;
    const unlabeledSamples = [];
    for (let i = 0; i < count; i++) {
        const el = inputs.nth(i);
        const id = await el.getAttribute('id');
        const ariaLabel = await el.getAttribute('aria-label');
        const ariaLabelledBy = await el.getAttribute('aria-labelledby');
        let hasLabel = !!ariaLabel || !!ariaLabelledBy;
        if (!hasLabel && id) {
            hasLabel = (await page.locator(`label[for="${id}"]`).count()) > 0;
        }
        if (!hasLabel) {
            unlabeled++;
            const name = (await el.getAttribute('name')) ?? (await el.getAttribute('placeholder')) ?? `input#${i}`;
            unlabeledSamples.push(name);
        }
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    const status = count === 0 ? 'NOT_TESTABLE' : unlabeled === 0 ? 'PASS' : 'FAIL';
    return makeResult('TS-009', status, ctx.websiteUrl, 'Input fields should have identifiable labels or accessible descriptions.', count === 0 ? 'No form inputs were found on the page.' : unlabeled === 0 ? `All ${count} input(s) had an identifiable label.` : `${unlabeled}/${count} input(s) missing a label: ${unlabeledSamples.slice(0, 5).join(', ')}`, evidence, start);
}
export async function checkImageAltText(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const images = page.locator('img');
    const count = await images.count();
    let missing = 0;
    for (let i = 0; i < count; i++) {
        const alt = await images.nth(i).getAttribute('alt');
        if (alt === null)
            missing++;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    const status = count === 0 ? 'NOT_TESTABLE' : missing === 0 ? 'PASS' : 'WARNING';
    return makeResult('TS-010', status, ctx.websiteUrl, 'Meaningful images should provide alternative text.', count === 0 ? 'No images were found on the page.' : `${missing}/${count} image(s) had no alt attribute at all.`, evidence, start);
}
export async function checkResponsiveLayout(browser, ctx) {
    const start = Date.now();
    const viewports = ctx.testContext.viewports?.length
        ? ctx.testContext.viewports
        : [
            { name: 'desktop', width: 1280, height: 800 },
            { name: 'mobile', width: 375, height: 667 },
        ];
    const pages = ctx.testContext.critical_pages?.length ? ctx.testContext.critical_pages : ['/'];
    const problems = [];
    for (const vp of viewports) {
        const context = await browser.newContext({ viewport: { width: vp.width, height: vp.height } });
        const page = await context.newPage();
        for (const p of pages) {
            const { error } = await safeGoto(page, resolveUrl(ctx.websiteUrl, p), ctx.timeoutMs);
            if (error) {
                problems.push(`${vp.name} @ ${p}: ${error}`);
                continue;
            }
            const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 5);
            if (overflow)
                problems.push(`${vp.name} @ ${p}: horizontal overflow`);
        }
        await context.close();
    }
    const evidence = baseEvidence(ctx);
    return makeResult('TS-011', problems.length === 0 ? 'PASS' : 'FAIL', pages.join(', '), 'Critical pages must remain usable at configured desktop and mobile viewport sizes.', problems.length === 0 ? `Tested ${viewports.length} viewport(s) x ${pages.length} page(s), no issues.` : problems.join('; '), evidence, start);
}
export async function checkContentOverflow(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext({ viewport: { width: 375, height: 667 } });
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth + 5);
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl, viewport: '375x667' });
    return makeResult('TS-012', overflow ? 'FAIL' : 'PASS', ctx.websiteUrl, 'Critical content must not become unusable due to unintended horizontal overflow.', overflow ? 'Page content overflows the viewport width at a mobile size.' : 'No horizontal overflow detected at a mobile viewport size.', evidence, start);
}
export async function checkRequiredFieldValidation(browser, ctx) {
    const start = Date.now();
    const form = ctx.testContext.test_forms?.[0];
    if (!form)
        return makeResult('TS-013', 'NOT_TESTABLE', null, null, 'No test form configured.', baseEvidence(ctx), start);
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, resolveUrl(ctx.websiteUrl, form.page), ctx.timeoutMs);
    let prevented = false;
    try {
        if (form.submit_selector) {
            await page.click(form.submit_selector, { timeout: 5000 });
            await page.waitForTimeout(500);
            const stillOnPage = new URL(page.url()).pathname === new URL(resolveUrl(ctx.websiteUrl, form.page)).pathname;
            const validationMessage = form.required_field_selector
                ? await page.locator(form.required_field_selector).evaluate((el) => el.validationMessage || '').catch(() => '')
                : '';
            prevented = stillOnPage || validationMessage.length > 0;
        }
    }
    catch {
        prevented = false;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: form.page, selector: form.required_field_selector ?? null });
    return makeResult('TS-013', prevented ? 'PASS' : 'FAIL', form.page, 'Required fields must prevent or properly handle incomplete submission.', prevented ? 'Submitting with a required field empty was prevented or handled.' : 'Submission with a required field empty was not prevented.', evidence, start);
}
export async function checkInvalidInputFeedback(browser, ctx) {
    const start = Date.now();
    const form = ctx.testContext.test_forms?.[0];
    if (!form || !form.invalid_value || !form.required_field_selector) {
        return makeResult('TS-014', 'NOT_TESTABLE', null, null, 'No test form with an invalid value/selector configured.', baseEvidence(ctx), start);
    }
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, resolveUrl(ctx.websiteUrl, form.page), ctx.timeoutMs);
    let feedback = '';
    try {
        await page.fill(form.required_field_selector, form.invalid_value, { timeout: 5000 });
        if (form.submit_selector)
            await page.click(form.submit_selector, { timeout: 5000 });
        await page.waitForTimeout(500);
        feedback = await page.locator(form.required_field_selector).evaluate((el) => el.validationMessage || '').catch(() => '');
        if (!feedback) {
            const errorText = await page.locator('[role="alert"], .error, .invalid-feedback').first().innerText().catch(() => '');
            feedback = errorText;
        }
    }
    catch {
        // fallthrough with empty feedback
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: form.page, selector: form.required_field_selector });
    return makeResult('TS-014', feedback ? 'PASS' : 'WARNING', form.page, 'Invalid input should produce understandable feedback.', feedback ? `Feedback observed: "${feedback}"` : 'No validation feedback was observed for the invalid value.', evidence, start);
}
export async function checkValidFormSubmission(browser, ctx) {
    const start = Date.now();
    const form = ctx.testContext.test_forms?.[0];
    if (!form || !form.valid_values) {
        return makeResult('TS-015', 'NOT_TESTABLE', null, null, 'No test form with safe valid values configured.', baseEvidence(ctx), start);
    }
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, resolveUrl(ctx.websiteUrl, form.page), ctx.timeoutMs);
    let succeeded = false;
    try {
        for (const [selector, value] of Object.entries(form.valid_values)) {
            await page.fill(selector, value, { timeout: 5000 });
        }
        const urlBefore = page.url();
        if (form.submit_selector) {
            await page.click(form.submit_selector, { timeout: 5000 });
            await page.waitForLoadState('load', { timeout: ctx.timeoutMs }).catch(() => { });
        }
        succeeded = page.url() !== urlBefore || (await page.locator('[role="alert"], .success, .alert-success').first().isVisible().catch(() => false));
    }
    catch {
        succeeded = false;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: form.page });
    return makeResult('TS-015', succeeded ? 'PASS' : 'WARNING', form.page, 'Valid input should allow the expected workflow to continue.', succeeded ? 'Submitting valid test data produced a visible change (navigation or success indicator).' : 'No visible change was observed after submitting valid test data.', evidence, start);
}
export async function checkDuplicateSubmission(browser, ctx) {
    const start = Date.now();
    if (!ctx.testContext.allow_duplicate_submission_test) {
        return makeResult('TS-016', 'NOT_TESTABLE', null, null, 'Duplicate-submission testing was not explicitly enabled -- avoiding unsafe repeated production actions.', baseEvidence(ctx), start);
    }
    const form = ctx.testContext.test_forms?.[0];
    if (!form || !form.valid_values || !form.submit_selector) {
        return makeResult('TS-016', 'NOT_TESTABLE', null, null, 'No safe test form configured for duplicate-submission testing.', baseEvidence(ctx), start);
    }
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, resolveUrl(ctx.websiteUrl, form.page), ctx.timeoutMs);
    try {
        for (const [selector, value] of Object.entries(form.valid_values)) {
            await page.fill(selector, value, { timeout: 5000 });
        }
        await page.click(form.submit_selector, { timeout: 5000 });
        await page.waitForTimeout(300);
        const disabledAfterFirst = await page.locator(form.submit_selector).isDisabled().catch(() => false);
        await context.close();
        const evidence = baseEvidence(ctx, { url: form.page });
        return makeResult('TS-016', disabledAfterFirst ? 'PASS' : 'WARNING', form.page, 'Repeated interaction must not unintentionally create duplicate operations.', disabledAfterFirst ? 'The submit control was disabled after first submission, preventing an easy duplicate.' : 'The submit control remained enabled after submission -- a duplicate click was not structurally prevented.', evidence, start);
    }
    catch (e) {
        await context.close();
        return makeResult('TS-016', 'NOT_TESTABLE', form.page, null, `Could not execute duplicate-submission test: ${e?.message}`, baseEvidence(ctx), start);
    }
}
export async function checkCriticalWorkflow(browser, ctx) {
    const start = Date.now();
    const workflows = ctx.testContext.critical_workflows ?? [];
    if (workflows.length === 0)
        return makeResult('TS-017', 'NOT_TESTABLE', null, null, 'No critical workflows configured.', baseEvidence(ctx), start);
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const failures = [];
    for (const wf of workflows) {
        for (const step of wf.steps) {
            try {
                await page.click(step, { timeout: 5000 });
            }
            catch (e) {
                failures.push(`${wf.name} step "${step}" failed: ${e?.message}`);
                break;
            }
        }
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-017', failures.length === 0 ? 'PASS' : 'FAIL', ctx.websiteUrl, 'Critical business workflows should be executable end-to-end.', failures.length === 0 ? `${workflows.length} configured workflow(s) completed all steps.` : failures.join('; '), evidence, start);
}
export async function checkUnhandledPageError(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const { pageErrors } = collectConsoleAndNetworkErrors(page);
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    await page.waitForTimeout(1000);
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-018', pageErrors.length === 0 ? 'PASS' : 'FAIL', ctx.websiteUrl, 'Critical workflows must not encounter an unhandled browser error.', pageErrors.length === 0 ? 'No unhandled page errors were raised during load.' : `${pageErrors.length} unhandled error(s): ${pageErrors.slice(0, 3).join('; ')}`, evidence, start);
}
export async function checkJavaScriptErrors(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const { consoleErrors, pageErrors } = collectConsoleAndNetworkErrors(page);
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    await page.waitForTimeout(1000);
    await context.close();
    const severe = [...pageErrors, ...consoleErrors];
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl, console_errors: severe });
    return makeResult('TS-019', severe.length === 0 ? 'PASS' : 'FAIL', ctx.websiteUrl, 'Severe uncaught JavaScript errors must not prevent expected behaviour.', severe.length === 0 ? 'No console/page errors were observed.' : `${severe.length} JS error(s) observed: ${severe.slice(0, 3).join('; ')}`, evidence, start);
}
export async function checkResourceLoading(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const failed = [];
    page.on('requestfailed', (req) => failed.push(req.url()));
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl, network_errors: failed });
    return makeResult('TS-020', failed.length === 0 ? 'PASS' : 'WARNING', ctx.websiteUrl, 'Resources required for a tested workflow must load successfully.', failed.length === 0 ? 'All requested resources loaded successfully.' : `${failed.length} resource(s) failed to load.`, evidence, start);
}
export async function checkLoadingTimeout(browser, ctx) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    const { error } = await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const elapsed = Date.now() - start;
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-021', error ? 'FAIL' : 'PASS', ctx.websiteUrl, `Required content must become available within the configured timeout (${ctx.timeoutMs}ms).`, error ? `Page did not load within the timeout: ${error}` : `Page loaded in ${elapsed}ms.`, evidence, start);
}
export async function checkKeyboardInteraction(browser, ctx) {
    const start = Date.now();
    const workflows = ctx.testContext.critical_workflows ?? [];
    if (workflows.length === 0)
        return makeResult('TS-023', 'NOT_TESTABLE', null, null, 'No critical workflows configured for keyboard testing.', baseEvidence(ctx), start);
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    let reached = false;
    try {
        await page.keyboard.press('Tab');
        const active = await page.evaluate(() => document.activeElement?.tagName ?? null);
        reached = !!active && active !== 'BODY';
    }
    catch {
        reached = false;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    return makeResult('TS-023', reached ? 'PASS' : 'WARNING', ctx.websiteUrl, 'Critical workflows should support keyboard-based interaction.', reached ? 'Tab navigation successfully moved focus to an interactive element.' : 'Tab navigation did not appear to move focus to an interactive element.', evidence, start);
}
async function checkAccessibleName(browser, ctx, ruleCode, selector, label) {
    const start = Date.now();
    const context = await browser.newContext();
    const page = await context.newPage();
    await safeGoto(page, ctx.websiteUrl, ctx.timeoutMs);
    const els = page.locator(selector);
    const count = await els.count();
    let unnamed = 0;
    for (let i = 0; i < count; i++) {
        const el = els.nth(i);
        const text = (await el.innerText().catch(() => '')).trim();
        const ariaLabel = await el.getAttribute('aria-label');
        const title = await el.getAttribute('title');
        if (!text && !ariaLabel && !title)
            unnamed++;
    }
    await context.close();
    const evidence = baseEvidence(ctx, { url: ctx.websiteUrl });
    const status = count === 0 ? 'NOT_TESTABLE' : unnamed === 0 ? 'PASS' : 'FAIL';
    return makeResult(ruleCode, status, ctx.websiteUrl, `Required ${label} need visible text or an accessible name.`, count === 0 ? `No ${label} were found.` : unnamed === 0 ? `All ${count} ${label} have an accessible name.` : `${unnamed}/${count} ${label} have no accessible name.`, evidence, start);
}
export const checkAccessibleButtonName = (browser, ctx) => checkAccessibleName(browser, ctx, 'TS-024', 'button, input[type="submit"], input[type="button"]', 'buttons');
export const checkAccessibleLinkName = (browser, ctx) => checkAccessibleName(browser, ctx, 'TS-025', 'a[href]', 'links');
export const checkAccessibleFormControls = (browser, ctx) => checkAccessibleName(browser, ctx, 'TS-026', 'input:not([type="hidden"]), select, textarea', 'form controls');
